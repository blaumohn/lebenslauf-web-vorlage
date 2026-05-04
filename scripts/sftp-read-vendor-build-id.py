#!/usr/bin/env python3
import sys

from sftp_lib import (
    SftpClient,
    read_config,
)
from sftp_deploy_state import DeployStateFile, RouterState

FALLBACK_VENDOR = "a"


def main():
    config = read_config()
    with SftpClient(config) as client:
        vendor_slot = read_vendor_slot(client)
        build_id = client.read_file(f"vendor-{vendor_slot}/.ci-build-id")
    sys.stdout.write(build_id)


def read_vendor_slot(client):
    deploy_state = DeployStateFile.read(client)
    router_state = RouterState.read(client)
    if deploy_state is not None and (router_state is None or deploy_state == router_state):
        return deploy_state.vendor
    state = router_state
    if state is None:
        return FALLBACK_VENDOR
    return state.vendor


if __name__ == "__main__":
    main()
