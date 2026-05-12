import sys

from cli.py.deploy.sftp_deploy_state import DeployState
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

FALLBACK_VENDOR = "a"


def main():
    with SftpClient(PipelineCfg("deploy")) as client:
        vendor_slot = read_vendor_slot(client)
        build_id = client.read_file(f"vendor-{vendor_slot}/.ci-build-id")
    sys.stdout.write(build_id)


def read_vendor_slot(client):
    deploy_state = DeployState.read(client)
    if deploy_state is None:
        return FALLBACK_VENDOR
    return deploy_state.vendor


if __name__ == "__main__":
    main()
