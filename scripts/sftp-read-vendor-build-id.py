#!/usr/bin/env python3
import sys

from sftp_lib import SftpClient, read_config


def main():
    config = read_config()
    with SftpClient(config) as client:
        build_id = client.read_file("vendor/.ci-build-id")
    sys.stdout.write(build_id)


if __name__ == "__main__":
    main()
