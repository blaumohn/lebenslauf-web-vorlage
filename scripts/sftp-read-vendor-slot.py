import sys

from cli.py.deploy.sftp_deploy_state import DeployState
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg


def main():
    cfg = PipelineCfg("deploy")
    with SftpClient(cfg) as client:
        state = DeployState.read(client)
    sys.stdout.write(state.vendor if state else "")


if __name__ == "__main__":
    main()
