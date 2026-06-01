import argparse
from datetime import datetime, timezone

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg


DEFAULT_REMOTE_PATH = "var/smoke/local-sftp-auth-smoke.txt"


def main() -> None:
    args = parse_args()
    content = build_content()
    with SftpClient(PipelineCfg("deploy")) as client:
        write_and_verify(client, args.remote_path, content)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="SFTP-Authentifizierung testen")
    parser.add_argument("--remote-path", default=DEFAULT_REMOTE_PATH)
    return parser.parse_args()


def build_content() -> str:
    timestamp = datetime.now(timezone.utc).isoformat()
    return f"sftp-auth-smoke {timestamp}\n"


def write_and_verify(client: SftpClient, remote_path: str, content: str) -> None:
    client.ensure_dir(parent_dir(remote_path))
    client.put_text(remote_path, content)
    actual = client.read_file(remote_path)
    if actual != content:
        raise RuntimeError(f"SFTP-Smoke konnte {remote_path} nicht verifizieren")
    print(f"[sftp-smoke] geschrieben und verifiziert: {remote_path}", flush=True)


def parent_dir(path: str) -> str:
    return path.rsplit("/", 1)[0] if "/" in path else ""


if __name__ == "__main__":
    main()
