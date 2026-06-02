from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

SFTP_DATA_DIR = "etc/lebenslauf"


def main() -> None:
    deploy_cfg = PipelineCfg("deploy")
    with SftpClient(deploy_cfg) as client:
        if client.dir_exists(SFTP_DATA_DIR):
            client.remove_dir(SFTP_DATA_DIR)
            print(f"[lebenslauf-loeschen] {SFTP_DATA_DIR} bereinigt", flush=True)


if __name__ == "__main__":
    main()
