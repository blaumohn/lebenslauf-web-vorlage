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
)
from cli.py.deploy.sftp_deploy_state import SlotState  # noqa: E402

OBSERVE_STEPS = (
    "load_state",
    "both_app_slots_exist",
    "should_upload_vendor",
)

EXECUTE_STEPS = (
    "prepare_target",
    "upload_app",
    "prepare_vendor",
    "migrate_tokens",
    "switch",
)

FAIL_STEPS = (*OBSERVE_STEPS, *EXECUTE_STEPS, "smoke_ok", "cleanup", "rollback")

PHASE_FLOW = (
    "started",
    "state_loaded",
    "target_selected",
    "target_prepared",
    "app_uploaded",
    "vendor_ready",
    "tokens_migrated",
    "switched",
    "verified",
)

TERMINAL_PHASES = {
    DeployMachine.cleaned_up,
    DeployMachine.rolled_back,
    DeployMachine.failed_safe,
    DeployMachine.manual_intervention_required,
}

DEFAULT_STATE = SlotState("a", "a")

STATES = (
    None,
    DEFAULT_STATE,
    SlotState("a", "b"),
    SlotState("b", "a"),
    SlotState("b", "b"),
)


@dataclass(frozen=True)
class DeployScenario:
    state: SlotState | None = SlotState("a", "a")
    both_slots: bool = False
    include_vendor: bool = False
    fail_at: str | None = None
    error_kind: str | None = None
    smoke: bool = True


class ControllableOps:
    def __init__(self, scenario):
        self.scenario = scenario
        self.calls = []
        self.completed = []
        self.plans = []
        self.rollback_state = None
        self.cleanup_plan = None

    def load_state(self):
        self._call("load_state")
        return self.scenario.state

    def both_app_slots_exist(self):
        self._call("both_app_slots_exist")
        return self.scenario.both_slots

    def should_upload_vendor(self, _active):
        self._call("should_upload_vendor")
        return self.scenario.include_vendor

    def prepare_target(self, plan):
        self._call_with_plan("prepare_target", plan)

    def upload_app(self, plan):
        self._call_with_plan("upload_app", plan)

    def prepare_vendor(self, plan):
        self._call_with_plan("prepare_vendor", plan)

    def migrate_tokens(self, plan):
        self._call_with_plan("migrate_tokens", plan)

    def switch(self, plan): self._call_with_plan("switch", plan)

    def smoke_ok(self) -> bool:
        self._call("smoke_ok")
        return self.scenario.smoke

    def rollback(self, state):
        self._call("rollback")
        self.rollback_state = state

    def cleanup(self, plan):
        self._call("cleanup")
        self.cleanup_plan = plan

    def _call_with_plan(self, name, plan):
        self.plans.append(plan)
        self._call(name)

    def _call(self, name):
        self.calls.append(name)
        if self.scenario.fail_at != name:
            self.completed.append(name)
            return
        if self.scenario.error_kind == "conflict":
            raise DeployConflictError(name)
        raise RuntimeError(name)


def expected_plan(scenario):
    state = scenario.state
    if state is None:
        if scenario.both_slots:
            return None
        return (None, DEFAULT_STATE)
    app = other_slot(state.app)
    vendor = state.vendor
    if scenario.include_vendor:
        vendor = other_slot(state.vendor)
    return (state, SlotState(app, vendor))


def other_slot(slot):
    return "b" if slot == "a" else "a"


def plan_selection_calls(scenario):
    if scenario.state is None:
        return ["both_app_slots_exist"]
    return ["should_upload_vendor"]


def expected_calls(scenario):
    calls = ["load_state"]
    if stops_at(scenario, calls):
        return calls
    calls.extend(plan_selection_calls(scenario))
    if stops_at(scenario, calls) or plan_conflicts(scenario):
        return calls
    calls.extend(EXECUTE_STEPS)
    if stops_at(scenario, calls):
        return calls[: terminal_call_index(scenario, calls)]
    calls.append("smoke_ok")
    if stops_at(scenario, calls):
        return calls[: terminal_call_index(scenario, calls)]
    if not scenario.smoke:
        calls.append("rollback")
    else:
        calls.append("cleanup")
    return calls


def terminal_call_index(scenario, calls):
    if scenario.fail_at not in calls:
        return len(calls)
    return calls.index(scenario.fail_at) + 1


def stops_at(scenario, calls):
    return scenario.fail_at in calls


def plan_conflicts(scenario):
    return scenario.state is None and scenario.both_slots


def expected_phase(scenario):
    calls = expected_calls(scenario)
    if scenario.fail_at in calls:
        return expected_error_phase(scenario)
    if plan_conflicts(scenario):
        return DeployMachine.manual_intervention_required
    if scenario.smoke:
        return DeployMachine.cleaned_up
    return DeployMachine.rolled_back


def expected_error_phase(scenario):
    if scenario.error_kind == "conflict":
        return DeployMachine.manual_intervention_required
    return DeployMachine.failed_safe


def make_scenario(
    state=DEFAULT_STATE,
    both_slots=False,
    include_vendor=False,
    fail_at=None,
    error_kind="runtime",
    smoke=True,
):
    kind = None if fail_at is None else error_kind
    return DeployScenario(
        state=state,
        both_slots=both_slots,
        include_vendor=include_vendor,
        fail_at=fail_at,
        error_kind=kind,
        smoke=smoke,
    )


def make_failing_scenario(step, error_kind):
    state = DEFAULT_STATE
    smoke = True
    if step == "both_app_slots_exist":
        state = None
    if step == "rollback":
        smoke = False
    return make_scenario(
        state=state,
        fail_at=step,
        error_kind=error_kind,
        smoke=smoke,
    )


class DeployMachineStateMachine(RuleBasedStateMachine):
    @initialize()
    def init(self):
        self.run_scenario(make_scenario())

    @rule(
        state=st.sampled_from(STATES),
        both_slots=st.booleans(),
        include_vendor=st.booleans(),
        fail_at=st.one_of(st.none(), st.sampled_from(FAIL_STEPS)),
        error_kind=st.sampled_from(("runtime", "conflict")),
        smoke=st.booleans(),
    )
    def run_generated(
        self,
        state,
        both_slots,
        include_vendor,
        fail_at,
        error_kind,
        smoke,
    ):
        scenario = make_scenario(
            state,
            both_slots,
            include_vendor,
            fail_at,
            error_kind,
            smoke,
        )
        self.run_scenario(scenario)

    def run_scenario(self, scenario):
        self.scenario = scenario
        self.ops = ControllableOps(scenario)
        self.machine = DeployMachine()
        self.machine.run(self.ops)

    @invariant()
    def endet_in_erwartetem_terminalzustand(self):
        assert self.machine.current_state in TERMINAL_PHASES
        assert self.machine.current_state == expected_phase(self.scenario)

    @invariant()
    def history_folgt_phasenordnung(self):
        length = len(self.machine.history)
        assert self.machine.history == list(PHASE_FLOW[:length])

    @invariant()
    def calls_stoppen_nach_erster_terminalphase(self):
        assert self.ops.calls == expected_calls(self.scenario)

    @invariant()
    def plan_passt_zu_state_und_vendor(self):
        expected = expected_plan(self.scenario)
        if expected is None or self.ops.plans == []:
            return
        active, target = expected
        for plan in self.ops.plans:
            assert plan.active == active
            assert plan.target == target

    @invariant()
    def switch_nur_nach_vorphasen(self):
        if "switch" not in self.ops.calls:
            return
        required = EXECUTE_STEPS[:-1]
        assert all(step in self.ops.completed for step in required)

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
        if "rollback" in self.ops.completed:
            assert self.ops.rollback_state == self.scenario.state

    @invariant()
    def cleanup_nur_nach_erfolgreichem_smoke(self):
        if "cleanup" not in self.ops.calls:
            return
        assert "smoke_ok" in self.ops.calls
        assert self.scenario.smoke


@pytest.mark.parametrize("step", FAIL_STEPS)
@pytest.mark.parametrize(
    ("error_kind", "expected"),
    [
        ("runtime", DeployMachine.failed_safe),
        ("conflict", DeployMachine.manual_intervention_required),
    ],
)
def test_jeder_schritt_kann_fehlschlagen(step, error_kind, expected):
    scenario = make_failing_scenario(step, error_kind)
    ops = ControllableOps(scenario)
    machine = DeployMachine()

    machine.run(ops)

    assert machine.current_state == expected
    assert ops.calls == expected_calls(scenario)


@pytest.mark.parametrize(
    ("scenario", "active", "target"),
    [
        (make_scenario(state=None), None, SlotState("a", "a")),
        (
            make_scenario(include_vendor=False),
            SlotState("a", "a"),
            SlotState("b", "a"),
        ),
        (
            make_scenario(include_vendor=True),
            SlotState("a", "a"),
            SlotState("b", "b"),
        ),
        (
            make_scenario(
                state=SlotState("b", "b"),
                include_vendor=True,
            ),
            SlotState("b", "b"),
            SlotState("a", "a"),
        ),
    ],
)
def test_machine_entscheidet_deployment_plan(scenario, active, target):
    ops = ControllableOps(scenario)
    machine = DeployMachine()

    machine.run(ops)

    assert ops.plans[0].active == active
    assert ops.plans[0].target == target


DeployMachineTest = DeployMachineStateMachine.TestCase
DeployMachineTest.settings = settings(max_examples=200)
