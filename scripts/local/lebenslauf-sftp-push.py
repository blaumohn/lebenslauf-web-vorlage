import sys
from pathlib import Path

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg
from cli.py.util.envvar import env

SFTP_DATA_DIR = "etc/lebenslauf"
DATA_GLOB = "daten-*.yaml"


def main() -> None:
    source = resolve_source()
    deploy_cfg = PipelineCfg("deploy")
    with SftpClient(deploy_cfg) as client:
        client.ensure_dir(SFTP_DATA_DIR)
        upload_data_files(client, source)


def resolve_source() -> Path:
    if len(sys.argv) > 1:
        return Path(sys.argv[1])
    source_repo = env("SOURCE_REPO_DIR").require_nonempty().value()
    build_cfg = PipelineCfg("build")
    return Path(source_repo) / build_cfg["LEBENSLAUF_DATEN_PFAD"]


def upload_data_files(client: SftpClient, source: Path) -> None:
    files = list(source.glob(DATA_GLOB))
    if not files:
        print(f"FEHLER: Keine {DATA_GLOB}-Dateien in {source}", file=sys.stderr)
        sys.exit(1)
    for f in files:
        client.put_file(f, f"{SFTP_DATA_DIR}/{f.name}")
    print(f"[lebenslauf-push] {len(files)} Datei(en) hochgeladen nach {SFTP_DATA_DIR}", flush=True)


if __name__ == "__main__":
    main()
