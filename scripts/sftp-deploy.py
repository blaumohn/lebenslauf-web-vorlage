import configparser
import stat
import time
from pathlib import Path

from cli.py.deploy.machine import DeployConflictError, DeployMachine, DeployPhase
from cli.py.deploy.sftp_deploy_state import (
    DeploymentPlan,
    DeployState,
    SlotState,
)
from cli.py.deploy.sftp_deploy_templates import resource_path
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.deploy.vendor_sentinel import ComposerInputChecksum, VendorSentinel
from cli.py.pipeline_cfg import PipelineCfg
from cli.py.task.dispatch import TaskDispatch
from cli.py.task.task import Task
from cli.py.util.envvar import env


def log(message):
    print(f"[sftp] {message}", flush=True)


def format_target(cfg):
    return (
        f"{cfg['SFTP_HOST']}:{cfg['SFTP_PORT']}"
        f"{cfg['SFTP_SERVER_DIR']}"
    )


def vendor_checksum() -> str:
    return ComposerInputChecksum.from_repo()


def main():
    cfg = PipelineCfg("deploy")
    run_id = env("PIPELINE_RUN_ID").require_nonempty().value()
    log(f"Verbinde zu {format_target(cfg)}")
    SftpDeploy(cfg, run_id).start()


class SftpDeploy:
    STAGING_DIR = Path("var/deploy")

    def __init__(self, cfg, run_id, logger=log):
        self.cfg = cfg
        self.run_id = run_id
        self.log = logger
        self.client = None

    def start(self):
        with SftpClient(self.cfg) as client:
            self.client = client
            self.log("Verbindung hergestellt")
            self.deploy()
        self.client = None

    def deploy(self):
        ops = SftpDeployOps(self)
        machine = DeployMachine()
        machine.run(ops)
        self.deploy_phase = machine.phase
        self._log_deploy_result(machine.phase)
        self._raise_if_failed(machine.phase)

    def _log_deploy_result(self, phase):
        if phase == DeployPhase.MANUAL_INTERVENTION_REQUIRED:
            self.log("Manueller Eingriff erforderlich — keine Änderungen")
        elif phase == DeployPhase.FAILED_SAFE:
            self.log("Deploy fehlgeschlagen — aktiver Deploy unberührt")
        elif phase == DeployPhase.VERIFIED:
            self.log("Deploy abgeschlossen")

    def _raise_if_failed(self, phase):
        if phase == DeployPhase.VERIFIED:
            return
        if phase == DeployPhase.MANUAL_INTERVENTION_REQUIRED:
            raise RuntimeError("Manueller Eingriff erforderlich")
        raise RuntimeError(f"Deploy fehlgeschlagen: {phase.name}")

    def deploy_fresh(self):
        plan = DeploymentPlan.fresh()
        target = plan.target
        self.log(
            f"Erstdeploy: Slot {target.app_dir}, "
            f"Vendor {target.vendor_dir}"
        )
        self.upload_app_tree(target.app_dir)
        self.upload_vendor_dir(target.vendor_dir)
        self.upload_static_entry_files()
        self.publish_switch(target)
        self.log("Erstdeploy abgeschlossen")

    def deploy_swap(self, state):
        active = state
        include_vendor = self._include_vendor(active)
        plan = DeploymentPlan.swap(active, include_vendor)
        target = plan.target
        self.log(
            f"Slot: {active.app_dir}→{target.app_dir}, "
            f"Vendor: {active.vendor_dir}→{target.vendor_dir}"
        )
        self._log_vendor_decision(plan)
        self.upload_app_tree(target.app_dir)
        if include_vendor:
            self.upload_vendor_dir(target.vendor_dir)
        self.migrate_tokens(active.app_dir, target.app_dir)
        self.dispatch_switch(target, active)
        self.log(
            f"Deploy vorbereitet: Slot {target.app_dir}, "
            f"Vendor {target.vendor_dir}"
        )

    def _log_vendor_decision(self, plan):
        if plan.target.vendor != plan.active.vendor:
            self.log(f"Vendor neu hochladen: {plan.target.vendor_dir}")
        else:
            self.log(f"Vendor unverändert: {plan.target.vendor_dir}")

    def _include_vendor(self, active) -> bool:
        stored = self._read_vendor_checksum(active)
        computed = vendor_checksum()
        if stored is None:
            self.log("Vendor-Sentinel fehlt oder ist ungültig")
            return True
        if stored != computed:
            self.log(
                f"Composer-Checksum abweichend: "
                f"gespeichert={stored!r}, berechnet={computed!r}"
            )
            self._log_vendor_inputs()
            return True
        return False

    def _log_vendor_inputs(self) -> None:
        for name in ComposerInputChecksum.FILENAMES:
            self.log(f"  Eingang: {name}")

    def _read_vendor_checksum(self, active) -> str | None:
        try:
            path = f"{active.vendor_dir}/.meta"
            content = self.client.read_file(path)
            sentinel = VendorSentinel.from_text(content)
            return sentinel.vendor_checksum
        except (OSError, KeyError, configparser.Error):
            return None

    def dispatch_switch(self, target, active):
        reason = self._system_invalid_reason(active)
        if reason is None:
            self._dispatch_via_task(target)
        else:
            self.log(reason)
            self.upload_deploy_state(target)

    def _system_invalid_reason(self, active):
        stored = self._read_vendor_checksum(active)
        if stored is None or stored != vendor_checksum():
            return (
                "Warnung: Vendor-Sentinel stimmt nicht überein — "
                "vorheriger Deploy möglicherweise unvollständig, "
                "Switch direkt via SFTP"
            )
        if not self.client.file_exists(f"{active.app_dir}/.deploy-run"):
            return (
                "Warnung: App-Sentinel fehlt — "
                "Switch direkt via SFTP"
            )
        if not TaskDispatch(self.cfg, logger=self.log).http_reachable():
            return "App nicht erreichbar — Switch direkt via SFTP"
        return None

    def _dispatch_via_task(self, target):
        task = Task("deploy_switch", {
            "app": target.app,
            "vendor": target.vendor,
            "run_id": self.run_id,
        })
        TaskDispatch(self.cfg, logger=self.log).submit(task)
        self.log(
            f"Switch ausgelöst: Slot {target.app_dir}, "
            f"Vendor {target.vendor_dir}, Run {self.run_id}"
        )

    def upload_static_entry_files(self):
        webroot = self.cfg["SFTP_WEBROOT"]
        self.client.ensure_dir(webroot)
        self.client.put_file(
            resource_path("webroot/.htaccess"),
            f"{webroot}/.htaccess",
        )
        self.client.put_file(
            resource_path("webroot/deploy-state.php"),
            f"{webroot}/deploy-state.php",
        )
        self.client.put_file(
            resource_path("webroot/index.php"),
            f"{webroot}/index.php",
        )
        self.log("Statische Entry-Dateien hochgeladen")

    def upload_app_tree(self, app_dir):
        stats = new_stats()
        started_at = time.monotonic()
        self.log(f"Upload App-Slot: {app_dir}")
        self._prepare_slot(app_dir)
        for item in sorted(self.STAGING_DIR.iterdir()):
            self.upload_app_item(item, app_dir, stats)
        self.client.put_text(f"{app_dir}/.deploy-run", self.run_id)
        self.log_upload_app_tree(app_dir, stats, started_at)

    def upload_app_item(self, item, app_dir, stats):
        if item.name == "vendor":
            return
        rel_remote = app_dir + "/" + item.name
        if item.is_dir():
            self.upload_dir(item, rel_remote, stats)
            return
        self.upload_file(item, rel_remote, stats)

    def log_upload_app_tree(self, app_dir, stats, started_at):
        duration = time.monotonic() - started_at
        self.log(
            f"App-Slot hochgeladen ({app_dir}): "
            f"{stats['files']} Dateien, "
            f"{stats['directories']} Verzeichnisse, "
            f"{stats['bytes']} Bytes, {duration:.2f}s"
        )

    def upload_vendor_dir(self, vendor_dir):
        stats = new_stats()
        started_at = time.monotonic()
        self.log(f"Upload Vendor: {vendor_dir}")
        self._prepare_slot(vendor_dir)
        for item in sorted((self.STAGING_DIR / "vendor").iterdir()):
            rel_remote = vendor_dir + "/" + item.name
            if item.is_dir():
                self.upload_dir(item, rel_remote, stats)
            else:
                self.upload_file(item, rel_remote, stats)
        sentinel = VendorSentinel(vendor_checksum())
        self.client.put_text(f"{vendor_dir}/.meta", sentinel.to_text())
        self.log_upload_vendor(stats, started_at)

    def log_upload_vendor(self, stats, started_at):
        duration = time.monotonic() - started_at
        self.log(
            f"Vendor hochgeladen: {stats['files']} Dateien, "
            f"{stats['bytes']} Bytes, {duration:.2f}s"
        )

    def _prepare_slot(self, slot_dir):
        self.client.remove_dir(slot_dir)
        self.client.ensure_dir(slot_dir)

    def migrate_tokens(self, active_dir, inactive_dir):
        src = f"{active_dir}/var/state/tokens"
        dst = f"{inactive_dir}/var/state/tokens"
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

    def upload_deploy_state(self, state: SlotState) -> None:
        checksum = vendor_checksum()
        record = SlotState(
            state.app,
            state.vendor,
            self.run_id,
            checksum,
        )
        DeployState.write(self.client, record)
        self.log(
            f"Deploy-State hochgeladen: Slot {state.app_dir}, "
            f"Vendor {state.vendor_dir}"
        )

    def publish_switch(self, target):
        self.upload_deploy_state(target)

    def cleanup(self, active, target):
        self.client.remove_dir(active.app_dir)
        self.log(f"Alter App-Slot entfernt: {active.app_dir}")
        if active.vendor != target.vendor:
            self.client.remove_dir(active.vendor_dir)
            self.log(f"Alter Vendor entfernt: {active.vendor_dir}")

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


class SftpDeployOps:
    def __init__(self, deploy):
        self._deploy = deploy
        self._state = None
        self._plan = None

    def load_state(self):
        state = DeployState.read(self._deploy.client)
        if state is None and self._both_app_slots_exist():
            raise DeployConflictError(
                ".deploy-state.ini fehlt, aber beide App-Slots vorhanden"
            )
        self._state = state

    def _both_app_slots_exist(self):
        client = self._deploy.client
        return (
            client.file_exists("app-a/.deploy-run")
            and client.file_exists("app-b/.deploy-run")
        )

    def select_target(self):
        include_vendor = self._resolve_include_vendor()
        if self._state is None:
            self._plan = DeploymentPlan.fresh()
        else:
            self._plan = DeploymentPlan.swap(self._state, include_vendor)

    def _resolve_include_vendor(self):
        if self._state is None:
            return True
        return self._deploy._include_vendor(self._state)

    def prepare_target(self):
        pass

    def upload_app(self):
        self._deploy.upload_app_tree(self._plan.target.app_dir)

    def prepare_vendor(self):
        plan = self._plan
        if plan.active is None or plan.target.vendor != plan.active.vendor:
            self._deploy.upload_vendor_dir(plan.target.vendor_dir)
        else:
            self._deploy._log_vendor_decision(plan)

    def migrate_tokens(self):
        if self._plan.active is None:
            self._deploy.upload_static_entry_files()
        else:
            self._deploy.migrate_tokens(
                self._plan.active.app_dir,
                self._plan.target.app_dir,
            )

    def switch(self):
        plan = self._plan
        if plan.active is None:
            self._deploy.publish_switch(plan.target)
        else:
            self._deploy.dispatch_switch(plan.target, plan.active)

    def smoke_ok(self):
        return True  # Schritt 6

    def rollback(self):
        pass  # Schritt 6


def new_stats():
    return {"directories": 0, "files": 0, "bytes": 0}


if __name__ == "__main__":
    main()
