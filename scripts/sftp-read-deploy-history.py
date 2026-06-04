import json
import sys

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

HISTORY_FILE = "log/deploy-history.ndjson"
SWITCH_EVENT = "switch_executed"


def main():
    cfg = PipelineCfg("deploy")
    with SftpClient(cfg) as client:
        content = client.read_file(HISTORY_FILE)
    if not content:
        print("[history] Keine Deploy-History gefunden", file=sys.stderr)
        sys.exit(1)
    entry = last_switch_entry(content)
    if entry is None:
        print("[history] Kein switch_executed-Eintrag gefunden", file=sys.stderr)
        sys.exit(1)
    print(json.dumps(entry))


def last_switch_entry(content: str) -> dict | None:
    for line in reversed(content.strip().splitlines()):
        entry = json.loads(line)
        if entry.get("event") == SWITCH_EVENT:
            return entry
    return None


if __name__ == "__main__":
    main()
