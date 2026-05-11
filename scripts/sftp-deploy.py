import os
import stat
import time
from pathlib import Path

from cli.py.admin.dispatch import enqueue_with_client
from cli.py.admin.task import AdminTask
from cli.py.deploy.sftp_deploy_prepared import PreparedDeployStore
from cli.py.deploy.sftp_deploy_state import DeploymentPlan, DeployState
from cli.py.deploy.sftp_deploy_templates import resource_path
from cli.py.deploy.sftp_lib import (
    SftpClient,
    read_config,
)


def log(message):
    print(f"[sftp] {message}", flush=True)


def format_target(cfg):
    return f"{cfg['SFTP_HOST']}:{cfg['SFTP_PORT']}{cfg['SFTP_SERVER_DIR']}"


def main():
    cfg = read_config()
    include_vendor = os.environ.get("SFTP_INCLUDE_VENDOR", "true") == "true"
    log(f"Verbinde zu {format_target(cfg)}")
    SftpDeploy(cfg, include_vendor).start()


class SftpDeploy:
    def __init__(self, cfg, include_vendor, logger=log):
        self.cfg = cfg
        self.include_vendor = include_vendor
        self.log = logger
        self.client = None

    def start(self):
        with SftpClient(self.cfg) as client:
            self.client = client
            self.log("Verbindung hergestellt")
            self.deploy()
        self.client = None

    def deploy(self):
        state = DeployState.read(self.client)
        if state is None:
            self.deploy_fresh()
        else:
            self.deploy_swap(state)

    def deploy_fresh(self):
        plan = DeploymentPlan.fresh()
        target = plan.target
        self.log(f"Erstdeploy: Baum {target.tree}, Vendor {target.vendor}")
        self.upload_app_tree(target.tree)
        if self.include_vendor:
            self.upload_vendor_dir(target.vendor)
        self.upload_static_entry_files()
        self.publish_switch(target)
        self.log("Erstdeploy abgeschlossen")

    def deploy_swap(self, state):
        plan = DeploymentPlan.swap(state, self.include_vendor)
        active = plan.active
        target = plan.target
        self.log(f"Baum: {active.tree}→{target.tree}, Vendor: {active.vendor}→{target.vendor}")
        self.upload_app_tree(target.tree)
        if self.include_vendor:
            self.upload_vendor_dir(target.vendor)
        self.migrate_tokens(active.tree, target.tree)
        self.prepare_switch(target)
        self.log(f"Deploy vorbereitet: Baum {target.tree}, Vendor {target.vendor}")

    def prepare_switch(self, target):
        prepared_path = PreparedDeployStore.write(self.client, target)
        task = AdminTask("deploy_switch", {"prepared_state": prepared_path})
        enqueue_with_client(self.client, task)
        self.log(f"Switch vorbereitet: {prepared_path}")

    def publish_switch(self, target):
        self.upload_deploy_state(target)

    def upload_static_entry_files(self):
        self.client.put_file(resource_path(".htaccess"), ".htaccess")
        self.client.put_file(resource_path("index.php"), "index.php")
        self.log("Statische Entry-Dateien hochgeladen")

    def upload_app_tree(self, tree):
        stats = new_stats()
        started_at = time.monotonic()
        self.log(f"Upload App-Baum: {tree}")
        self.client.ensure_dir(tree)
        for item in sorted(Path("var/deploy").iterdir()):
            self.upload_app_item(item, tree, stats)
        self.log_upload_app_tree(tree, stats, started_at)

    def upload_app_item(self, item, tree, stats):
        if item.name == "vendor":
            return
        rel_remote = tree + "/" + item.name
        if item.is_dir():
            self.upload_dir(item, rel_remote, stats)
            return
        self.upload_file(item, rel_remote, stats)

    def log_upload_app_tree(self, _tree, stats, started_at):
        duration = time.monotonic() - started_at
        self.log(
            f"App-Baum hochgeladen: {stats['files']} Dateien, "
            f"{stats['directories']} Verzeichnisse, "
            f"{stats['bytes']} Bytes, {duration:.2f}s"
        )

    def upload_vendor_dir(self, vendor_slot):
        stats = new_stats()
        rel_dir = "vendor-" + vendor_slot
        started_at = time.monotonic()
        self.log(f"Upload Vendor: {rel_dir}")
        self.client.ensure_dir(rel_dir)
        for item in sorted(Path("var/deploy/vendor").iterdir()):
            rel_remote = rel_dir + "/" + item.name
            if item.is_dir():
                self.upload_dir(item, rel_remote, stats)
            else:
                self.upload_file(item, rel_remote, stats)
        self.log_upload_vendor(stats, started_at)

    def log_upload_vendor(self, stats, started_at):
        duration = time.monotonic() - started_at
        self.log(
            f"Vendor hochgeladen: {stats['files']} Dateien, "
            f"{stats['bytes']} Bytes, {duration:.2f}s"
        )

    def migrate_tokens(self, active_tree, inactive_tree):
        src = f"{active_tree}/var/state/tokens"
        dst = f"{inactive_tree}/var/state/tokens"
        try:
            entries = self.client.listdir_attr(src)
        except OSError:
            return
        self.copy_token_entries(entries, src, dst)

    def copy_token_entries(self, entries, src, dst):
        self.client.ensure_dir(dst)
        count = 0
        for entry in entries:
            count += self.copy_token_entry(entry, src, dst)
        if count:
            self.log(f"Tokens migriert: {count}")

    def copy_token_entry(self, entry, src, dst):
        if not stat.S_ISREG(entry.st_mode):
            return 0
        with self.client.open(f"{src}/{entry.filename}", "rb") as f:
            data = f.read()
        self.client.put_bytes(f"{dst}/{entry.filename}", data)
        return 1

    def upload_deploy_state(self, state):
        DeployState.write(self.client, state)
        self.log(f"Deploy-State hochgeladen: Baum {state.tree}, Vendor {state.vendor}")

    def cleanup(self, active, target):
        self.client.remove_dir(active.tree)
        self.log(f"Alter App-Baum entfernt: {active.tree}")
        if active.vendor != target.vendor:
            self.client.remove_dir(f"vendor-{active.vendor}")
            self.log(f"Alter Vendor entfernt: vendor-{active.vendor}")

    def upload_dir(self, local_path, rel_remote, stats):
        created = self.client.mkdir_p(rel_remote)
        if created:
            stats["directories"] += 1
        for item in sorted(Path(local_path).iterdir()):
            rel_child = rel_remote + "/" + item.name
            if item.is_dir():
                self.upload_dir(item, rel_child, stats)
            else:
                self.client.put_file(item, rel_child)
                stats["files"] += 1
                stats["bytes"] += item.stat().st_size

    def upload_file(self, local_path, rel_remote, stats):
        parent = str(Path(rel_remote).parent).replace("\\", "/")
        self.client.ensure_dir(parent)
        self.client.put_file(local_path, rel_remote)
        stats["files"] += 1
        stats["bytes"] += Path(local_path).stat().st_size


def new_stats():
    return {"directories": 0, "files": 0, "bytes": 0}


if __name__ == "__main__":
    main()
