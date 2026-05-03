#!/usr/bin/env python3
import sys

from sftp_lib import (
    SftpClient,
    read_config,
    parse_deploy_state,
    parse_router_state,
    STATE_FILE,
)

FALLBACK_VENDOR = "a"


def main():
    config = read_config()
    with SftpClient(config) as client:
        vendor_slot = read_vendor_slot(client)
        build_id = client.read_file(f"vendor-{vendor_slot}/.ci-build-id")
    sys.stdout.write(build_id)


def read_vendor_slot(client):
    deploy_state = parse_deploy_state(client.read_file(STATE_FILE))
    router_state = parse_router_state(client.read_file("index.php"))
    if deploy_state is not None and (router_state is None or deploy_state == router_state):
        return deploy_state[1]
    state = router_state
    if state is None:
        return FALLBACK_VENDOR
    return state[1]


if __name__ == "__main__":
    main()
