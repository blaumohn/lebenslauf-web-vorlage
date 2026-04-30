import json
import os
from pathlib import Path
import paramiko


def read_config():
    return json.loads(os.environ["SFTP_CFG_JSON"])


class SftpClient:
    def __init__(self, cfg):
        self.cfg = cfg
        known_hosts = Path("/tmp/sftp-known-hosts")
        known_hosts.write_text(cfg["SSH_KNOWN_HOST_LINE"] + "\n")
        known_hosts.chmod(0o600)
        self._ssh = paramiko.SSHClient()
        self._ssh.load_system_host_keys(str(known_hosts))
        self._ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
        self._ssh.connect(
            cfg["FTP_HOST"],
            port=int(cfg["FTP_PORT"]),
            username=cfg["FTP_USER"],
            password=cfg["FTP_PASS"],
        )
        self.sftp = self._ssh.open_sftp()

    def __enter__(self):
        return self

    def read_file(self, path):
        remote_path = self.cfg["FTP_SERVER_DIR"].rstrip("/") + "/" + path
        try:
            with self.sftp.open(remote_path) as f:
                return f.read().decode()
        except IOError:
            return ""

    def __exit__(self, *_):
        self.close()

    def close(self):
        self.sftp.close()
        transport = self._ssh.get_transport()
        if transport is not None and transport.is_active():
            transport.close()
        self._ssh.close()
