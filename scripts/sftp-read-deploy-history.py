import json
import sys

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

HISTORY_FILE = "var/deploy-history.ndjson"


def main():
    cfg = PipelineCfg("deploy")
    with SftpClient(cfg) as client:
        content = client.read_file(HISTORY_FILE)
    if not content:
        print("[history] Keine Deploy-History gefunden", file=sys.stderr)
        sys.exit(1)
    last_line = content.strip().splitlines()[-1]
    entry = json.loads(last_line)
    print(json.dumps(entry))


if __name__ == "__main__":
    main()
