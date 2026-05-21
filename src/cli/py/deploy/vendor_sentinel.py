import hashlib
from dataclasses import dataclass
from pathlib import Path

from cli.py.util.structured_text import IniModel, JsonDocument


class ComposerInputChecksum:
    FILENAMES = ("composer.json", "composer.lock")

    @classmethod
    def from_repo(cls, repo_dir: Path | str = ".") -> str:
        root = Path(repo_dir)
        checksum = hashlib.sha256()
        for filename in cls.FILENAMES:
            checksum.update(filename.encode("utf-8"))
            checksum.update(b"\0")
            content = JsonDocument.canonical_file_bytes(root / filename)
            checksum.update(content)
            checksum.update(b"\0")
        return checksum.hexdigest()[:16]

    @classmethod
    def diff_paths(cls, repo_dir: Path | str = ".") -> dict[str, Path]:
        root = Path(repo_dir)
        return {filename: root / filename for filename in cls.FILENAMES}


@dataclass(frozen=True)
class VendorSentinel(IniModel):
    schema = {"vendor": {"checksum": str}}

    vendor_checksum: str
