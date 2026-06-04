from collections.abc import Callable
from contextlib import suppress
from typing import Protocol

from statemachine import State, StateMachine

from cli.py.deploy.exceptions import DeployConflictError
from cli.py.deploy.slots import DeploymentPlan, SlotMap


class DeployOps(Protocol):
    def load_current_slot_map(self) -> SlotMap | None: ...
    def should_upload_vendor(self, active: SlotMap) -> bool: ...
    def prepare_target(self, plan: DeploymentPlan) -> None: ...
    def upload_app(self, plan: DeploymentPlan) -> None: ...
    def upload_vendor(self, plan: DeploymentPlan) -> None: ...
    def skip_vendor(self, plan: DeploymentPlan) -> None: ...
    def migrate_tokens(self, plan: DeploymentPlan) -> None: ...
    def switch_fresh(self, plan: DeploymentPlan) -> None: ...
    def switch_swap(self, plan: DeploymentPlan) -> None: ...
    def smoke_ok(self) -> None: ...
    def app_uploaded(self, plan: DeploymentPlan) -> bool: ...
    def vendor_ready(self, plan: DeploymentPlan) -> bool: ...
    def switched(self, plan: DeploymentPlan) -> bool: ...
    def abort_before_switch(
        self,
        plan: DeploymentPlan | None,
        active_slot_map: SlotMap | None,
    ) -> None: ...
    def rollback_after_switch(
        self,
        plan: DeploymentPlan | None,
        previous_slot_map: SlotMap | None,
    ) -> None: ...


class DeployMachine(StateMachine):
    started                      = State(initial=True)
    state_loaded                 = State()
    fresh_selected               = State()
    swap_selected                = State()
    swap_vendor_update_selected  = State()
    target_prepared              = State()
    app_uploaded                 = State()
    vendor_ready                 = State()
    tokens_migrated              = State()
    switched                     = State()
    smoke_passed                     = State()
    cleaned_up                   = State(final=True)
    rolled_back                  = State(final=True)
    deploy_failed                  = State(final=True)
    manual_intervention_required = State(final=True)

    load               = started.to(state_loaded)
    select_fresh       = state_loaded.to(fresh_selected)
    select_swap        = state_loaded.to(swap_selected)
    select_swap_vendor = state_loaded.to(swap_vendor_update_selected)
    prepare = (
        fresh_selected.to(target_prepared)
        | swap_selected.to(target_prepared)
        | swap_vendor_update_selected.to(target_prepared)
    )
    upload         = target_prepared.to(app_uploaded)
    vendor_upload  = app_uploaded.to(vendor_ready)
    vendor_skip    = app_uploaded.to(vendor_ready)
    tokens_migrate = vendor_ready.to(tokens_migrated)
    tokens_skip    = vendor_ready.to(tokens_migrated)
    switch_fresh   = tokens_migrated.to(switched)
    switch_swap    = tokens_migrated.to(switched)
    verify         = switched.to(smoke_passed)
    done           = smoke_passed.to(cleaned_up)
    rollback = (
        switched.to(rolled_back)
        | smoke_passed.to(rolled_back)
    )
    fail = (
        started.to(deploy_failed)
        | state_loaded.to(deploy_failed)
        | fresh_selected.to(deploy_failed)
        | swap_selected.to(deploy_failed)
        | swap_vendor_update_selected.to(deploy_failed)
        | target_prepared.to(deploy_failed)
        | app_uploaded.to(deploy_failed)
        | vendor_ready.to(deploy_failed)
        | tokens_migrated.to(deploy_failed)
        | switched.to(deploy_failed)
        | smoke_passed.to(deploy_failed)
    )
    conflict = (
        started.to(manual_intervention_required)
        | state_loaded.to(manual_intervention_required)
        | fresh_selected.to(manual_intervention_required)
        | swap_selected.to(manual_intervention_required)
        | swap_vendor_update_selected.to(manual_intervention_required)
        | target_prepared.to(manual_intervention_required)
        | app_uploaded.to(manual_intervention_required)
        | vendor_ready.to(manual_intervention_required)
        | tokens_migrated.to(manual_intervention_required)
        | switched.to(manual_intervention_required)
        | smoke_passed.to(manual_intervention_required)
    )

    def __init__(
        self,
        ops: DeployOps,
        on_transition: Callable | None = None,
        on_error: Callable | None = None,
    ):
        super().__init__()
        self._ops = ops
        self._plan: DeploymentPlan | None = None
        self._current_slot_map: SlotMap | None = None
        self._post_switch: bool = False
        self._on_transition = on_transition
        self._on_error = on_error
        self.history: list[str] = []

    def run(self) -> None:
        self._guarded(self._step_load)

    def on_enter_state_loaded(self) -> None:
        self._guarded(self._step_select)

    def on_enter_fresh_selected(self) -> None:
        self._guarded(self._step_prepare)

    def on_enter_swap_selected(self) -> None:
        self._guarded(self._step_prepare)

    def on_enter_swap_vendor_update_selected(self) -> None:
        self._guarded(self._step_prepare)

    def on_enter_target_prepared(self) -> None:
        self._guarded(self._step_upload_app)

    def on_enter_app_uploaded(self) -> None:
        self._guarded(self._step_vendor)

    def on_enter_vendor_ready(self) -> None:
        self._guarded(self._step_tokens)

    def on_enter_tokens_migrated(self) -> None:
        self._guarded(self._step_switch)

    def on_enter_switched(self) -> None:
        self._post_switch = True
        self._guarded(self._step_verify)

    def on_enter_smoke_passed(self) -> None:
        self.done()

    def after_transition(self, event, source, target) -> None:
        _ = event
        if self._on_transition:
            self._on_transition(source, target)
        self.history.append(source.id)

    def _step_load(self) -> None:
        self._current_slot_map = self._ops.load_current_slot_map()
        self.load()

    def _step_select(self) -> None:
        self._plan = self._build_plan()
        if self._plan.active_slot_map is None:
            self.select_fresh()
            return
        if self._vendor_changed():
            self.select_swap_vendor()
        else:
            self.select_swap()

    def _build_plan(self) -> DeploymentPlan:
        if self._current_slot_map is None:
            return DeploymentPlan.fresh()
        include_vendor = self._ops.should_upload_vendor(
            self._current_slot_map
        )
        return DeploymentPlan.swap(
            self._current_slot_map,
            include_vendor,
        )

    def _vendor_changed(self) -> bool:
        return (
            self._plan.target_slot_map.vendor
            != self._plan.active_slot_map.vendor
        )

    def _step_prepare(self) -> None:
        self._ops.prepare_target(self._plan)
        self.prepare()

    def _step_upload_app(self) -> None:
        if not self._ops.app_uploaded(self._plan):
            self._ops.upload_app(self._plan)
        self.upload()

    def _step_vendor(self) -> None:
        if not self._needs_vendor_upload():
            self._ops.skip_vendor(self._plan)
            self.vendor_skip()
            return
        if not self._ops.vendor_ready(self._plan):
            self._ops.upload_vendor(self._plan)
        self.vendor_upload()

    def _needs_vendor_upload(self) -> bool:
        if self._plan.active_slot_map is None:
            return True
        return (
            self._plan.target_slot_map.vendor
            != self._plan.active_slot_map.vendor
        )

    def _step_tokens(self) -> None:
        if self._plan.active_slot_map is None:
            self.tokens_skip()
            return
        self._ops.migrate_tokens(self._plan)
        self.tokens_migrate()

    def _step_switch(self) -> None:
        is_fresh = self._plan.active_slot_map is None
        if not self._ops.switched(self._plan):
            self._run_switch_action(is_fresh)
        if is_fresh:
            self.switch_fresh()
        else:
            self.switch_swap()

    def _run_switch_action(self, is_fresh: bool) -> None:
        if is_fresh:
            self._ops.switch_fresh(self._plan)
        else:
            self._ops.switch_swap(self._plan)

    def _step_verify(self) -> None:
        self._ops.smoke_ok()
        self.verify()

    def _guarded(self, action: Callable) -> None:
        if self.current_state.final:
            return
        try:
            action()
        except DeployConflictError as exc:
            self._report_error(exc)
            self.conflict()
        except Exception as exc:
            self._report_error(exc)
            if self._post_switch:
                self._recover_post_switch()
            else:
                self._abort_and_fail()

    def _abort_and_fail(self) -> None:
        with suppress(Exception):
            self._ops.abort_before_switch(
                self._plan, self._current_slot_map
            )
        self.fail()

    def _recover_post_switch(self) -> None:
        try:
            self._ops.rollback_after_switch(
                self._plan, self._current_slot_map
            )
            self.rollback()
        except DeployConflictError as exc:
            self._report_error(exc)
            self.conflict()
        except Exception as exc:
            self._report_error(exc)
            self.fail()

    def _report_error(self, exc: Exception) -> None:
        if self._on_error:
            self._on_error(exc)
