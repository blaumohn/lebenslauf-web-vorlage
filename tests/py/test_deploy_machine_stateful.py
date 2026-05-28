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

from cli.py.deploy.exceptions import DeployConflictError  # noqa: E402
from cli.py.deploy.machine import DeployMachine  # noqa: E402
from cli.py.deploy.slots import SlotMap  # noqa: E402

OBSERVE_STEPS = (
    "load_current_slot_map",
    "should_upload_vendor",
    "app_uploaded",
    "vendor_ready",
    "switched",
)

FAIL_STEPS = (
    *OBSERVE_STEPS,
    "prepare_target",
    "upload_app",
    "upload_vendor",
    "skip_vendor",
    "migrate_tokens",
    "switch_fresh",
    "switch_swap",
    "smoke_ok",
    "rollback_after_switch",
)

_PHASE_FLOW_SUFFIX = (
    "target_prepared",
    "app_uploaded",
    "vendor_ready",
    "tokens_migrated",
    "switched",
    "verified",
)

POST_SWITCH_STEPS = {"smoke_ok"}


def _select_phase_id(scenario):
    if scenario.state is None:
        return "fresh_selected"
    if scenario.include_vendor:
        return "swap_vendor_update_selected"
    return "swap_selected"


def expected_phase_flow(scenario):
    return (
        "started",
        "state_loaded",
        _select_phase_id(scenario),
        *_PHASE_FLOW_SUFFIX,
    )


TERMINAL_PHASES = {
    DeployMachine.cleaned_up,
    DeployMachine.rolled_back,
    DeployMachine.failed_safe,
    DeployMachine.manual_intervention_required,
}

DEFAULT_STATE = SlotMap.from_labels(app="a", vendor="a")

STATES = (
    None,
    DEFAULT_STATE,
    SlotMap.from_labels(app="a", vendor="b"),
    SlotMap.from_labels(app="b", vendor="a"),
    SlotMap.from_labels(app="b", vendor="b"),
)


@dataclass(frozen=True)
class DeployScenario:
    state: SlotMap | None = DEFAULT_STATE
    include_vendor: bool = False
    fail_at: str | None = None
    error_kind: str | None = None
    smoke: bool = True


def expected_execute_steps(scenario):
    steps = ["prepare_target", "app_uploaded", "upload_app"]
    if scenario.state is None or scenario.include_vendor:
        steps.extend(["vendor_ready", "upload_vendor"])
    else:
        steps.append("skip_vendor")
    if scenario.state is not None:
        steps.append("migrate_tokens")
    steps.append("switched")
    if scenario.state is None:
        steps.append("switch_fresh")
    else:
        steps.append("switch_swap")
    return steps


class ControllableOps:
    def __init__(self, scenario):
        self.scenario = scenario
        self.calls = []
        self.completed = []
        self.plans = []
        self.rollback_state = None

    def load_current_slot_map(self):
        self._call("load_current_slot_map")
        return self.scenario.state

    def should_upload_vendor(self, _active):
        self._call("should_upload_vendor")
        return self.scenario.include_vendor

    def prepare_target(self, plan):
        self._call_with_plan("prepare_target", plan)

    def upload_app(self, plan):
        self._call_with_plan("upload_app", plan)

    def upload_vendor(self, plan):
        self._call_with_plan("upload_vendor", plan)

    def skip_vendor(self, plan):
        self._call_with_plan("skip_vendor", plan)

    def migrate_tokens(self, plan):
        self._call_with_plan("migrate_tokens", plan)

    def switch_fresh(self, plan):
        self._call_with_plan("switch_fresh", plan)

    def switch_swap(self, plan):
        self._call_with_plan("switch_swap", plan)

    def smoke_ok(self) -> None:
        self._call("smoke_ok")
        if not self.scenario.smoke:
            raise RuntimeError("Smoke-Check fehlgeschlagen")

    def app_uploaded(self, plan) -> bool:
        self._call("app_uploaded")
        return False

    def vendor_ready(self, plan) -> bool:
        self._call("vendor_ready")
        return False

    def switched(self, plan) -> bool:
        self._call("switched")
        return False

    def abort_before_switch(self, plan, state) -> None:
        self._call("abort_before_switch")

    def rollback_after_switch(self, plan, state) -> None:
        self._call("rollback_after_switch")
        self.rollback_state = state

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
        return (None, DEFAULT_STATE)
    app = other_slot(state.app.label)
    vendor = state.vendor.label
    if scenario.include_vendor:
        vendor = other_slot(state.vendor.label)
    return (state, SlotMap.from_labels(app=app, vendor=vendor))


def other_slot(slot):
    return "b" if slot == "a" else "a"


def plan_selection_calls(scenario):
    if scenario.state is None:
        return []
    return ["should_upload_vendor"]


def _pre_switch_result(scenario, calls):
    idx = terminal_call_index(scenario, calls)
    base = calls[:idx]
    if scenario.error_kind == "conflict":
        return base
    return base + ["abort_before_switch"]


def _post_switch_result(scenario, calls):
    idx = terminal_call_index(scenario, calls)
    base = calls[:idx]
    if scenario.error_kind == "conflict":
        return base
    return base + ["rollback_after_switch"]


def expected_calls(scenario):
    calls = ["load_current_slot_map"]
    if stops_at(scenario, calls):
        return _pre_switch_result(scenario, calls)
    calls.extend(plan_selection_calls(scenario))
    if stops_at(scenario, calls):
        return _pre_switch_result(scenario, calls)
    calls.extend(expected_execute_steps(scenario))
    if stops_at(scenario, calls):
        return _pre_switch_result(scenario, calls)
    calls.append("smoke_ok")
    if stops_at(scenario, calls):
        return _post_switch_result(scenario, calls)
    if not scenario.smoke:
        calls.append("rollback_after_switch")
        if stops_at(scenario, calls):
            return calls
    return calls


def terminal_call_index(scenario, calls):
    if scenario.fail_at not in calls:
        return len(calls)
    return calls.index(scenario.fail_at) + 1


def stops_at(scenario, calls):
    return scenario.fail_at in calls


def expected_phase(scenario):
    calls = expected_calls(scenario)
    if scenario.fail_at in calls:
        return expected_error_phase(scenario)
    if scenario.smoke:
        return DeployMachine.cleaned_up
    return DeployMachine.rolled_back


def expected_error_phase(scenario):
    if scenario.error_kind == "conflict":
        return DeployMachine.manual_intervention_required
    if scenario.fail_at in POST_SWITCH_STEPS:
        return DeployMachine.rolled_back
    return DeployMachine.failed_safe


def make_scenario(
    state=DEFAULT_STATE,
    include_vendor=False,
    fail_at=None,
    error_kind="runtime",
    smoke=True,
):
    kind = None if fail_at is None else error_kind
    return DeployScenario(
        state=state,
        include_vendor=include_vendor,
        fail_at=fail_at,
        error_kind=kind,
        smoke=smoke,
    )


def make_failing_scenario(step, error_kind):
    state = DEFAULT_STATE
    include_vendor = False
    smoke = True
    if step in ("upload_vendor", "switch_fresh", "vendor_ready"):
        state = None
    if step == "skip_vendor":
        state = DEFAULT_STATE
        include_vendor = False
    if step == "rollback_after_switch":
        smoke = False
    return make_scenario(
        state=state,
        include_vendor=include_vendor,
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
        include_vendor=st.booleans(),
        fail_at=st.one_of(st.none(), st.sampled_from(FAIL_STEPS)),
        error_kind=st.sampled_from(("runtime", "conflict")),
        smoke=st.booleans(),
    )
    def run_generated(
        self,
        state,
        include_vendor,
        fail_at,
        error_kind,
        smoke,
    ):
        scenario = make_scenario(
            state,
            include_vendor,
            fail_at,
            error_kind,
            smoke,
        )
        self.run_scenario(scenario)

    def run_scenario(self, scenario):
        self.scenario = scenario
        self.ops = ControllableOps(scenario)
        self.machine = DeployMachine(self.ops)
        self.machine.run()

    @invariant()
    def endet_in_erwartetem_terminalzustand(self):
        assert self.machine.current_state in TERMINAL_PHASES
        assert self.machine.current_state == expected_phase(
            self.scenario
        )

    @invariant()
    def history_folgt_phasenordnung(self):
        flow = expected_phase_flow(self.scenario)
        length = len(self.machine.history)
        assert self.machine.history == list(flow[:length])

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
            assert plan.active_slot_map == active
            assert plan.target_slot_map == target

    @invariant()
    def switch_nur_nach_vorphasen(self):
        switch_called = (
            "switch_fresh" in self.ops.calls
            or "switch_swap" in self.ops.calls
        )
        if not switch_called:
            return
        required = expected_execute_steps(self.scenario)[:-1]
        assert all(step in self.ops.completed for step in required)

    @invariant()
    def smoke_nur_nach_erfolgreichem_switch(self):
        if "smoke_ok" not in self.ops.calls:
            return
        assert (
            "switch_fresh" in self.ops.completed
            or "switch_swap" in self.ops.completed
        )

    @invariant()
    def rollback_nur_nach_smoke_problem(self):
        if "rollback_after_switch" not in self.ops.calls:
            return
        assert "smoke_ok" in self.ops.calls
        smoke_failed = not self.scenario.smoke
        smoke_raised = (
            self.scenario.fail_at == "smoke_ok"
            and self.scenario.error_kind != "conflict"
        )
        assert smoke_failed or smoke_raised
        if "rollback_after_switch" in self.ops.completed:
            assert self.ops.rollback_state == self.scenario.state


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
    machine = DeployMachine(ops)
    machine.run()

    if step == "smoke_ok" and error_kind == "runtime":
        assert machine.current_state == DeployMachine.rolled_back
    else:
        assert machine.current_state == expected
    assert ops.calls == expected_calls(scenario)


@pytest.mark.parametrize(
    ("scenario", "active", "target"),
    [
        (
            make_scenario(state=None),
            None,
            SlotMap.from_labels(app="a", vendor="a"),
        ),
        (
            make_scenario(include_vendor=False),
            SlotMap.from_labels(app="a", vendor="a"),
            SlotMap.from_labels(app="b", vendor="a"),
        ),
        (
            make_scenario(include_vendor=True),
            SlotMap.from_labels(app="a", vendor="a"),
            SlotMap.from_labels(app="b", vendor="b"),
        ),
        (
            make_scenario(
                state=SlotMap.from_labels(app="b", vendor="b"),
                include_vendor=True,
            ),
            SlotMap.from_labels(app="b", vendor="b"),
            SlotMap.from_labels(app="a", vendor="a"),
        ),
    ],
)
def test_machine_entscheidet_deployment_plan(scenario, active, target):
    ops = ControllableOps(scenario)
    machine = DeployMachine(ops)
    machine.run()

    assert ops.plans[0].active_slot_map == active
    assert ops.plans[0].target_slot_map == target


DeployMachineTest = DeployMachineStateMachine.TestCase
DeployMachineTest.settings = settings(max_examples=200)
