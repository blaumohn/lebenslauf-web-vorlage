import time
from collections.abc import Callable
from pathlib import Path

from cli.py.deploy.tree_uploader import SftpTreeUploader, UploadStats
from cli.py.deploy.vendor_sentinel import VendorSentinel


class SftpDeployUploader:
    def __init__(
        self,
        client,
        staging_dir: Path,
        run_id: str,
        vendor_checksum: Callable[[], str],
        log,
    ):
        self._tree = SftpTreeUploader(client, log)
        self.client = client
        self.staging_dir = staging_dir
        self.run_id = run_id
        self.vendor_checksum = vendor_checksum
        self.log = log

    def prepare_app_slot(self, app_dir: str) -> None:
        self._prepare_slot(app_dir)

    def upload_app_tree(self, app_dir: str, vendor_dir: str) -> None:
        self._inject_vendor_dir(vendor_dir)
        started_at = time.monotonic()
        self.log(f"Upload App-Slot: {app_dir}")
        stats = UploadStats()
        for item in sorted(self.staging_dir.iterdir()):
            if item.name == "vendor":
                continue
            stats.merge(self._tree.upload_item(item, app_dir))
        self.client.put_text(f"{app_dir}/.deploy-run", self.run_id)
        self._log_app_upload(app_dir, stats, started_at)

    def upload_vendor_dir(self, vendor_dir: str) -> None:
        started_at = time.monotonic()
        self.log(f"Upload Vendor: {vendor_dir}")
        self._prepare_slot(vendor_dir)
        stats = UploadStats()
        for item in sorted((self.staging_dir / "vendor").iterdir()):
            stats.merge(self._tree.upload_item(item, vendor_dir))
        sentinel = VendorSentinel(self.vendor_checksum())
        self.client.put_text(f"{vendor_dir}/.meta", sentinel.to_text())
        self._log_vendor_upload(stats, started_at)

    def _prepare_slot(self, slot_dir: str) -> None:
        self.client.remove_dir(slot_dir)
        self.client.ensure_dir(slot_dir)

    def _inject_vendor_dir(self, vendor_dir: str) -> None:
        index_php = self.staging_dir / "public/index.php"
        original = index_php.read_text(encoding="utf-8")
        old = "$vendorDir  = $appSlot . '/vendor';"
        new = f"$vendorDir  = dirname(__DIR__, 2) . '/{vendor_dir}';"
        count = original.count(old)
        if count != 1:
            raise RuntimeError(
                f"index.php: Inject-Marker {count}× gefunden, erwartet genau 1 — "
                "Vendor-Inject fehlgeschlagen. "
                "Wenn diese Zeile geändert wurde, muss auch "
                "_inject_vendor_dir() angepasst werden. "
                "Siehe: https://docs.template.ysdani.com/de/areas/deploy/slot-switch/"
            )
        index_php.write_text(original.replace(old, new, 1), encoding="utf-8")

    def _log_app_upload(self, app_dir: str, stats: UploadStats, started_at: float) -> None:
        duration = time.monotonic() - started_at
        self.log(
            f"App-Slot hochgeladen ({app_dir}): "
            f"{stats.files} Dateien, "
            f"{stats.directories} Verzeichnisse, "
            f"{stats.bytes} Bytes, {duration:.2f}s"
        )

    def _log_vendor_upload(self, stats: UploadStats, started_at: float) -> None:
        duration = time.monotonic() - started_at
        self.log(
            f"Vendor hochgeladen: {stats.files} Dateien, "
            f"{stats.bytes} Bytes, {duration:.2f}s"
        )
