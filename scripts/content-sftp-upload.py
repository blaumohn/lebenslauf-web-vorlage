import sys
from pathlib import Path

from cli.py.deploy.content_tree import upload_content_tree
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

SFTP_DATA_DIR = "etc/content"


def main() -> None:
    deploy_cfg = PipelineCfg("deploy")
    with SftpClient(deploy_cfg) as client:
        assert_dir_empty(client)
        build_cfg = PipelineCfg("build")
        local_path = Path(build_cfg["CONTENT_PATH"])
        if not local_path.is_dir():
            print(
                f"FEHLER: Content-Pfad nicht gefunden: {local_path}",
                file=sys.stderr,
            )
            sys.exit(1)
        upload(client, local_path)


def assert_dir_empty(client: SftpClient) -> None:
    if not client.dir_exists(SFTP_DATA_DIR):
        return
    entries = list(client.listdir_attr(SFTP_DATA_DIR))
    if not entries:
        return
    print(
        f"FEHLER: {SFTP_DATA_DIR} auf SFTP enthält {len(entries)} Einträge. "
        "Vorheriger Workflow hat nicht bereinigt.",
        file=sys.stderr,
    )
    sys.exit(1)


def upload(client: SftpClient, source: Path) -> None:
    total = upload_content_tree(client, source, SFTP_DATA_DIR)
    print(f"[content-upload] {total} Datei(en) nach {SFTP_DATA_DIR}", flush=True)


if __name__ == "__main__":
    main()
