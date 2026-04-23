#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko


def read_env():
    return {
        "host": os.environ["SFTP_HOST"],
        "user": os.environ["SFTP_USER"],
        "password": os.environ["SSHPASS"],
        "port": int(os.environ.get("SFTP_PORT", "22")),
        "remote_dir": os.environ["SFTP_REMOTE_DIR"],
        "known_host_line": os.environ["SSH_KNOWN_HOST_LINE"],
    }


def setup_known_hosts(known_host_line):
    ssh_dir = Path.home() / ".ssh"
    ssh_dir.mkdir(mode=0o700, exist_ok=True)
    known_hosts = ssh_dir / "known_hosts"
    known_hosts.write_text(known_host_line + "\n")
    known_hosts.chmod(0o600)
    return known_hosts


def open_sftp(cfg, known_hosts):
    ssh = paramiko.SSHClient()
    ssh.load_system_host_keys(str(known_hosts))
    ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
    ssh.connect(cfg["host"], port=cfg["port"], username=cfg["user"], password=cfg["password"])
    return ssh, ssh.open_sftp()


def mkdir_p(sftp, remote_path):
    if remote_path == "/":
        return
    try:
        sftp.mkdir(remote_path)
    except OSError:
        pass


def upload_dir(sftp, local_path, remote_path):
    mkdir_p(sftp, remote_path)
    for item in sorted(Path(local_path).iterdir()):
        remote_item = remote_path.rstrip("/") + "/" + item.name
        if item.is_dir():
            upload_dir(sftp, item, remote_item)
        else:
            sftp.put(str(item), remote_item)


def main():
    cfg = read_env()
    known_hosts = setup_known_hosts(cfg["known_host_line"])
    ssh, sftp = open_sftp(cfg, known_hosts)
    try:
        upload_dir(sftp, "var/deploy", cfg["remote_dir"])
    finally:
        sftp.close()
        ssh.close()


if __name__ == "__main__":
    main()
