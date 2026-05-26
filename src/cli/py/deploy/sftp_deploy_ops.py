from collections.abc import Callable

from cli.py.deploy.exceptions import DeployConflictError
from cli.py.deploy.slots import DeploymentPlan, SlotMap


class SftpDeployOps:
    def __init__(
        self,
        *,
        cfg,
        run_id: str,
        slot_store,
        uploader,
        token_migrator,
        slot_publisher,
        switch_dispatcher,
        logger,
        vendor_checksum: Callable[[], str],
        smoke_check: Callable,
    ):
        self.cfg = cfg
        self.run_id = run_id
        self.slot_store = slot_store
        self.uploader = uploader
        self.token_migrator = token_migrator
        self.slot_publisher = slot_publisher
        self.switch_dispatcher = switch_dispatcher
        self.log = logger
        self.vendor_checksum = vendor_checksum
        self.smoke_check = smoke_check

    def load_current_slot_map(self) -> SlotMap | None:
        return self.slot_store.current_slot_map()

    def should_upload_vendor(self, active: SlotMap) -> bool:
        stored = self.slot_store.vendor_checksum_for_slot(
            active.vendor.label
        )
        computed = self.vendor_checksum()
        if stored is None:
            self.log("Vendor-Sentinel fehlt oder ist ungültig")
            return True
        if stored == computed:
            return False
        self._log_vendor_mismatch(stored, computed)
        return True

    def prepare_target(self, _plan: DeploymentPlan) -> None:
        pass

    def upload_app(self, plan: DeploymentPlan) -> None:
        self.uploader.upload_app_tree(
            plan.target_slot_map.app.dir,
            plan.target_slot_map.vendor.dir,
        )

    def upload_vendor(self, plan: DeploymentPlan) -> None:
        self.uploader.upload_vendor_dir(plan.target_slot_map.vendor.dir)

    def skip_vendor(self, plan: DeploymentPlan) -> None:
        self.log_vendor_decision(plan)

    def migrate_tokens(self, plan: DeploymentPlan) -> None:
        self.token_migrator.migrate(
            plan.active_slot_map.app.dir,
            plan.target_slot_map.app.dir,
        )

    def switch_fresh(self, plan: DeploymentPlan) -> None:
        self.slot_publisher.publish(plan.target_slot_map)

    def switch_swap(self, plan: DeploymentPlan) -> None:
        self.switch_dispatcher.dispatch(
            plan.target_slot_map,
            plan.active_slot_map,
        )

    def smoke_ok(self) -> bool:
        return self.smoke_check(self.cfg, self.log)

    def app_uploaded(self, plan: DeploymentPlan) -> bool:
        run_id = self.slot_store.run_id_for_app_slot(
            plan.target_slot_map.app.label
        )
        return run_id == self.run_id

    def vendor_ready(self, plan: DeploymentPlan) -> bool:
        stored = self.slot_store.vendor_checksum_for_slot(
            plan.target_slot_map.vendor.label
        )
        return stored == self.vendor_checksum()

    def switched(self, plan: DeploymentPlan) -> bool:
        try:
            current = self.slot_store.current_slot_map()
        except DeployConflictError:
            return False
        return current is not None and current == plan.target_slot_map

    def abort_before_switch(
        self,
        _plan: DeploymentPlan | None,
        _active_slot_map: SlotMap | None,
    ) -> None:
        self.log("Abbruch vor Switch — aktiver Slot unberührt")

    def rollback_after_switch(
        self,
        _plan: DeploymentPlan | None,
        previous_slot_map: SlotMap | None,
    ) -> None:
        if previous_slot_map is None:
            raise DeployConflictError(
                "Rollback nicht möglich: keine vorherige Slot-Zuordnung"
            )
        self.slot_store.activate_slot_map(previous_slot_map)
        self.log(
            f"Rollback: {previous_slot_map.app.dir}, "
            f"Vendor {previous_slot_map.vendor.dir}"
        )

    def log_vendor_decision(self, plan: DeploymentPlan) -> None:
        if plan.target_slot_map.vendor != plan.active_slot_map.vendor:
            vendor_dir = plan.target_slot_map.vendor.dir
            self.log(f"Vendor neu hochladen: {vendor_dir}")
            return
        vendor_dir = plan.target_slot_map.vendor.dir
        self.log(f"Vendor unverändert: {vendor_dir}")

    def _log_vendor_mismatch(self, stored: str, computed: str) -> None:
        self.log(
            f"Composer-Checksum abweichend: "
            f"gespeichert={stored!r}, berechnet={computed!r}"
        )
