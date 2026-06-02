import sys
from pathlib import Path

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

SFTP_DATA_DIR = "etc/lebenslauf"


def main() -> None:
    build_cfg = PipelineCfg("build")
    data_path = Path(build_cfg["LEBENSLAUF_DATEN_PFAD"])
    if data_path.is_dir():
        sys.exit(0)
    deploy_cfg = PipelineCfg("deploy")
    with SftpClient(deploy_cfg) as client:
        if not client.dir_exists(SFTP_DATA_DIR):
            print_sftp_missing_error()
            sys.exit(1)
        fetch_to(client, data_path)


def fetch_to(client: SftpClient, local_dir: Path) -> None:
    local_dir.mkdir(parents=True, exist_ok=True)
    for entry in client.listdir_attr(SFTP_DATA_DIR):
        remote = f"{SFTP_DATA_DIR}/{entry.filename}"
        client.get_file(remote, local_dir / entry.filename)
    count = sum(1 for _ in local_dir.iterdir())
    print(f"[lebenslauf-fetch] {count} Datei(en) nach {local_dir}", flush=True)


def print_sftp_missing_error() -> None:
    print(
        f"FEHLER: Lebenslauf-Daten fehlen auf SFTP ({SFTP_DATA_DIR}).\n"
        "Lokal ausführen: cli python <pipeline> --phase deploy \\\n"
        "  scripts/local/lebenslauf-sftp-push.py <pfad>",
        file=sys.stderr,
    )


if __name__ == "__main__":
    main()
