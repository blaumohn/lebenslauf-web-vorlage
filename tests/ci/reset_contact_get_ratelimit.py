import sys

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

RATELIMIT_DIRS = (
    "app-a/var/tmp/ratelimit",
    "app-b/var/tmp/ratelimit",
)


def main() -> int:
    cfg = PipelineCfg("deploy")
    with SftpClient(cfg) as sftp:
        removed = reset_contact_get_ratelimit(sftp)
    print(f"[ratelimit-reset] contact_get entries removed: {removed}")
    return 0


def reset_contact_get_ratelimit(sftp) -> int:
    removed = 0
    for directory in RATELIMIT_DIRS:
        for entry in list_entries(sftp, directory):
            if not is_contact_get_ratelimit_file(entry.filename):
                continue
            sftp.remove_file(f"{directory}/{entry.filename}")
            removed += 1
    return removed


def list_entries(sftp, directory: str):
    try:
        return sftp.listdir_attr(directory)
    except OSError:
        return []


def is_contact_get_ratelimit_file(filename: str) -> bool:
    return (
        filename.startswith("contact_get_")
        and filename.endswith(".json")
    )


if __name__ == "__main__":
    sys.exit(main())
