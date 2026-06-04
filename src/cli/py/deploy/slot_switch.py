from cli.py.deploy.history import DeployHistoryWriter
from cli.py.deploy.slot_store import SlotStore
from cli.py.deploy.slots import SlotMap


class SlotPublisher:
    def __init__(self, slot_store: SlotStore, logger):
        self.slot_store = slot_store
        self.log = logger

    def publish(self, slot_map: SlotMap) -> None:
        self.slot_store.activate_slot_map(slot_map)
        self.log(
            f"Aktive Slots veröffentlicht: App {slot_map.app.dir}, "
            f"Vendor {slot_map.vendor.dir}"
        )


class SlotSwitchDispatcher:
    def __init__(
        self,
        cfg,
        run_id: str,
        client,
        logger,
        task_dispatch_cls,
        task_cls,
        publisher: SlotPublisher,
        history_writer: DeployHistoryWriter | None = None,
    ):
        self.cfg = cfg
        self.run_id = run_id
        self.client = client
        self.log = logger
        self.task_dispatch_cls = task_dispatch_cls
        self.task_cls = task_cls
        self.publisher = publisher
        self.history_writer = history_writer

    def dispatch(self, target: SlotMap, active: SlotMap) -> None:
        reason = self._system_invalid_reason(active)
        if reason is None:
            self._dispatch_via_task(target)
            return
        self.log(reason)
        self.publisher.publish(target)

    def _system_invalid_reason(self, active: SlotMap) -> str | None:
        if not self.client.file_exists(f"{active.app.dir}/.deploy-run"):
            return (
                "Warnung: App-Sentinel fehlt — "
                "Switch direkt via SFTP"
            )
        dispatch = self.task_dispatch_cls(self.cfg, logger=self.log)
        if not dispatch.http_reachable():
            return "App nicht erreichbar — Switch direkt via SFTP"
        return None

    def _dispatch_via_task(self, target: SlotMap) -> None:
        task = self.task_cls("deploy_switch", {
            "app": target.app.label,
            "vendor": target.vendor.label,
            "pipeline_run_id": self.run_id,
        })
        self._record("task_dispatched", target_app=target.app.label, target_vendor=target.vendor.label)
        self.task_dispatch_cls(self.cfg, logger=self.log).submit(task)
        self._record("task_confirmed")
        self.log(
            f"Switch ausgelöst: Slot {target.app.dir}, "
            f"Vendor {target.vendor.dir}, Run {self.run_id}"
        )

    def _record(self, event: str, **kwargs) -> None:
        if self.history_writer:
            self.history_writer.record(event, **kwargs)
