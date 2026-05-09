import os
import subprocess
import sys
from pathlib import Path


def run_command(command: list[str]) -> int:
    result = subprocess.run(command, check=False)
    return result.returncode


def run_pylint(command: list[str]) -> int:
    cache_dir = Path(".local") / "cache" / "pylint"
    cache_dir.mkdir(parents=True, exist_ok=True)
    env = os.environ.copy()
    env["PYLINTHOME"] = str(cache_dir)
    result = subprocess.run(command, check=False, env=env)
    return result.returncode


def python_paths() -> list[str]:
    return [
        "src/cli/py",
        "scripts",
        "tests/py",
    ]


def main() -> int:
    paths = python_paths()
    ruff_status = run_command([sys.executable, "-m", "ruff", "check", *paths])
    pylint_status = run_pylint([sys.executable, "-m", "pylint", *paths])
    if ruff_status != 0:
        return ruff_status
    return pylint_status


if __name__ == "__main__":
    raise SystemExit(main())
