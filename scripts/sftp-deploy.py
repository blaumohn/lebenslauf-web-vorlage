#!/usr/bin/env python3
import os
import stat
import time
from pathlib import Path

from sftp_lib import SftpClient, read_config, parse_router_state, STATE_MARKER, VALID_SLOTS

INITIAL_SLOT = "a"


def log(message):
    print(f"[sftp] {message}", flush=True)


def format_target(cfg):
    return f"{cfg['FTP_HOST']}:{cfg['FTP_PORT']}{cfg['FTP_SERVER_DIR']}"


def main():
    cfg = read_config()
    include_vendor = os.environ.get("SFTP_INCLUDE_VENDOR", "true") == "true"
    log(f"Verbinde zu {format_target(cfg)}")
    with SftpClient(cfg) as client:
        log("Verbindung hergestellt")
        deploy(client, include_vendor)


def deploy(client, include_vendor):
    state = read_router_state(client)
    if state is None:
        deploy_fresh(client, include_vendor)
    else:
        deploy_swap(client, state, include_vendor)


def read_router_state(client):
    return parse_router_state(client.read_file("index.php"))


def deploy_fresh(client, include_vendor):
    tree = INITIAL_SLOT
    vendor = INITIAL_SLOT
    log(f"Erstdeploy: Baum {tree}, Vendor {vendor}")
    upload_app_tree(client, tree)
    if include_vendor:
        upload_vendor_dir(client, vendor)
    upload_htaccess(client)
    upload_router(client, tree, vendor)
    log("Erstdeploy abgeschlossen")


def deploy_swap(client, state, include_vendor):
    active_tree, active_vendor = state
    inactive_tree = other_slot(active_tree)
    inactive_vendor = other_slot(active_vendor) if include_vendor else active_vendor
    log(f"Baum: {active_tree}→{inactive_tree}, Vendor: {active_vendor}→{inactive_vendor}")
    upload_app_tree(client, inactive_tree)
    if include_vendor:
        upload_vendor_dir(client, inactive_vendor)
    migrate_tokens(client, active_tree, inactive_tree)
    upload_htaccess(client)
    upload_router(client, inactive_tree, inactive_vendor)
    cleanup(client, active_tree, active_vendor, inactive_vendor)
    log(f"Deploy abgeschlossen: Baum {inactive_tree}, Vendor {inactive_vendor}")


def other_slot(slot):
    return "b" if slot == "a" else "a"


def upload_app_tree(client, tree):
    stats = new_stats()
    started_at = time.monotonic()
    log(f"Upload App-Baum: {tree}")
    client.ensure_dir(tree)
    for item in sorted(Path("var/deploy").iterdir()):
        if item.name == "vendor":
            continue
        rel_remote = tree + "/" + item.name
        if item.is_dir():
            upload_dir(client, item, rel_remote, stats)
        else:
            upload_file(client, item, rel_remote, stats)
    duration = time.monotonic() - started_at
    log(
        f"App-Baum hochgeladen: {stats['files']} Dateien, "
        f"{stats['directories']} Verzeichnisse, "
        f"{stats['bytes']} Bytes, {duration:.2f}s"
    )


def upload_vendor_dir(client, vendor_slot):
    stats = new_stats()
    rel_dir = "vendor-" + vendor_slot
    started_at = time.monotonic()
    log(f"Upload Vendor: {rel_dir}")
    client.ensure_dir(rel_dir)
    for item in sorted(Path("var/deploy/vendor").iterdir()):
        rel_remote = rel_dir + "/" + item.name
        if item.is_dir():
            upload_dir(client, item, rel_remote, stats)
        else:
            upload_file(client, item, rel_remote, stats)
    duration = time.monotonic() - started_at
    log(
        f"Vendor hochgeladen: {stats['files']} Dateien, "
        f"{stats['bytes']} Bytes, {duration:.2f}s"
    )


def migrate_tokens(client, active_tree, inactive_tree):
    src = f"{active_tree}/var/state/tokens"
    dst = f"{inactive_tree}/var/state/tokens"
    try:
        entries = client.listdir_attr(src)
    except IOError:
        return
    client.ensure_dir(dst)
    count = 0
    for entry in entries:
        if not stat.S_ISREG(entry.st_mode):
            continue
        with client.open(f"{src}/{entry.filename}", "rb") as f:
            data = f.read()
        client.put_bytes(f"{dst}/{entry.filename}", data)
        count += 1
    if count:
        log(f"Tokens migriert: {count}")


def upload_htaccess(client):
    htaccess = Path("src/resources/http/entry/.htaccess")
    if htaccess.exists():
        client.put_file(htaccess, ".htaccess")
        log("Entry .htaccess hochgeladen")


def upload_router(client, tree, vendor):
    content = generate_router(tree, vendor).encode("utf-8")
    client.put_bytes("index.php", content)
    log(f"Root-Router hochgeladen: Baum {tree}, Vendor {vendor}")


def generate_router(tree, vendor):
    return (
        "<?php\n"
        f"{STATE_MARKER} tree={tree} vendor={vendor}\n"
        f"define('APP_VENDOR_DIR', __DIR__ . '/vendor-{vendor}');\n"
        f"require __DIR__ . '/{tree}/public/index.php';\n"
    )


def cleanup(client, old_tree, old_vendor, new_vendor):
    client.remove_dir(old_tree)
    log(f"Alter App-Baum entfernt: {old_tree}")
    if old_vendor != new_vendor:
        client.remove_dir(f"vendor-{old_vendor}")
        log(f"Alter Vendor entfernt: vendor-{old_vendor}")


def upload_dir(client, local_path, rel_remote, stats):
    created = client.mkdir_p(rel_remote)
    if created:
        stats["directories"] += 1
    for item in sorted(Path(local_path).iterdir()):
        rel_child = rel_remote + "/" + item.name
        if item.is_dir():
            upload_dir(client, item, rel_child, stats)
        else:
            client.put_file(item, rel_child)
            stats["files"] += 1
            stats["bytes"] += item.stat().st_size


def upload_file(client, local_path, rel_remote, stats):
    parent = str(Path(rel_remote).parent).replace("\\", "/")
    client.ensure_dir(parent)
    client.put_file(local_path, rel_remote)
    stats["files"] += 1
    stats["bytes"] += Path(local_path).stat().st_size


def new_stats():
    return {"directories": 0, "files": 0, "bytes": 0}


main()
