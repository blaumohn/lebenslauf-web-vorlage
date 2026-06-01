import subprocess
from pathlib import Path

MAX_LINE_LENGTH = 72

SKIPPED_DIRS = {
    ".git",
    ".pytest_cache",
    ".venv",
    "node_modules",
    "var",
    "vendor",
    "__pycache__",
}

SKIPPED_FILES = {
    "composer.lock",
    "package-lock.json",
}

SKIPPED_PREFIXES = (
    "public/css/",
)

BINARY_SUFFIXES = {
    ".crt",
    ".key",
    ".pub",
    ".pyc",
}


def tracked_files() -> list[Path]:
    result = subprocess.run(
        ["git", "ls-files"],
        check=False,
        capture_output=True,
        text=True,
    )
    if result.returncode != 0:
        return walked_files()
    paths = result.stdout.splitlines()
    return [Path(path) for path in paths if path]


def walked_files() -> list[Path]:
    result = []
    for path in Path(".").rglob("*"):
        if path.is_file():
            result.append(path)
    return result


def should_skip(path: Path) -> bool:
    path_text = path.as_posix()
    if not path.exists():
        return True
    if path.name in SKIPPED_FILES:
        return True
    if path_text.startswith(SKIPPED_PREFIXES):
        return True
    if path.suffix in BINARY_SUFFIXES:
        return True
    return bool(SKIPPED_DIRS.intersection(path.parts))


def long_lines(path: Path) -> list[str]:
    errors = []
    for number, line in file_lines(path):
        length = len(line.rstrip("\n\r"))
        if length <= MAX_LINE_LENGTH:
            continue
        errors.append(f"{path}:{number}: {length} > {MAX_LINE_LENGTH}")
    return errors


def file_lines(path: Path) -> list[tuple[int, str]]:
    try:
        text = path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        return []
    lines = text.splitlines(keepends=True)
    return list(enumerate(lines, start=1))


def lint_paths(paths: list[Path]) -> list[str]:
    errors = []
    for path in paths:
        if should_skip(path):
            continue
        errors.extend(long_lines(path))
    return errors


def main() -> int:
    errors = lint_paths(tracked_files())
    if errors == []:
        return 0
    print("Zeilen sind länger als 72 Zeichen:")
    print("\n".join(errors))
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
