import sys
from pathlib import Path

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg
from cli.py.util.envvar import env

SFTP_DATA_DIR = "etc/content"


def main() -> None:
    source = resolve_source()
    deploy_cfg = PipelineCfg("deploy")
    with SftpClient(deploy_cfg) as client:
        upload_content_tree(client, source)


def resolve_source() -> Path:
    if len(sys.argv) > 1:
        return Path(sys.argv[1])
    source_repo = env("SOURCE_REPO_DIR").require_nonempty().value()
    build_cfg = PipelineCfg("build")
    return Path(source_repo) / build_cfg["CONTENT_PATH"]


def upload_content_tree(client: SftpClient, source: Path) -> None:
    if not source.is_dir():
        print(f"FEHLER: Content-Pfad nicht gefunden: {source}", file=sys.stderr)
        sys.exit(1)
    total = 0
    for subdir in sorted(source.iterdir()):
        if not subdir.is_dir():
            continue
        remote_subdir = f"{SFTP_DATA_DIR}/{subdir.name}"
        client.ensure_dir(remote_subdir)
        for f in sorted(subdir.iterdir()):
            if f.is_file():
                client.put_file(f, f"{remote_subdir}/{f.name}")
                total += 1
    print(f"[content-push] {total} Datei(en) nach {SFTP_DATA_DIR}", flush=True)


if __name__ == "__main__":
    main()
