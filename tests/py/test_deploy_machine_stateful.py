import sys
from dataclasses import dataclass
from pathlib import Path

import pytest
from hypothesis import settings
from hypothesis import strategies as st
from hypothesis.stateful import (
    RuleBasedStateMachine,
    initialize,
    invariant,
    rule,
)

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "src"))

from cli.py.deploy.machine import (  # noqa: E402
    DeployConflictError,
    DeployMachine,
    DeployPhase,
)

STEP_NAMES = (
    "load_state",
    "select_target",
    "prepare_target",
    "upload_app",
    "prepare_vendor",
    "migrate_tokens",
    "switch",
)

PHASE_FLOW = (
    DeployPhase.STARTED,
    DeployPhase.STATE_LOADED,
    DeployPhase.TARGET_SELECTED,
    DeployPhase.TARGET_PREPARED,
    DeployPhase.APP_UPLOADED,
    DeployPhase.VENDOR_READY,
    DeployPhase.TOKENS_MIGRATED,
    DeployPhase.SWITCHED,
)

TERMINAL_PHASES = {
    DeployPhase.VERIFIED,
    DeployPhase.ROLLED_BACK,
    DeployPhase.FAILED_SAFE,
    DeployPhase.MANUAL_INTERVENTION_REQUIRED,
}


@dataclass(frozen=True)
class DeployScenario:
    fail_at: str | None = None
    error_kind: str | None = None
    smoke: bool = True


class ControllableOps:
    def __init__(self, scenario):
        self.scenario = scenario
        self.calls = []
        self.completed = []

    def load_state(self): self._call("load_state")
    def select_target(self): self._call("select_target")
    def prepare_target(self): self._call("prepare_target")
    def upload_app(self): self._call("upload_app")
    def prepare_vendor(self): self._call("prepare_vendor")
    def migrate_tokens(self): self._call("migrate_tokens")
    def switch(self): self._call("switch")

    def smoke_ok(self) -> bool:
        self.calls.append("smoke_ok")
        return self.scenario.smoke

    def rollback(self):
        self.calls.append("rollback")
        self.completed.append("rollback")

    def _call(self, name):
        self.calls.append(name)
        if self.scenario.fail_at != name:
            self.completed.append(name)
            return
        if self.scenario.error_kind == "conflict":
            raise DeployConflictError(name)
        raise RuntimeError(name)


def expected_phase(scenario):
    if scenario.error_kind == "conflict":
        return DeployPhase.MANUAL_INTERVENTION_REQUIRED
    if scenario.error_kind == "runtime":
        return DeployPhase.FAILED_SAFE
    if scenario.smoke:
        return DeployPhase.VERIFIED
    return DeployPhase.ROLLED_BACK


def expected_history(scenario):
    if scenario.fail_at is None:
        return list(PHASE_FLOW)
    fail_index = STEP_NAMES.index(scenario.fail_at)
    return list(PHASE_FLOW[: fail_index + 1])


def expected_calls(scenario):
    if scenario.fail_at is not None:
        fail_index = STEP_NAMES.index(scenario.fail_at)
        return list(STEP_NAMES[: fail_index + 1])
    calls = [*STEP_NAMES, "smoke_ok"]
    if not scenario.smoke:
        calls.append("rollback")
    return calls


def normalize_error_kind(fail_at, error_kind):
    if fail_at is None:
        return None
    return error_kind


def make_scenario(fail_at=None, error_kind="runtime", smoke=True):
    kind = normalize_error_kind(fail_at, error_kind)
    return DeployScenario(fail_at=fail_at, error_kind=kind, smoke=smoke)


class DeployMachineStateMachine(RuleBasedStateMachine):
    @initialize()
    def init(self):
        self.run_scenario(make_scenario())

    @rule(
        fail_at=st.one_of(st.none(), st.sampled_from(STEP_NAMES)),
        error_kind=st.sampled_from(("runtime", "conflict")),
        smoke=st.booleans(),
    )
    def run_generated(self, fail_at, error_kind, smoke):
        scenario = make_scenario(fail_at, error_kind, smoke)
        self.run_scenario(scenario)

    def run_scenario(self, scenario):
        self.scenario = scenario
        self.ops = ControllableOps(scenario)
        self.machine = DeployMachine()
        self.machine.run(self.ops)

    @invariant()
    def endet_in_erwartetem_terminalzustand(self):
        assert self.machine.phase in TERMINAL_PHASES
        assert self.machine.phase == expected_phase(self.scenario)

    @invariant()
    def history_folgt_phasenordnung(self):
        assert self.machine.history == expected_history(self.scenario)

    @invariant()
    def calls_stoppen_nach_erster_terminalphase(self):
        assert self.ops.calls == expected_calls(self.scenario)

    @invariant()
    def switch_nur_nach_vorphasen(self):
        if "switch" not in self.ops.calls:
            return
        assert self.ops.completed[:6] == list(STEP_NAMES[:6])

    @invariant()
    def smoke_nur_nach_erfolgreichem_switch(self):
        if "smoke_ok" not in self.ops.calls:
            return
        assert "switch" in self.ops.completed

    @invariant()
    def rollback_nur_nach_smoke_fehler(self):
        if "rollback" not in self.ops.calls:
            return
        assert "smoke_ok" in self.ops.calls
        assert not self.scenario.smoke


@pytest.mark.parametrize("step", STEP_NAMES)
@pytest.mark.parametrize(
    ("error_kind", "expected"),
    [
        ("runtime", DeployPhase.FAILED_SAFE),
        ("conflict", DeployPhase.MANUAL_INTERVENTION_REQUIRED),
    ],
)
def test_jeder_schritt_kann_fehlschlagen(step, error_kind, expected):
    scenario = make_scenario(fail_at=step, error_kind=error_kind)
    ops = ControllableOps(scenario)
    machine = DeployMachine()

    machine.run(ops)

    assert machine.phase == expected
    assert ops.calls == expected_calls(scenario)


DeployMachineTest = DeployMachineStateMachine.TestCase
DeployMachineTest.settings = settings(max_examples=200)
