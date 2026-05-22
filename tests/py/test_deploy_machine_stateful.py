import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "src"))

from hypothesis import settings
from hypothesis.stateful import RuleBasedStateMachine, initialize, rule, invariant

from cli.py.deploy.machine import (  # noqa: E402
    DeployConflictError,
    DeployMachine,
    DeployPhase,
)

TERMINAL_PHASES = {
    DeployPhase.VERIFIED,
    DeployPhase.ROLLED_BACK,
    DeployPhase.FAILED_SAFE,
    DeployPhase.MANUAL_INTERVENTION_REQUIRED,
}

PHASES_BEFORE_SWITCH = {
    DeployPhase.STARTED,
    DeployPhase.STATE_LOADED,
    DeployPhase.TARGET_SELECTED,
    DeployPhase.TARGET_PREPARED,
    DeployPhase.APP_UPLOADED,
    DeployPhase.VENDOR_READY,
    DeployPhase.TOKENS_MIGRATED,
}


class ControllableOps:
    def __init__(self):
        self.switch_called = False
        self.rollback_called = False
        self._fail_at = None
        self._smoke = True

    def set_fail_at(self, name):
        self._fail_at = name

    def set_smoke(self, value):
        self._smoke = value

    def _call(self, name):
        if self._fail_at == name:
            raise RuntimeError(name)
        if self._fail_at == f"conflict:{name}":
            raise DeployConflictError(name)

    def load_state(self): self._call("load_state")
    def select_target(self): self._call("select_target")
    def prepare_target(self): self._call("prepare_target")
    def upload_app(self): self._call("upload_app")
    def prepare_vendor(self): self._call("prepare_vendor")
    def migrate_tokens(self): self._call("migrate_tokens")

    def switch(self):
        self._call("switch")
        self.switch_called = True

    def smoke_ok(self) -> bool:
        return self._smoke

    def rollback(self):
        self.rollback_called = True


class DeployMachineStateMachine(RuleBasedStateMachine):

    @initialize()
    def init(self):
        self.ops = ControllableOps()
        self.machine = DeployMachine()
        self.machine.run(self.ops)

    @rule()
    def run_happy(self):
        self.ops = ControllableOps()
        self.machine = DeployMachine()
        self.machine.run(self.ops)

    @rule()
    def run_upload_fails(self):
        self.ops = ControllableOps()
        self.ops.set_fail_at("upload_app")
        self.machine = DeployMachine()
        self.machine.run(self.ops)

    @rule()
    def run_smoke_fails(self):
        self.ops = ControllableOps()
        self.ops.set_smoke(False)
        self.machine = DeployMachine()
        self.machine.run(self.ops)

    @rule()
    def run_conflict(self):
        self.ops = ControllableOps()
        self.ops.set_fail_at("conflict:load_state")
        self.machine = DeployMachine()
        self.machine.run(self.ops)

    @invariant()
    def endet_in_terminalem_zustand(self):
        assert self.machine.phase in TERMINAL_PHASES

    @invariant()
    def switch_nur_wenn_vorphasen_ok(self):
        if self.machine.phase == DeployPhase.FAILED_SAFE:
            if self.machine.history:
                letzter_vor_terminal = self.machine.history[-1]
                assert letzter_vor_terminal in PHASES_BEFORE_SWITCH, (
                    f"switch trotz fehlgeschlagener Phase: {letzter_vor_terminal}"
                )

    @invariant()
    def rollback_nur_nach_smoke_fehler(self):
        if self.machine.phase == DeployPhase.ROLLED_BACK:
            assert self.ops.rollback_called

    @invariant()
    def history_beginnt_mit_started(self):
        assert self.machine.history[0] == DeployPhase.STARTED


DeployMachineTest = DeployMachineStateMachine.TestCase
DeployMachineTest.settings = settings(max_examples=200)
