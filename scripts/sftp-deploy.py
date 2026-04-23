#!/usr/bin/env python3
import json
import sys
import time
from pathlib import Path
import paramiko


def read_config():
    return json.loads(sys.argv[1])


def setup_known_hosts(known_host_line):
    known_hosts = Path("/tmp/sftp-known-hosts")
    known_hosts.write_text(known_host_line + "\n")
    known_hosts.chmod(0o600)
    return known_hosts


def open_sftp(cfg, known_hosts):
    ssh = paramiko.SSHClient()
    ssh.load_system_host_keys(str(known_hosts))
    ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
    ssh.connect(
        cfg["FTP_HOST"],
        port=int(cfg["FTP_PORT"]),
        username=cfg["FTP_USER"],
        password=cfg["FTP_PASS"],
    )
    return ssh, ssh.open_sftp()


def new_stats():
    return {
        "directories": 0,
        "files": 0,
        "bytes": 0,
    }


def log_status(message):
    print(f"[sftp] {message}", flush=True)


def format_target(cfg):
    return f"{cfg['FTP_HOST']}:{cfg['FTP_PORT']}{cfg['FTP_SERVER_DIR']}"


def file_size(path):
    return path.stat().st_size


def mkdir_p(sftp, remote_path):
    if remote_path == "/":
        return False
    try:
        sftp.mkdir(remote_path)
        return True
    except OSError:
        return False


def upload_dir(sftp, local_path, remote_path, stats):
    created = mkdir_p(sftp, remote_path)
    if created:
        stats["directories"] += 1
    for item in sorted(Path(local_path).iterdir()):
        remote_item = remote_path.rstrip("/") + "/" + item.name
        if item.is_dir():
            upload_dir(sftp, item, remote_item, stats)
            continue
        sftp.put(str(item), remote_item)
        stats["files"] += 1
        stats["bytes"] += file_size(item)


def upload(cfg, sftp):
    stats = new_stats()
    local_path = Path("var/deploy")
    started_at = time.monotonic()
    log_status(f"Upload startet: {local_path} -> {cfg['FTP_SERVER_DIR']}")
    upload_dir(sftp, local_path, cfg["FTP_SERVER_DIR"], stats)
    duration = time.monotonic() - started_at
    log_status(
        "Upload abgeschlossen: "
        f"{stats['files']} Dateien, "
        f"{stats['directories']} Verzeichnisse, "
        f"{stats['bytes']} Bytes, "
        f"{duration:.2f}s"
    )


def close_connection(ssh, sftp):
    log_status("Verbindung wird geschlossen")
    sftp.close()
    transport = ssh.get_transport()
    if transport is not None and transport.is_active():
        transport.close()
    ssh.close()


def main():
    cfg = read_config()
    known_hosts = setup_known_hosts(cfg["SSH_KNOWN_HOST_LINE"])
    log_status(f"Verbinde zu {format_target(cfg)}")
    ssh, sftp = open_sftp(cfg, known_hosts)
    try:
        log_status("Verbindung hergestellt")
        upload(cfg, sftp)
    except Exception as exc:
        log_status(f"Fehler im SFTP-Upload: {exc}")
        raise
    finally:
        close_connection(ssh, sftp)


if __name__ == "__main__":
    main()
