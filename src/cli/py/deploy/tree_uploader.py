import time
from dataclasses import dataclass
from pathlib import Path


@dataclass
class UploadStats:
    directories: int = 0
    files: int = 0
    bytes: int = 0

    def merge(self, other: "UploadStats") -> None:
        self.directories += other.directories
        self.files += other.files
        self.bytes += other.bytes


class SftpTreeUploader:
    def __init__(self, client, log):
        self.client = client
        self.log = log

    def upload_dir(self, local_path: Path, rel_remote: str) -> UploadStats:
        stats = UploadStats()
        started_at = time.monotonic()
        self._recurse(Path(local_path), rel_remote, stats)
        duration = time.monotonic() - started_at
        self.log(
            f"Hochgeladen ({rel_remote}): "
            f"{stats.files} Dateien, {stats.bytes} Bytes, {duration:.2f}s"
        )
        return stats

    def upload_file(self, local_path: Path, rel_remote: str) -> int:
        parent = str(Path(rel_remote).parent).replace("\\", "/")
        self.client.ensure_dir(parent)
        self.client.put_file(local_path, rel_remote)
        return Path(local_path).stat().st_size

    def upload_item(self, item: Path, remote_dir: str) -> UploadStats:
        rel_remote = f"{remote_dir}/{item.name}"
        stats = UploadStats()
        if item.is_dir():
            self._recurse(item, rel_remote, stats)
        else:
            stats.bytes += self.upload_file(item, rel_remote)
            stats.files += 1
        return stats

    def _recurse(self, local_path: Path, rel_remote: str, stats: UploadStats) -> None:
        if self.client.mkdir_p(rel_remote):
            stats.directories += 1
        for item in sorted(local_path.iterdir()):
            rel_child = f"{rel_remote}/{item.name}"
            if item.is_dir():
                self._recurse(item, rel_child, stats)
            else:
                stats.bytes += self.upload_file(item, rel_child)
                stats.files += 1
