import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "src"))

from cli.py.deploy.machine import (  # noqa: E402
    DeployConflictError,
    DeployMachine,
)
from cli.py.deploy.sftp_deploy_state import SlotState  # noqa: E402

DEFAULT_STATE = SlotState("a", "a")


class FakeOps:
    def __init__(
        self,
        fail_at=None,
        smoke=True,
        state=DEFAULT_STATE,
        both_slots=False,
        include_vendor=False,
    ):
        self.fail_at = fail_at
        self.smoke = smoke
        self.state = state
        self.both_slots = both_slots
        self.include_vendor = include_vendor
        self.called = []
        self.plans = []
        self.rollback_state = None
        self.cleanup_plan = None

    def load_state(self):
        self._call("load_state")
        return self.state

    def both_app_slots_exist(self):
        self._call("both_app_slots_exist")
        return self.both_slots

    def should_upload_vendor(self, _active):
        self._call("should_upload_vendor")
        return self.include_vendor

    def prepare_target(self, plan):
        self._call_with_plan("prepare_target", plan)

    def upload_app(self, plan):
        self._call_with_plan("upload_app", plan)

    def prepare_vendor(self, plan):
        self._call_with_plan("prepare_vendor", plan)

    def migrate_tokens(self, plan):
        self._call_with_plan("migrate_tokens", plan)

    def switch(self, plan): self._call_with_plan("switch", plan)

    def rollback(self, state):
        self._call("rollback")
        self.rollback_state = state

    def cleanup(self, plan):
        self._call("cleanup")
        self.cleanup_plan = plan

    def smoke_ok(self) -> bool:
        self._call("smoke_ok")
        return self.smoke

    def _call_with_plan(self, name, plan):
        self.plans.append(plan)
        self._call(name)

    def _call(self, name):
        self.called.append(name)
        if self.fail_at == name:
            raise RuntimeError(f"Fehler: {name}")
        if self.fail_at == f"conflict:{name}":
            raise DeployConflictError(name)


def test_happy_path_endet_in_cleaned_up():
    m = DeployMachine()
    m.run(FakeOps())
    assert m.current_state == DeployMachine.cleaned_up


def test_cleanup_wird_nach_erfolg_aufgerufen():
    ops = FakeOps()
    m = DeployMachine()
    m.run(ops)
    assert "cleanup" in ops.called


def test_cleanup_fehler_fuehrt_zu_failed_safe():
    m = DeployMachine()
    m.run(FakeOps(fail_at="cleanup"))
    assert m.current_state == DeployMachine.failed_safe


def test_upload_fehler_fuehrt_zu_failed_safe():
    m = DeployMachine()
    m.run(FakeOps(fail_at="upload_app"))
    assert m.current_state == DeployMachine.failed_safe


def test_failed_safe_verhindert_switch():
    ops = FakeOps(fail_at="upload_app")
    m = DeployMachine()
    m.run(ops)
    assert "switch" not in ops.called


def test_smoke_ok_exception_fuehrt_zu_failed_safe():
    m = DeployMachine()
    m.run(FakeOps(fail_at="smoke_ok"))
    assert m.current_state == DeployMachine.failed_safe


def test_smoke_fehler_fuehrt_zu_rollback():
    m = DeployMachine()
    m.run(FakeOps(smoke=False))
    assert m.current_state == DeployMachine.rolled_back


def test_rollback_wird_nach_smoke_fehler_aufgerufen():
    ops = FakeOps(smoke=False)
    m = DeployMachine()
    m.run(ops)
    assert "rollback" in ops.called
    assert ops.rollback_state == SlotState("a", "a")


def test_konflikt_fuehrt_zu_manual_intervention():
    m = DeployMachine()
    m.run(FakeOps(state=None, both_slots=True))
    assert m.current_state == DeployMachine.manual_intervention_required


def test_history_enthaelt_alle_zwischenphasen():
    m = DeployMachine()
    m.run(FakeOps())
    assert "started" in m.history
    assert "switched" in m.history


def test_fresh_plan_wird_bei_fehlendem_state_erstellt():
    ops = FakeOps(state=None)
    m = DeployMachine()
    m.run(ops)
    plan = ops.plans[0]
    assert plan.active is None
    assert plan.target == SlotState("a", "a")


def test_swap_plan_verwendet_vendor_wieder():
    ops = FakeOps(state=SlotState("a", "a"), include_vendor=False)
    m = DeployMachine()
    m.run(ops)
    plan = ops.plans[0]
    assert plan.active == SlotState("a", "a")
    assert plan.target == SlotState("b", "a")


def test_swap_plan_wechselt_vendor_wenn_noetig():
    ops = FakeOps(state=SlotState("a", "a"), include_vendor=True)
    m = DeployMachine()
    m.run(ops)
    plan = ops.plans[0]
    assert plan.active == SlotState("a", "a")
    assert plan.target == SlotState("b", "b")
