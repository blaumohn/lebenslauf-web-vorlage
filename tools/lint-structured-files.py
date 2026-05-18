import json
import subprocess
import tomllib
import xml.etree.ElementTree as xml_tree
from pathlib import Path

SKIPPED_DIRS = {
    ".git",
    ".pytest_cache",
    ".venv",
    "node_modules",
    "var",
    "vendor",
}

SKIPPED_FILES = {
    "composer.lock",
    "package-lock.json",
}

YAML_SUFFIXES = {
    ".yaml",
    ".yml",
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
    if not path.exists():
        return True
    if path.name in SKIPPED_FILES:
        return True
    return bool(SKIPPED_DIRS.intersection(path.parts))


def selected_paths() -> list[Path]:
    result = []
    for path in tracked_files():
        if should_skip(path):
            continue
        if path.suffix in {".json", ".toml", ".xml", ".yaml", ".yml"}:
            result.append(path)
    return result


def lint_json(path: Path) -> None:
    json.loads(path.read_text(encoding="utf-8"))


def lint_toml(path: Path) -> None:
    tomllib.loads(path.read_text(encoding="utf-8"))


def lint_xml(path: Path) -> None:
    xml_tree.parse(path)


def yaml_lint_command(path: Path) -> list[str]:
    return ["vendor/bin/yaml-lint", path.as_posix()]


def lint_yaml(path: Path) -> None:
    result = subprocess.run(
        yaml_lint_command(path),
        check=False,
        capture_output=True,
        text=True,
    )
    if result.returncode == 0:
        return
    message = result.stdout.strip() or result.stderr.strip()
    raise RuntimeError(message)


def lint_path(path: Path) -> None:
    if path.suffix == ".json":
        lint_json(path)
        return
    if path.suffix == ".toml":
        lint_toml(path)
        return
    if path.suffix == ".xml":
        lint_xml(path)
        return
    if path.suffix in YAML_SUFFIXES:
        lint_yaml(path)


def lint_paths(paths: list[Path]) -> list[str]:
    errors = []
    for path in paths:
        try:
            lint_path(path)
        except Exception as exc:
            errors.append(f"{path}: {exc}")
    return errors


def main() -> int:
    errors = lint_paths(selected_paths())
    if errors == []:
        return 0
    print("Strukturierte Dateien sind ungültig:")
    print("\n".join(errors))
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
