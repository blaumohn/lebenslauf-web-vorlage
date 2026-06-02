import sys
from pathlib import Path

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

SFTP_DATA_DIR = "etc/lebenslauf"
DATA_GLOB = "daten-*.yaml"


def main() -> None:
    deploy_cfg = PipelineCfg("deploy")
    with SftpClient(deploy_cfg) as client:
        assert_dir_empty(client)
        build_cfg = PipelineCfg("build")
        local_path = Path(build_cfg["LEBENSLAUF_DATEN_PFAD"])
        if not local_path.is_dir():
            print(
                f"FEHLER: Lebenslauf-Pfad nicht gefunden: {local_path}",
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
        f"FEHLER: {SFTP_DATA_DIR} auf SFTP enthält {len(entries)} Datei(en). "
        "Vorheriger Workflow hat nicht bereinigt.",
        file=sys.stderr,
    )
    sys.exit(1)


def upload(client: SftpClient, source: Path) -> None:
    files = list(source.glob(DATA_GLOB))
    if not files:
        print(f"FEHLER: Keine {DATA_GLOB}-Dateien in {source}", file=sys.stderr)
        sys.exit(1)
    client.ensure_dir(SFTP_DATA_DIR)
    for f in files:
        client.put_file(f, f"{SFTP_DATA_DIR}/{f.name}")
    print(f"[lebenslauf-vorbereiten] {len(files)} Datei(en) nach {SFTP_DATA_DIR}", flush=True)


if __name__ == "__main__":
    main()
