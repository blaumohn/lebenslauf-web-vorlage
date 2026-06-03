from pathlib import Path

import requests
import requests.exceptions

from cli.py.deploy.machine import DeployMachine
from cli.py.deploy.sftp_deploy_ops import SftpDeployOps
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.deploy.slot_store import SlotStore
from cli.py.deploy.slot_switch import (
    SlotPublisher,
    SlotSwitchDispatcher,
)
from cli.py.deploy.token_migrator import RuntimeTokenMigrator
from cli.py.deploy.sftp_deploy_uploader import SftpDeployUploader
from cli.py.deploy.vendor_sentinel import ComposerInputChecksum
from cli.py.pipeline_cfg import PipelineCfg
from cli.py.task.dispatch import TaskDispatch
from cli.py.task.task import Task
from cli.py.util.envvar import env
from cli.py.util.log import Logger


def format_target(cfg):
    return (
        f"{cfg['SFTP_HOST']}:{cfg['SFTP_PORT']}"
        f"{cfg['SFTP_SERVER_DIR']}"
    )


def vendor_checksum() -> str:
    return ComposerInputChecksum.from_repo()


def smoke_check(cfg, log) -> None:
    url = cfg.get("APP_ROOT_URL", "")
    if not url:
        log(
            "Warnung: APP_ROOT_URL nicht konfiguriert — "
            "Smoke übersprungen"
        )
        return
    resp = requests.get(url, timeout=10, allow_redirects=True)
    if resp.status_code != 200:
        raise RuntimeError(
            f"Smoke fehlgeschlagen: HTTP {resp.status_code} — {url}"
        )


def main():
    logger = Logger("sftp")
    cfg = PipelineCfg("deploy")
    run_id = env("PIPELINE_RUN_ID").require_nonempty().value()
    logger(f"Verbinde zu {format_target(cfg)}")
    SftpDeploy(cfg, run_id, logger=logger).start()


class SftpDeploy:
    STAGING_DIR = Path("var/deploy")

    def __init__(self, cfg, run_id, logger: Logger):
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
        machine = DeployMachine(
            self._build_ops(),
            on_transition=self._log_deploy_state,
            on_error=self.log.error,
        )
        machine.run()
        self.deploy_phase = machine.current_state
        self._log_deploy_result(machine.current_state)
        self._raise_if_failed(machine.current_state)

    def _build_ops(self):
        slot_store = SlotStore(self.client)
        publisher = SlotPublisher(slot_store, self.log)
        return SftpDeployOps(
            cfg=self.cfg,
            run_id=self.run_id,
            slot_store=slot_store,
            uploader=self._tree_uploader(),
            token_migrator=RuntimeTokenMigrator(self.client, self.log),
            slot_publisher=publisher,
            switch_dispatcher=self._switch_dispatcher(publisher),
            logger=self.log,
            vendor_checksum=vendor_checksum,
            smoke_check=smoke_check,
        )

    def _tree_uploader(self):
        return SftpDeployUploader(
            self.client,
            self.STAGING_DIR,
            self.run_id,
            vendor_checksum,
            self.log,
        )

    def _switch_dispatcher(self, publisher):
        return SlotSwitchDispatcher(
            self.cfg,
            self.run_id,
            self.client,
            self.log,
            TaskDispatch,
            Task,
            publisher,
        )

    def _log_deploy_state(self, source, target):
        self.log(f"Deploy-State: {source.id} → {target.id}")

    def _log_deploy_result(self, state):
        if state == DeployMachine.manual_intervention_required:
            self.log(
                "Manueller Eingriff erforderlich — keine Änderungen"
            )
        elif state == DeployMachine.failed_safe:
            self.log(
                "Deploy fehlgeschlagen — aktiver Deploy unberührt"
            )
        elif state == DeployMachine.rolled_back:
            self.log("Deploy zurückgerollt — vorheriger Slot aktiv")
        elif state == DeployMachine.cleaned_up:
            self.log("Deploy abgeschlossen")

    def _raise_if_failed(self, state):
        if state in (DeployMachine.cleaned_up, DeployMachine.rolled_back):
            return
        if state == DeployMachine.manual_intervention_required:
            raise RuntimeError("Manueller Eingriff erforderlich")
        raise RuntimeError(f"Deploy fehlgeschlagen: {state.id}")


if __name__ == "__main__":
    main()
