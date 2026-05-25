import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "src"))

from cli.py.deploy.exceptions import DeployConflictError  # noqa: E402
from cli.py.deploy.machine import DeployMachine  # noqa: E402
from cli.py.deploy.sftp_deploy_state import SlotState  # noqa: E402

DEFAULT_STATE = SlotState("a", "a")


class FakeOps:
    def __init__(
        self,
        fail_at=None,
        smoke=True,
        state=DEFAULT_STATE,
        include_vendor=False,
    ):
        self.fail_at = fail_at
        self.smoke = smoke
        self.state = state
        self.include_vendor = include_vendor
        self.called = []
        self.plans = []
        self.rollback_state = None

    def load_state(self):
        self._call("load_state")
        return self.state

    def should_upload_vendor(self, _active):
        self._call("should_upload_vendor")
        return self.include_vendor

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

    def rollback(self, state):
        self._call("rollback")
        self.rollback_state = state

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


def test_upload_fehler_fuehrt_zu_failed_safe():
    m = DeployMachine()
    m.run(FakeOps(fail_at="upload_app"))
    assert m.current_state == DeployMachine.failed_safe


def test_failed_safe_verhindert_switch():
    ops = FakeOps(fail_at="upload_app")
    m = DeployMachine()
    m.run(ops)
    assert "switch_fresh" not in ops.called
    assert "switch_swap" not in ops.called


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


def test_fresh_deploy_ruft_switch_fresh_auf():
    ops = FakeOps(state=None)
    m = DeployMachine()
    m.run(ops)
    assert "switch_fresh" in ops.called
    assert "switch_swap" not in ops.called


def test_swap_deploy_ruft_switch_swap_auf():
    ops = FakeOps()
    m = DeployMachine()
    m.run(ops)
    assert "switch_swap" in ops.called
    assert "switch_fresh" not in ops.called


def test_fresh_deploy_laedt_vendor_hoch():
    ops = FakeOps(state=None)
    m = DeployMachine()
    m.run(ops)
    assert "upload_vendor" in ops.called
    assert "skip_vendor" not in ops.called


def test_swap_ohne_vendor_ueberspringt_vendor():
    ops = FakeOps(include_vendor=False)
    m = DeployMachine()
    m.run(ops)
    assert "skip_vendor" in ops.called
    assert "upload_vendor" not in ops.called


def test_swap_mit_vendor_laedt_vendor_hoch():
    ops = FakeOps(include_vendor=True)
    m = DeployMachine()
    m.run(ops)
    assert "upload_vendor" in ops.called
    assert "skip_vendor" not in ops.called


def test_fresh_deploy_migriert_keine_tokens():
    ops = FakeOps(state=None)
    m = DeployMachine()
    m.run(ops)
    assert "migrate_tokens" not in ops.called


def test_swap_migriert_tokens():
    ops = FakeOps()
    m = DeployMachine()
    m.run(ops)
    assert "migrate_tokens" in ops.called
