import os
import subprocess
import uuid

COMPOSE_FILE = "docker-compose.ci.yml"


def build_run_id(prefix: str) -> str:
    return f"{prefix}-{uuid.uuid4().hex}"


def runner_env(prefix: str) -> dict[str, str]:
    env = os.environ.copy()
    env["PIPELINE_RUN_ID"] = build_run_id(prefix)
    return env


def compose_cmd(*args) -> list[str]:
    return ["docker", "compose", "-f", COMPOSE_FILE, *args]


def compose(*args, check=True, label: str = "", env=None, capture_output=False) -> subprocess.CompletedProcess:
    cmd = ["docker", "compose", "-f", COMPOSE_FILE, *args]
    return run(cmd, check=check, label=label, env=env, capture_output=capture_output)


def run(cmd, check=True, label: str = "", env=None, capture_output=False) -> subprocess.CompletedProcess:
    result = subprocess.run(cmd, env=env, capture_output=capture_output, text=capture_output)
    if check and result.returncode != 0:
        context = label if label else " ".join(cmd)
        raise RuntimeError(f"[runner] Fehlgeschlagen: {context} (Exit-Code: {result.returncode})")
    return result
