import os

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg


REMOTE_PATH = "var/smoke/github-actions-write-smoke.txt"


def main() -> None:
    content = build_content()
    with SftpClient(PipelineCfg("deploy")) as client:
        write_and_verify(client, REMOTE_PATH, content)


def build_content() -> str:
    run_id = os.environ.get("GITHUB_RUN_ID", "local")
    return f"sftp-write-smoke={run_id}\n"


def write_and_verify(client: SftpClient, remote_path: str, content: str) -> None:
    client.ensure_dir(parent_dir(remote_path))
    client.put_text(remote_path, content)
    actual = client.read_file(remote_path)
    if actual != content:
        raise RuntimeError(f"SFTP-Schreib-Smoke konnte {remote_path} nicht verifizieren")
    print(f"[sftp-smoke] geschrieben und verifiziert: {remote_path}", flush=True)


def parent_dir(path: str) -> str:
    return path.rsplit("/", 1)[0] if "/" in path else ""


if __name__ == "__main__":
    main()
