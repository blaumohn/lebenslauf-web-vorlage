import configparser
import stat
import time
from pathlib import Path

import requests
import requests.exceptions

from cli.py.deploy.exceptions import DeployConflictError
from cli.py.deploy.machine import DeployMachine
from cli.py.deploy.sftp_deploy_state import (
    DeploymentPlan,
    SlotMap,
    SlotStore,
)
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.deploy.vendor_sentinel import (
    ComposerInputChecksum,
    VendorSentinel,
)
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


def smoke_check(cfg, log) -> bool:
    url = cfg.get("APP_ROOT_URL", "")
    if not url:
        log(
            "Warnung: APP_ROOT_URL nicht konfiguriert — "
            "Smoke übersprungen"
        )
        return True
    try:
        resp = requests.get(url, timeout=10, allow_redirects=True)
        return resp.status_code == 200
    except requests.exceptions.RequestException:
        return False


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
        machine = DeployMachine(ops, on_transition=self._log_phase)
        machine.run()
        self.deploy_phase = machine.current_state
        self._log_deploy_result(machine.current_state)
        self._raise_if_failed(machine.current_state)

    def _log_phase(self, source, target):
        self.log(f"Phase: {source.id} → {target.id}")

    def _log_deploy_result(self, state):
        if state == DeployMachine.manual_intervention_required:
            self.log(
                "Manueller Eingriff erforderlich — keine Änderungen"
            )
        elif state == DeployMachine.failed_safe:
            self.log(
                "Deploy fehlgeschlagen — aktiver Deploy unberührt"
            )
        elif state == DeployMachine.cleaned_up:
            self.log("Deploy abgeschlossen")

    def _raise_if_failed(self, state):
        if state == DeployMachine.cleaned_up:
            return
        if state == DeployMachine.manual_intervention_required:
            raise RuntimeError("Manueller Eingriff erforderlich")
        raise RuntimeError(f"Deploy fehlgeschlagen: {state.id}")

    def deploy_fresh(self):
        plan = DeploymentPlan.fresh()
        target = plan.target_slot_map
        self.log(
            f"Erstdeploy: Slot {target.app.dir}, "
            f"Vendor {target.vendor.dir}"
        )
        self.upload_app_tree(target.app.dir, target.vendor.dir)
        self.upload_vendor_dir(target.vendor.dir)
        self.publish_switch(target)
        self.log("Erstdeploy abgeschlossen")

    def deploy_swap(self, state):
        active = state
        include_vendor = self._include_vendor(active)
        plan = DeploymentPlan.swap(active, include_vendor)
        target = plan.target_slot_map
        self.log(
            f"Slot: {active.app.dir}→{target.app.dir}, "
            f"Vendor: {active.vendor.dir}→{target.vendor.dir}"
        )
        self._log_vendor_decision(plan)
        self.upload_app_tree(target.app.dir, target.vendor.dir)
        if include_vendor:
            self.upload_vendor_dir(target.vendor.dir)
        self.migrate_tokens(active.app.dir, target.app.dir)
        self.dispatch_switch(target, active)
        self.log(
            f"Deploy vorbereitet: Slot {target.app.dir}, "
            f"Vendor {target.vendor.dir}"
        )

    def _log_vendor_decision(self, plan):
        if (
            plan.target_slot_map.vendor
            != plan.active_slot_map.vendor
        ):
            self.log(
                f"Vendor neu hochladen: {plan.target_slot_map.vendor.dir}"
            )
        else:
            self.log(
                f"Vendor unverändert: {plan.target_slot_map.vendor.dir}"
            )

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
            path = f"{active.vendor.dir}/.meta"
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
            self._switch_via_sftp(target, active)

    def _system_invalid_reason(self, active):
        if not self.client.file_exists(
            f"{active.app.dir}/.deploy-run"
        ):
            return "Warnung: App-Sentinel fehlt — Switch direkt via SFTP"
        if not TaskDispatch(self.cfg, logger=self.log).http_reachable():
            return "App nicht erreichbar — Switch direkt via SFTP"
        return None

    def _switch_via_sftp(self, target, active):
        self.upload_deploy_state(target)

    def _dispatch_via_task(self, target):
        task = Task("deploy_switch", {
            "app": target.app.label,
            "vendor": target.vendor.label,
            "pipeline_run_id": self.run_id,
        })
        TaskDispatch(self.cfg, logger=self.log).submit(task)
        self.log(
            f"Switch ausgelöst: Slot {target.app.dir}, "
            f"Vendor {target.vendor.dir}, Run {self.run_id}"
        )

    def upload_app_tree(self, app_dir, vendor_dir):
        self._inject_vendor_require(vendor_dir)
        stats = new_stats()
        started_at = time.monotonic()
        self.log(f"Upload App-Slot: {app_dir}")
        self._prepare_slot(app_dir)
        for item in sorted(self.STAGING_DIR.iterdir()):
            self.upload_app_item(item, app_dir, stats)
        self.client.put_text(f"{app_dir}/.deploy-run", self.run_id)
        self.log_upload_app_tree(app_dir, stats, started_at)

    def _inject_vendor_require(self, vendor_dir):
        bootstrap = self.STAGING_DIR / "src/Http/bootstrap.php"
        original = bootstrap.read_text(encoding="utf-8")
        old = "require $vendorDir . '/autoload.php';"
        new = (
            f"require dirname(__DIR__, 3) . '/{vendor_dir}/autoload.php';"
        )
        if old not in original:
            raise RuntimeError(
                f"bootstrap.php: Zeile '{old}' nicht gefunden — "
                "Vendor-Inject fehlgeschlagen. "
                "Wenn diese Zeile geändert wurde, muss auch "
                "_inject_vendor_require() angepasst werden. "
                "Siehe: https://docs.template.ysdani.com/de/areas/deploy/slot-switch/"
            )
        bootstrap.write_text(
            original.replace(old, new, 1), encoding="utf-8"
        )

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
        for item in sorted(
            (self.STAGING_DIR / "vendor").iterdir()
        ):
            rel_remote = vendor_dir + "/" + item.name
            if item.is_dir():
                self.upload_dir(item, rel_remote, stats)
            else:
                self.upload_file(item, rel_remote, stats)
        sentinel = VendorSentinel(vendor_checksum())
        self.client.put_text(
            f"{vendor_dir}/.meta", sentinel.to_text()
        )
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

    def upload_deploy_state(self, state: SlotMap) -> None:
        SlotStore(self.client).activate_slot_map(state)
        self.log(
            f"Deploy-State hochgeladen: Slot {state.app.dir}, "
            f"Vendor {state.vendor.dir}"
        )

    def publish_switch(self, target):
        self.upload_deploy_state(target)

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

    def load_state(self):
        return SlotStore(self._deploy.client).current_slot_map()

    def should_upload_vendor(self, active):
        return self._deploy._include_vendor(active)

    def prepare_target(self, _plan):
        pass

    def upload_app(self, plan):
        self._deploy.upload_app_tree(
            plan.target_slot_map.app.dir,
            plan.target_slot_map.vendor.dir,
        )

    def upload_vendor(self, plan):
        self._deploy.upload_vendor_dir(
            plan.target_slot_map.vendor.dir
        )

    def skip_vendor(self, plan):
        self._deploy._log_vendor_decision(plan)

    def migrate_tokens(self, plan):
        self._deploy.migrate_tokens(
            plan.active_slot_map.app.dir,
            plan.target_slot_map.app.dir,
        )

    def switch_fresh(self, plan):
        self._deploy.publish_switch(plan.target_slot_map)

    def switch_swap(self, plan):
        self._deploy.dispatch_switch(
            plan.target_slot_map,
            plan.active_slot_map,
        )

    def smoke_ok(self):
        return smoke_check(self._deploy.cfg, self._deploy.log)

    def app_uploaded(self, plan) -> bool:
        store = SlotStore(self._deploy.client)
        run_id = store.run_id_for_app_slot(
            plan.target_slot_map.app.label
        )
        return run_id == self._deploy.run_id

    def vendor_ready(self, plan) -> bool:
        store = SlotStore(self._deploy.client)
        stored = store.vendor_checksum_for_slot(
            plan.target_slot_map.vendor.label
        )
        return stored == vendor_checksum()

    def switched(self, plan) -> bool:
        try:
            current = SlotStore(
                self._deploy.client
            ).current_slot_map()
        except DeployConflictError:
            return False
        return (
            current is not None
            and current == plan.target_slot_map
        )

    def abort_before_switch(self, plan, state) -> None:
        self._deploy.log(
            "Abbruch vor Switch — aktiver Slot unberührt"
        )

    def rollback_after_switch(self, plan, state) -> None:
        if state is None:
            raise DeployConflictError(
                "Rollback nicht möglich: kein vorheriger State"
            )
        SlotStore(self._deploy.client).activate_slot_map(state)
        self._deploy.log(
            f"Rollback: {state.app.dir}, "
            f"Vendor {state.vendor.dir}"
        )


def new_stats():
    return {"directories": 0, "files": 0, "bytes": 0}


if __name__ == "__main__":
    main()
