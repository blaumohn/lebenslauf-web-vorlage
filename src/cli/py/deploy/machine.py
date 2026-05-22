from collections.abc import Callable
from dataclasses import dataclass, field
from enum import Enum, auto
from typing import Protocol

from cli.py.deploy.sftp_deploy_state import DeploymentPlan, SlotState

MISSING_STATE_CONFLICT = (
    ".deploy-state.ini fehlt, aber beide App-Slots vorhanden"
)


class DeployPhase(Enum):
    STARTED = auto()
    STATE_LOADED = auto()
    TARGET_SELECTED = auto()
    TARGET_PREPARED = auto()
    APP_UPLOADED = auto()
    VENDOR_READY = auto()
    TOKENS_MIGRATED = auto()
    SWITCHED = auto()
    VERIFIED = auto()
    ROLLED_BACK = auto()
    FAILED_SAFE = auto()
    MANUAL_INTERVENTION_REQUIRED = auto()

    @property
    def is_terminal(self) -> bool:
        return self in _TERMINAL_PHASES


_TERMINAL_PHASES = frozenset({
    DeployPhase.VERIFIED,
    DeployPhase.ROLLED_BACK,
    DeployPhase.FAILED_SAFE,
    DeployPhase.MANUAL_INTERVENTION_REQUIRED,
})


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


@dataclass
class DeployMachine:
    phase: DeployPhase = field(default=DeployPhase.STARTED)
    history: list[DeployPhase] = field(default_factory=list)
    on_transition: Callable | None = field(default=None)

    def run(self, ops: DeployOps) -> None:
        state = self.transition(
            DeployPhase.STATE_LOADED,
            ops.load_state,
        )
        plan = self.transition(
            DeployPhase.TARGET_SELECTED,
            lambda: self._select_plan(ops, state),
        )
        self._run_plan_steps(ops, plan)
        self._verify_or_rollback(ops, state)

    def _run_plan_steps(
        self,
        ops: DeployOps,
        plan: DeploymentPlan,
    ) -> None:
        self.transition(
            DeployPhase.TARGET_PREPARED,
            lambda: ops.prepare_target(plan),
        )
        self.transition(
            DeployPhase.APP_UPLOADED,
            lambda: ops.upload_app(plan),
        )
        self.transition(
            DeployPhase.VENDOR_READY,
            lambda: ops.prepare_vendor(plan),
        )
        self.transition(
            DeployPhase.TOKENS_MIGRATED,
            lambda: ops.migrate_tokens(plan),
        )
        self.transition(DeployPhase.SWITCHED, lambda: ops.switch(plan))

    def transition(self, target: DeployPhase, action: Callable):
        if self.phase.is_terminal:
            return None
        try:
            result = action()
            self._advance(target)
            return result
        except DeployConflictError:
            self._advance(DeployPhase.MANUAL_INTERVENTION_REQUIRED)
        except Exception:
            self._advance(DeployPhase.FAILED_SAFE)
        return None

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

    def _verify_or_rollback(
        self,
        ops: DeployOps,
        state: SlotState | None,
    ) -> None:
        if self.phase.is_terminal:
            return
        if ops.smoke_ok():
            self._advance(DeployPhase.VERIFIED)
        else:
            ops.rollback(state)
            self._advance(DeployPhase.ROLLED_BACK)

    def _advance(self, target: DeployPhase) -> None:
        if self.on_transition:
            self.on_transition(self.phase, target)
        self.history.append(self.phase)
        self.phase = target
