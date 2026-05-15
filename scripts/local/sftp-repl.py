from cli.py.deploy.sftp_lib import SftpClient
from cli.py.deploy.sftp_shell import SftpShell
from cli.py.pipeline_cfg import PipelineCfg


def main() -> None:
    with SftpClient(PipelineCfg("deploy")) as client:
        SftpShell(client).cmdloop()


if __name__ == "__main__":
    main()
