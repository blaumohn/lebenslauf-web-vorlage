#!/usr/bin/env python3
import json
import os
import time
from pathlib import Path
import paramiko


def read_config():
    return json.loads(os.environ["SFTP_CFG_JSON"])


def read_diff_config():
    raw = os.environ.get("SFTP_DIFF_JSON", "")
    if not raw:
        return None
    return json.loads(raw)


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
    return {"directories": 0, "files": 0, "bytes": 0}


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


def ensure_remote_dir(sftp, remote_path):
    parts = [p for p in remote_path.split("/") if p]
    path = ""
    for part in parts:
        path = path + "/" + part
        try:
            sftp.mkdir(path)
        except OSError:
            pass


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


def upload_diff(cfg, sftp, diff_cfg):
    stats = new_stats()
    server_dir = cfg["FTP_SERVER_DIR"].rstrip("/")
    started_at = time.monotonic()
    changed_files = diff_cfg.get("diff_files", [])
    vendor_full = diff_cfg.get("vendor_full", False)

    log_status(f"Diff-Upload startet: {len(changed_files)} geänderte Dateien")

    if vendor_full:
        log_status("composer.lock geändert: vendor/ wird vollständig hochgeladen")
        upload_dir(sftp, Path("var/deploy/vendor"), server_dir + "/vendor", stats)

    for git_path in changed_files:
        if git_path.startswith("vendor/"):
            continue
        deploy_path = Path("var/deploy") / git_path
        if not deploy_path.exists():
            continue
        remote_path = server_dir + "/" + git_path
        if deploy_path.is_dir():
            upload_dir(sftp, deploy_path, remote_path, stats)
        else:
            upload_file(sftp, deploy_path, remote_path, stats)

    duration = time.monotonic() - started_at
    log_status(
        "Diff-Upload abgeschlossen: "
        f"{stats['files']} Dateien, "
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
    diff_cfg = read_diff_config()
    known_hosts = setup_known_hosts(cfg["SSH_KNOWN_HOST_LINE"])
    log_status(f"Verbinde zu {format_target(cfg)}")
    ssh, sftp = open_sftp(cfg, known_hosts)
    try:
        log_status("Verbindung hergestellt")
        if diff_cfg is not None:
            upload_diff(cfg, sftp, diff_cfg)
        else:
            upload(cfg, sftp)
    except Exception as exc:
        log_status(f"Fehler im SFTP-Upload: {exc}")
        raise
    finally:
        close_connection(ssh, sftp)


if __name__ == "__main__":
    main()
