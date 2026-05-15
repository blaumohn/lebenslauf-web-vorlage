import stat
import time
from pathlib import Path

from cli.py.task.dispatch import TaskDispatch
from cli.py.task.task import Task
from cli.py.deploy.sftp_deploy_state import DeploymentPlan, DeployState
from cli.py.deploy.sftp_deploy_templates import resource_path
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg
from cli.py.util.envvar import env


def log(message):
    print(f"[sftp] {message}", flush=True)


def format_target(cfg):
    return f"{cfg['SFTP_HOST']}:{cfg['SFTP_PORT']}{cfg['SFTP_SERVER_DIR']}"


def main():
    cfg = PipelineCfg("deploy")
    run_id = env("PIPELINE_RUN_ID").require_nonempty().value()
    composer_lock_changed = env("COMPOSER_LOCK_CHANGED").require_bool().to_bool()
    log(f"Verbinde zu {format_target(cfg)}")
    SftpDeploy(cfg, run_id, composer_lock_changed).start()


class SftpDeploy:
    def __init__(self, cfg, run_id, composer_lock_changed, logger=log):
        self.cfg = cfg
        self.run_id = run_id
        self.composer_lock_changed = composer_lock_changed
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
        self.log(f"Erstdeploy: Baum {target.app}, Vendor {target.vendor}")
        self.upload_app_tree(target.app)
        self.upload_vendor_dir(target.vendor)
        self.upload_static_entry_files()
        self.publish_switch(target)
        self.log("Erstdeploy abgeschlossen")

    def deploy_swap(self, state):
        vendor_slot_valid = self._vendor_slot_valid(state.vendor)
        plan = DeploymentPlan.swap(state, self.composer_lock_changed, vendor_slot_valid)
        active = plan.active
        target = plan.target
        self.log(f"Baum: {active.app}→{target.app}, Vendor: {active.vendor}→{target.vendor}")
        self.upload_app_tree(target.app)
        if target.vendor != active.vendor:
            self.upload_vendor_dir(target.vendor)
        self.migrate_tokens(active.app, target.app)
        self.dispatch_switch(target)
        self.log(f"Deploy vorbereitet: Baum {target.app}, Vendor {target.vendor}")

    def _vendor_slot_valid(self, vendor_slot):
        return self.client.file_exists(f"vendor-{vendor_slot}/.deploy-run")

    def dispatch_switch(self, target):
        self.write_run_markers(target)
        task = Task("deploy_switch", {
            "app": target.app,
            "vendor": target.vendor,
            "run_id": self.run_id,
        })
        TaskDispatch(self.cfg).submit(task)
        self.log(f"Switch ausgelöst: App {target.app}, Vendor {target.vendor}, Run {self.run_id}")

    def write_run_markers(self, target):
        for slot in (target.app, f"vendor-{target.vendor}"):
            self.client.put_text(f"{slot}/.deploy-run", self.run_id)

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
        self.log(f"Deploy-State hochgeladen: Baum {state.app}, Vendor {state.vendor}")

    def cleanup(self, active, target):
        self.client.remove_dir(active.app)
        self.log(f"Alter App-Baum entfernt: {active.app}")
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
