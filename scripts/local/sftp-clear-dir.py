import argparse
import stat

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg


def main() -> None:
    args = parse_args()
    with SftpClient(PipelineCfg("deploy")) as client:
        clear_contents(client, args.remote_path)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Entfernt alle Einträge in einem Remote-Verzeichnis (wie rm -rf <ordner>/*)"
    )
    parser.add_argument("remote_path", help="Relativer Pfad zum Remote-Verzeichnis")
    return parser.parse_args()


def clear_contents(client: SftpClient, rel_path: str) -> None:
    entries = client.listdir_attr(rel_path)
    for entry in entries:
        child = rel_path.rstrip("/") + "/" + entry.filename
        remove_entry(client, child, entry.st_mode)
    print(f"[sftp-clear] fertig: {rel_path}", flush=True)


def remove_entry(client: SftpClient, child: str, mode: int) -> None:
    if stat.S_ISDIR(mode):
        client.remove_dir(child)
    else:
        client.remove_file(child)
    print(f"[sftp-clear] entfernt: {child}", flush=True)


if __name__ == "__main__":
    main()
