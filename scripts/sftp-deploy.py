#!/usr/bin/env python3
import os
import stat
import time
from pathlib import Path

from sftp_lib import (
    SftpClient,
    read_config,
)
from sftp_deploy_state import DeployStateFile, DeploymentPlan, RouterState
from sftp_deploy_templates import (
    render_entry_htaccess,
    render_fallback_entry_htaccess,
    render_router,
)


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
    deploy_state = DeployStateFile.read(client)
    router_state = RouterState.read(client)
    if deploy_state is not None and deploy_state == router_state:
        return deploy_state
    if router_state is not None:
        return router_state
    return deploy_state


def deploy_fresh(client, include_vendor):
    plan = DeploymentPlan.fresh()
    target = plan.target
    log(f"Erstdeploy: Baum {target.tree}, Vendor {target.vendor}")
    upload_app_tree(client, target.tree)
    if include_vendor:
        upload_vendor_dir(client, target.vendor)
    upload_fallback_entry_htaccess(client)
    upload_router(client, target)
    upload_deploy_state(client, target)
    upload_entry_htaccess(client, target)
    log("Erstdeploy abgeschlossen")


def deploy_swap(client, state, include_vendor):
    plan = DeploymentPlan.swap(state, include_vendor)
    active = plan.active
    target = plan.target
    log(f"Baum: {active.tree}→{target.tree}, Vendor: {active.vendor}→{target.vendor}")
    upload_app_tree(client, target.tree)
    if include_vendor:
        upload_vendor_dir(client, target.vendor)
    migrate_tokens(client, active.tree, target.tree)
    upload_fallback_entry_htaccess(client)
    upload_router(client, target)
    upload_deploy_state(client, target)
    upload_entry_htaccess(client, target)
    cleanup(client, active, target)
    log(f"Deploy abgeschlossen: Baum {target.tree}, Vendor {target.vendor}")


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


def upload_entry_htaccess(client, state):
    content = render_entry_htaccess(state).encode("utf-8")
    client.put_bytes(".htaccess", content)
    log(f"Entry .htaccess hochgeladen: Baum {state.tree}")


def upload_fallback_entry_htaccess(client):
    content = render_fallback_entry_htaccess().encode("utf-8")
    client.put_bytes(".htaccess", content)
    log("Entry .htaccess ohne statische Slot-Regeln hochgeladen")


def upload_router(client, state):
    content = render_router(state).encode("utf-8")
    client.put_bytes("index.php", content)
    log(f"Root-Router hochgeladen: Baum {state.tree}, Vendor {state.vendor}")


def upload_deploy_state(client, state):
    DeployStateFile.write(client, state)
    log(f"Deploy-State hochgeladen: Baum {state.tree}, Vendor {state.vendor}")


def cleanup(client, active, target):
    client.remove_dir(active.tree)
    log(f"Alter App-Baum entfernt: {active.tree}")
    if active.vendor != target.vendor:
        client.remove_dir(f"vendor-{active.vendor}")
        log(f"Alter Vendor entfernt: vendor-{active.vendor}")


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


if __name__ == "__main__":
    main()
