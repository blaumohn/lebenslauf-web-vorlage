from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

LOG_PATH = "log/error.log"


def main() -> None:
    with SftpClient(PipelineCfg("deploy")) as client:
        content = client.read_file(LOG_PATH)
        if not content:
            print(f"[show-error-log] Keine Einträge: {LOG_PATH}", flush=True)
            return
        print(content, end="", flush=True)


if __name__ == "__main__":
    main()
