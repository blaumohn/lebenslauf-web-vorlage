import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "src"))

from cli.py.deploy.exceptions import DeployConflictError  # noqa: E402
from cli.py.deploy.machine import DeployMachine  # noqa: E402
from cli.py.deploy.slots import SlotMap  # noqa: E402

DEFAULT_STATE = SlotMap.from_labels(app="a", vendor="a")


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

    def load_current_slot_map(self):
        self._call("load_current_slot_map")
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

    def smoke_ok(self) -> None:
        self._call("smoke_ok")
        if not self.smoke:
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
        self.called.append(name)
        if self.fail_at == name:
            raise RuntimeError(f"Fehler: {name}")
        if self.fail_at == f"conflict:{name}":
            raise DeployConflictError(name)


def test_happy_path_endet_in_cleaned_up():
    m = DeployMachine(FakeOps())
    m.run()
    assert m.current_state == DeployMachine.cleaned_up


def test_upload_fehler_fuehrt_zu_deploy_failed():
    m = DeployMachine(FakeOps(fail_at="upload_app"))
    m.run()
    assert m.current_state == DeployMachine.deploy_failed


def test_deploy_failed_verhindert_switch():
    ops = FakeOps(fail_at="upload_app")
    m = DeployMachine(ops)
    m.run()
    assert "switch_fresh" not in ops.called
    assert "switch_swap" not in ops.called


def test_smoke_ok_exception_loest_rollback_aus():
    m = DeployMachine(FakeOps(fail_at="smoke_ok"))
    m.run()
    assert m.current_state == DeployMachine.rolled_back


def test_rollback_fehler_fuehrt_zu_deploy_failed():
    ops = FakeOps(fail_at="rollback_after_switch", smoke=False)
    m = DeployMachine(ops)
    m.run()
    assert m.current_state == DeployMachine.deploy_failed


def test_smoke_fehler_fuehrt_zu_rollback():
    m = DeployMachine(FakeOps(smoke=False))
    m.run()
    assert m.current_state == DeployMachine.rolled_back


def test_rollback_wird_nach_smoke_fehler_aufgerufen():
    ops = FakeOps(smoke=False)
    m = DeployMachine(ops)
    m.run()
    assert "rollback_after_switch" in ops.called
    assert ops.rollback_state == SlotMap.from_labels(app="a", vendor="a")


def test_abort_wird_bei_pre_switch_fehler_aufgerufen():
    ops = FakeOps(fail_at="upload_app")
    m = DeployMachine(ops)
    m.run()
    assert "abort_before_switch" in ops.called
    assert "rollback_after_switch" not in ops.called


def test_history_enthaelt_alle_zwischenphasen():
    m = DeployMachine(FakeOps())
    m.run()
    assert "started" in m.history
    assert "switched" in m.history


def test_fresh_plan_wird_bei_fehlendem_state_erstellt():
    ops = FakeOps(state=None)
    m = DeployMachine(ops)
    m.run()
    plan = ops.plans[0]
    assert plan.active_slot_map is None
    assert plan.target_slot_map == SlotMap.from_labels(
        app="a", vendor="a"
    )


def test_swap_plan_verwendet_vendor_wieder():
    ops = FakeOps(
        state=SlotMap.from_labels(app="a", vendor="a"),
        include_vendor=False,
    )
    m = DeployMachine(ops)
    m.run()
    plan = ops.plans[0]
    assert plan.active_slot_map == SlotMap.from_labels(
        app="a", vendor="a"
    )
    assert plan.target_slot_map == SlotMap.from_labels(
        app="b", vendor="a"
    )


def test_swap_plan_wechselt_vendor_wenn_noetig():
    ops = FakeOps(
        state=SlotMap.from_labels(app="a", vendor="a"),
        include_vendor=True,
    )
    m = DeployMachine(ops)
    m.run()
    plan = ops.plans[0]
    assert plan.active_slot_map == SlotMap.from_labels(
        app="a", vendor="a"
    )
    assert plan.target_slot_map == SlotMap.from_labels(
        app="b", vendor="b"
    )


def test_fresh_deploy_ruft_switch_fresh_auf():
    ops = FakeOps(state=None)
    m = DeployMachine(ops)
    m.run()
    assert "switch_fresh" in ops.called
    assert "switch_swap" not in ops.called


def test_swap_deploy_ruft_switch_swap_auf():
    ops = FakeOps()
    m = DeployMachine(ops)
    m.run()
    assert "switch_swap" in ops.called
    assert "switch_fresh" not in ops.called


def test_fresh_deploy_laedt_vendor_hoch():
    ops = FakeOps(state=None)
    m = DeployMachine(ops)
    m.run()
    assert "upload_vendor" in ops.called
    assert "skip_vendor" not in ops.called


def test_swap_ohne_vendor_ueberspringt_vendor():
    ops = FakeOps(include_vendor=False)
    m = DeployMachine(ops)
    m.run()
    assert "skip_vendor" in ops.called
    assert "upload_vendor" not in ops.called


def test_swap_mit_vendor_laedt_vendor_hoch():
    ops = FakeOps(include_vendor=True)
    m = DeployMachine(ops)
    m.run()
    assert "upload_vendor" in ops.called
    assert "skip_vendor" not in ops.called


def test_fresh_deploy_migriert_keine_tokens():
    ops = FakeOps(state=None)
    m = DeployMachine(ops)
    m.run()
    assert "migrate_tokens" not in ops.called


def test_swap_migriert_tokens():
    ops = FakeOps()
    m = DeployMachine(ops)
    m.run()
    assert "migrate_tokens" in ops.called


def test_idempotenz_ueberspringt_app_upload():
    class IdempotentOps(FakeOps):
        def app_uploaded(self, plan) -> bool:
            self._call("app_uploaded")
            return True

    ops = IdempotentOps()
    m = DeployMachine(ops)
    m.run()
    assert "app_uploaded" in ops.called
    assert "upload_app" not in ops.called
    assert m.current_state == DeployMachine.cleaned_up


def test_idempotenz_ueberspringt_vendor_upload():
    class IdempotentOps(FakeOps):
        def vendor_ready(self, plan) -> bool:
            self._call("vendor_ready")
            return True

    ops = IdempotentOps(state=None)
    m = DeployMachine(ops)
    m.run()
    assert "vendor_ready" in ops.called
    assert "upload_vendor" not in ops.called
    assert m.current_state == DeployMachine.cleaned_up


def test_idempotenz_ueberspringt_switch():
    class IdempotentOps(FakeOps):
        def switched(self, plan) -> bool:
            self._call("switched")
            return True

    ops = IdempotentOps()
    m = DeployMachine(ops)
    m.run()
    assert "switched" in ops.called
    assert "switch_swap" not in ops.called
    assert m.current_state == DeployMachine.cleaned_up


def test_on_error_wird_bei_pre_switch_fehler_gemeldet():
    errors = []
    ops = FakeOps(fail_at="upload_app")
    m = DeployMachine(ops, on_error=errors.append)
    m.run()
    assert len(errors) == 1
    assert isinstance(errors[0], RuntimeError)


def test_on_error_wird_bei_conflict_gemeldet():
    errors = []
    ops = FakeOps(fail_at="conflict:load_current_slot_map")
    m = DeployMachine(ops, on_error=errors.append)
    m.run()
    assert len(errors) == 1
    assert isinstance(errors[0], DeployConflictError)


def test_on_error_meldet_beide_fehler_bei_post_switch_rollback_fehler():
    errors = []
    ops = FakeOps(smoke=False, fail_at="rollback_after_switch")
    m = DeployMachine(ops, on_error=errors.append)
    m.run()
    assert len(errors) == 2
    assert m.current_state == DeployMachine.deploy_failed


def test_on_error_meldet_conflict_in_rollback():
    errors = []
    ops = FakeOps(smoke=False, fail_at="conflict:rollback_after_switch")
    m = DeployMachine(ops, on_error=errors.append)
    m.run()
    assert len(errors) == 2
    assert isinstance(errors[1], DeployConflictError)
    assert m.current_state == DeployMachine.manual_intervention_required
