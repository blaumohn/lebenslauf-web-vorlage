import json
import subprocess


def run_filtered(
    cmd: list[str],
    env: dict[str, str],
    suppress_types: set[str],
) -> int:
    with subprocess.Popen(cmd, env=env, stdout=subprocess.PIPE, text=True) as proc:
        _filter_stream(proc.stdout, suppress_types)
    return proc.returncode


def reformat_log_line(line: str) -> str:
    try:
        entry = json.loads(line)
    except (json.JSONDecodeError, ValueError):
        return line
    prefix = entry.get("prefix", "")
    message = entry.get("message", line)
    return f"[{prefix}] {message}"


def is_suppressed_log_line(line: str, suppress_types: set[str]) -> bool:
    try:
        entry = json.loads(line)
    except (json.JSONDecodeError, ValueError):
        return False
    return entry.get("type") in suppress_types


def _filter_stream(stdout, suppress_types: set[str]) -> None:
    for line in stdout:
        stripped = line.rstrip("\n")
        if not is_suppressed_log_line(stripped, suppress_types):
            print(reformat_log_line(stripped), flush=True)
