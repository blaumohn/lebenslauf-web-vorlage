import sys
from pathlib import Path

from cli.py.deploy.content_tree import fetch_content_tree
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

SFTP_DATA_DIR = "etc/content"


def main() -> None:
    build_cfg = PipelineCfg("build")
    content_path = Path(build_cfg["CONTENT_PATH"])
    deploy_cfg = PipelineCfg("deploy")
    with SftpClient(deploy_cfg) as client:
        if not client.dir_exists(SFTP_DATA_DIR):
            print_sftp_missing_error()
            sys.exit(1)
        fetch_to(client, content_path)


def fetch_to(client: SftpClient, local_dir: Path) -> None:
    total = fetch_content_tree(client, SFTP_DATA_DIR, local_dir)
    print(f"[content-fetch] {total} Datei(en) nach {local_dir}", flush=True)


def print_sftp_missing_error() -> None:
    print(
        f"FEHLER: Content-Daten fehlen auf SFTP ({SFTP_DATA_DIR}).\n"
        "Lokal ausführen: cli python <pipeline> --phase deploy \\\n"
        "  scripts/local/content-sftp-push.py <pfad>",
        file=sys.stderr,
    )


if __name__ == "__main__":
    main()
