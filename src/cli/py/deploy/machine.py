from dataclasses import dataclass, field
from enum import Enum, auto
from typing import Callable, Protocol


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
    def load_state(self) -> None: ...
    def select_target(self) -> None: ...
    def prepare_target(self) -> None: ...
    def upload_app(self) -> None: ...
    def prepare_vendor(self) -> None: ...
    def migrate_tokens(self) -> None: ...
    def switch(self) -> None: ...
    def smoke_ok(self) -> bool: ...
    def rollback(self) -> None: ...


@dataclass
class DeployMachine:
    phase: DeployPhase = field(default=DeployPhase.STARTED)
    history: list[DeployPhase] = field(default_factory=list)
    on_transition: Callable | None = field(default=None)

    def run(self, ops: DeployOps) -> None:
        self.transition(DeployPhase.STATE_LOADED, ops.load_state)
        self.transition(DeployPhase.TARGET_SELECTED, ops.select_target)
        self.transition(DeployPhase.TARGET_PREPARED, ops.prepare_target)
        self.transition(DeployPhase.APP_UPLOADED, ops.upload_app)
        self.transition(DeployPhase.VENDOR_READY, ops.prepare_vendor)
        self.transition(DeployPhase.TOKENS_MIGRATED, ops.migrate_tokens)
        self.transition(DeployPhase.SWITCHED, ops.switch)
        self._verify_or_rollback(ops)

    def transition(self, target: DeployPhase, action: Callable) -> None:
        if self.phase.is_terminal:
            return
        try:
            action()
            self._advance(target)
        except DeployConflictError:
            self._advance(DeployPhase.MANUAL_INTERVENTION_REQUIRED)
        except Exception:
            self._advance(DeployPhase.FAILED_SAFE)

    def _verify_or_rollback(self, ops: DeployOps) -> None:
        if self.phase.is_terminal:
            return
        if ops.smoke_ok():
            self._advance(DeployPhase.VERIFIED)
        else:
            ops.rollback()
            self._advance(DeployPhase.ROLLED_BACK)

    def _advance(self, target: DeployPhase) -> None:
        if self.on_transition:
            self.on_transition(self.phase, target)
        self.history.append(self.phase)
        self.phase = target
