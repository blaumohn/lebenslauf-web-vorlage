from collections.abc import Callable
from typing import Protocol

from statemachine import State, StateMachine

from cli.py.deploy.sftp_deploy_state import DeploymentPlan, SlotState

MISSING_STATE_CONFLICT = (
    ".htaccess fehlt oder ungültig, aber beide App-Slots vorhanden"
)


class DeployConflictError(Exception):
    pass


class DeployOps(Protocol):
    def load_state(self) -> SlotState | None: ...
    def both_app_slots_exist(self) -> bool: ...
    def should_upload_vendor(self, active: SlotState) -> bool: ...
    def prepare_target(self, plan: DeploymentPlan) -> None: ...
    def upload_app(self, plan: DeploymentPlan) -> None: ...
    def prepare_vendor(self, plan: DeploymentPlan) -> None: ...
    def migrate_tokens(self, plan: DeploymentPlan) -> None: ...
    def switch(self, plan: DeploymentPlan) -> None: ...
    def smoke_ok(self) -> bool: ...
    def rollback(self, state: SlotState | None) -> None: ...
    def cleanup(self, plan: DeploymentPlan) -> None: ...


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
    verified                     = State()
    cleaned_up                   = State(final=True)
    rolled_back                  = State(final=True)
    failed_safe                  = State(final=True)
    manual_intervention_required = State(final=True)

    ev_load              = started.to(state_loaded)
    ev_select_fresh      = state_loaded.to(fresh_selected)
    ev_select_swap       = state_loaded.to(swap_selected)
    ev_select_swap_vendor = state_loaded.to(swap_vendor_update_selected)
    ev_prepare = (
        fresh_selected.to(target_prepared)
        | swap_selected.to(target_prepared)
        | swap_vendor_update_selected.to(target_prepared)
    )
    ev_upload   = target_prepared.to(app_uploaded)
    ev_vendor   = app_uploaded.to(vendor_ready)
    ev_tokens   = vendor_ready.to(tokens_migrated)
    ev_switch   = tokens_migrated.to(switched)
    ev_verify   = switched.to(verified)
    ev_cleanup  = verified.to(cleaned_up)
    ev_rollback = verified.to(rolled_back)

    ev_fail = (
        started.to(failed_safe)
        | state_loaded.to(failed_safe)
        | fresh_selected.to(failed_safe)
        | swap_selected.to(failed_safe)
        | swap_vendor_update_selected.to(failed_safe)
        | target_prepared.to(failed_safe)
        | app_uploaded.to(failed_safe)
        | vendor_ready.to(failed_safe)
        | tokens_migrated.to(failed_safe)
        | switched.to(failed_safe)
        | verified.to(failed_safe)
    )
    ev_conflict = (
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
        | verified.to(manual_intervention_required)
    )

    def __init__(self, on_transition: Callable | None = None):
        super().__init__()
        self._on_transition = on_transition
        self.history: list[str] = []

    def after_transition(self, event, source, target):
        if self._on_transition:
            self._on_transition(source, target)
        self.history.append(source.id)

    def run(self, ops: DeployOps) -> None:
        state = self._step(self.ev_load, ops.load_state)
        plan = self._select_with_transition(ops, state)
        if not self.current_state.final:
            self._run_plan_steps(ops, plan)
            self._verify_or_rollback(ops, plan, state)

    def _select_with_transition(self, ops, state):
        if self.current_state.final:
            return None
        try:
            plan = self._select_plan(ops, state)
            self._fire_select_event(plan)
            return plan
        except DeployConflictError:
            self.ev_conflict()
            return None
        except Exception:
            self.ev_fail()
            return None

    def _fire_select_event(self, plan):
        if plan.active is None:
            self.ev_select_fresh()
        elif plan.target.vendor != plan.active.vendor:
            self.ev_select_swap_vendor()
        else:
            self.ev_select_swap()

    def _step(self, event, action):
        if self.current_state.final:
            return None
        try:
            result = action()
            event()
            return result
        except DeployConflictError:
            self.ev_conflict()
            return None
        except Exception:
            self.ev_fail()
            return None

    def _run_plan_steps(self, ops: DeployOps, plan: DeploymentPlan) -> None:
        self._step(self.ev_prepare, lambda: ops.prepare_target(plan))
        self._step(self.ev_upload,  lambda: ops.upload_app(plan))
        self._step(self.ev_vendor,  lambda: ops.prepare_vendor(plan))
        self._step(self.ev_tokens,  lambda: ops.migrate_tokens(plan))
        self._step(self.ev_switch,  lambda: ops.switch(plan))

    def _verify_or_rollback(
        self,
        ops: DeployOps,
        plan: DeploymentPlan | None,
        state: SlotState | None,
    ) -> None:
        if self.current_state.final:
            return
        ok = self._step(self.ev_verify, ops.smoke_ok)
        if self.current_state.final:
            return
        if ok:
            self._step(self.ev_cleanup, lambda: ops.cleanup(plan))
        else:
            self._step(self.ev_rollback, lambda: ops.rollback(state))

    def _select_plan(
        self,
        ops: DeployOps,
        state: SlotState | None,
    ) -> DeploymentPlan:
        if state is None:
            if ops.both_app_slots_exist():
                raise DeployConflictError(MISSING_STATE_CONFLICT)
            return DeploymentPlan.fresh()
        include_vendor = ops.should_upload_vendor(state)
        return DeploymentPlan.swap(state, include_vendor)
