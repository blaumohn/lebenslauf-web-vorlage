import sys

from cli.py.deploy.sftp_deploy_state import SlotStore
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg


def main():
    cfg = PipelineCfg("deploy")
    with SftpClient(cfg) as client:
        slot_map = SlotStore(client).current_slot_map()
    sys.stdout.write(slot_map.vendor.label if slot_map else "")


if __name__ == "__main__":
    main()
