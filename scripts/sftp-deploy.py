#!/usr/bin/env python3
import os
import time
from pathlib import Path

from sftp_lib import SftpClient, read_config


def log_status(message):
    print(f"[sftp] {message}", flush=True)


def format_target(cfg):
    return f"{cfg['FTP_HOST']}:{cfg['FTP_PORT']}{cfg['FTP_SERVER_DIR']}"


def main():
    cfg = read_config()
    include_vendor = os.environ.get("SFTP_INCLUDE_VENDOR", "true") == "true"
    log_status(f"Verbinde zu {format_target(cfg)}")
    with SftpClient(cfg) as client:
        log_status("Verbindung hergestellt")
        try:
            upload(cfg, client.sftp, include_vendor)
        except Exception as exc:
            log_status(f"Fehler im SFTP-Upload: {exc}")
            raise


def upload(cfg, sftp, include_vendor):
    stats = new_stats()
    server_dir = cfg["FTP_SERVER_DIR"].rstrip("/")
    started_at = time.monotonic()
    log_status(f"Upload startet: {cfg['FTP_SERVER_DIR']}")
    for item in sorted(Path("var/deploy").iterdir()):
        if item.name == "vendor" and not include_vendor:
            continue
        remote_item = server_dir + "/" + item.name
        if item.is_dir():
            upload_dir(sftp, item, remote_item, stats)
        else:
            upload_file(sftp, item, remote_item, stats)
    duration = time.monotonic() - started_at
    log_status(
        "Upload abgeschlossen: "
        f"{stats['files']} Dateien, "
        f"{stats['directories']} Verzeichnisse, "
        f"{stats['bytes']} Bytes, "
        f"{duration:.2f}s"
    )


def mkdir_p(sftp, remote_path):
    if remote_path == "/":
        return False
    try:
        sftp.mkdir(remote_path)
        return True
    except OSError:
        return False


def ensure_remote_dir(sftp, remote_path):
    parts = [p for p in remote_path.split("/") if p]
    path = ""
    for part in parts:
        path = path + "/" + part
        try:
            sftp.mkdir(path)
        except OSError:
            pass


def file_size(path):
    return path.stat().st_size


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


def upload_file(sftp, local_path, remote_path, stats):
    ensure_remote_dir(sftp, str(Path(remote_path).parent))
    sftp.put(str(local_path), remote_path)
    stats["files"] += 1
    stats["bytes"] += file_size(local_path)


def new_stats():
    return {"directories": 0, "files": 0, "bytes": 0}


main()
