import time
from collections.abc import Callable
from dataclasses import dataclass
from pathlib import Path

from cli.py.deploy.vendor_sentinel import VendorSentinel


@dataclass
class UploadStats:
    directories: int = 0
    files: int = 0
    bytes: int = 0


class SftpTreeUploader:
    def __init__(
        self,
        client,
        staging_dir: Path,
        run_id: str,
        vendor_checksum: Callable[[], str],
        logger,
    ):
        self.client = client
        self.staging_dir = staging_dir
        self.run_id = run_id
        self.vendor_checksum = vendor_checksum
        self.log = logger

    def upload_app_tree(self, app_dir: str, vendor_dir: str) -> None:
        self._inject_vendor_dir(vendor_dir)
        stats = UploadStats()
        started_at = time.monotonic()
        self.log(f"Upload App-Slot: {app_dir}")
        self._prepare_slot(app_dir)
        for item in sorted(self.staging_dir.iterdir()):
            self._upload_app_item(item, app_dir, stats)
        self.client.put_text(f"{app_dir}/.deploy-run", self.run_id)
        self._log_app_upload(app_dir, stats, started_at)

    def upload_vendor_dir(self, vendor_dir: str) -> None:
        stats = UploadStats()
        started_at = time.monotonic()
        self.log(f"Upload Vendor: {vendor_dir}")
        self._prepare_slot(vendor_dir)
        for item in sorted((self.staging_dir / "vendor").iterdir()):
            self.upload_item(item, vendor_dir, stats)
        sentinel = VendorSentinel(self.vendor_checksum())
        self.client.put_text(f"{vendor_dir}/.meta", sentinel.to_text())
        self._log_vendor_upload(stats, started_at)

    def upload_item(
        self,
        item: Path,
        remote_dir: str,
        stats: UploadStats,
    ) -> None:
        rel_remote = remote_dir + "/" + item.name
        if item.is_dir():
            self.upload_dir(item, rel_remote, stats)
            return
        self.upload_file(item, rel_remote, stats)

    def upload_dir(
        self,
        local_path: Path,
        rel_remote: str,
        stats: UploadStats,
    ) -> None:
        if self.client.mkdir_p(rel_remote):
            stats.directories += 1
        for item in sorted(Path(local_path).iterdir()):
            rel_child = rel_remote + "/" + item.name
            if item.is_dir():
                self.upload_dir(item, rel_child, stats)
            else:
                self.upload_file(item, rel_child, stats)

    def upload_file(
        self,
        local_path: Path,
        rel_remote: str,
        stats: UploadStats,
    ) -> None:
        parent = str(Path(rel_remote).parent).replace("\\", "/")
        self.client.ensure_dir(parent)
        self.client.put_file(local_path, rel_remote)
        stats.files += 1
        stats.bytes += Path(local_path).stat().st_size

    def _upload_app_item(
        self,
        item: Path,
        app_dir: str,
        stats: UploadStats,
    ) -> None:
        if item.name == "vendor":
            return
        self.upload_item(item, app_dir, stats)

    def _prepare_slot(self, slot_dir: str) -> None:
        self.client.remove_dir(slot_dir)
        self.client.ensure_dir(slot_dir)

    def _inject_vendor_dir(self, vendor_dir: str) -> None:
        index_php = self.staging_dir / "public/index.php"
        original = index_php.read_text(encoding="utf-8")
        old = "$vendorDir  = $appSlot . '/vendor';"
        new = f"$vendorDir  = dirname(__DIR__, 2) . '/{vendor_dir}';"
        if old not in original:
            raise RuntimeError(
                f"index.php: Zeile '{old}' nicht gefunden — "
                "Vendor-Inject fehlgeschlagen. "
                "Wenn diese Zeile geändert wurde, muss auch "
                "_inject_vendor_dir() angepasst werden. "
                "Siehe: https://docs.template.ysdani.com/de/areas/deploy/slot-switch/"
            )
        index_php.write_text(
            original.replace(old, new, 1), encoding="utf-8"
        )

    def _log_app_upload(
        self,
        app_dir: str,
        stats: UploadStats,
        started_at: float,
    ) -> None:
        duration = time.monotonic() - started_at
        self.log(
            f"App-Slot hochgeladen ({app_dir}): "
            f"{stats.files} Dateien, "
            f"{stats.directories} Verzeichnisse, "
            f"{stats.bytes} Bytes, {duration:.2f}s"
        )

    def _log_vendor_upload(
        self,
        stats: UploadStats,
        started_at: float,
    ) -> None:
        duration = time.monotonic() - started_at
        self.log(
            f"Vendor hochgeladen: {stats.files} Dateien, "
            f"{stats.bytes} Bytes, {duration:.2f}s"
        )
