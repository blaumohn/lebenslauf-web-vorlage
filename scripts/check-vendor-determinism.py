import hashlib
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

REPO_DIR = Path("/repo")
COMPOSER_ARGS = ["composer", "install", "--optimize-autoloader", "--no-interaction"]
DIFF_HEAD = 30


def install_vendor(dest: Path) -> None:
    shutil.copy(REPO_DIR / "composer.lock", dest / "composer.lock")
    shutil.copy(REPO_DIR / "composer.json", dest / "composer.json")
    subprocess.run(
        COMPOSER_ARGS, cwd=dest, check=True,
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )


def vendor_checksum(vendor_dir: Path) -> str:
    h = hashlib.sha256()
    for p in sorted((vendor_dir / "composer").iterdir()):
        if p.is_file():
            text = p.read_text(encoding="utf-8")
            normalized = re.sub(
                r"(Composer(?:Autoloader|Static)Init)[0-9a-f]{32}",
                r"\1***",
                text,
            )
            h.update(normalized.encode())
    return h.hexdigest()[:16]


def show_diff(dir_a: Path, dir_b: Path) -> None:
    result = subprocess.run(
        ["diff", "-r", str(dir_a / "vendor" / "composer"),
                       str(dir_b / "vendor" / "composer")],
        capture_output=True, text=True,
    )
    for line in result.stdout.splitlines()[:DIFF_HEAD]:
        print(line, file=sys.stderr)


def main() -> None:
    with tempfile.TemporaryDirectory() as tmp_a, tempfile.TemporaryDirectory() as tmp_b:
        a, b = Path(tmp_a), Path(tmp_b)
        install_vendor(a)
        install_vendor(b)
        cs_a = vendor_checksum(a / "vendor")
        cs_b = vendor_checksum(b / "vendor")
        if cs_a == cs_b:
            print(f"Checksum deterministisch: {cs_a}")
            return
        print(
            f"Checksum nicht deterministisch: {cs_a!r} vs {cs_b!r}",
            file=sys.stderr,
        )
        show_diff(a, b)
        sys.exit(1)


if __name__ == "__main__":
    main()
