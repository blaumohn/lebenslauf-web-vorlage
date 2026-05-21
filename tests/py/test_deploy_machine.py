import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "src"))

from cli.py.deploy.machine import (  # noqa: E402
    DeployConflictError,
    DeployMachine,
    DeployPhase,
)


class FakeOps:
    def __init__(self, fail_at=None, smoke=True):
        self.fail_at = fail_at
        self.smoke = smoke
        self.called = []

    def load_state(self): self._call("load_state")
    def select_target(self): self._call("select_target")
    def prepare_target(self): self._call("prepare_target")
    def upload_app(self): self._call("upload_app")
    def prepare_vendor(self): self._call("prepare_vendor")
    def migrate_tokens(self): self._call("migrate_tokens")
    def switch(self): self._call("switch")
    def rollback(self): self.called.append("rollback")
    def smoke_ok(self) -> bool: return self.smoke

    def _call(self, name):
        self.called.append(name)
        if self.fail_at == name:
            raise RuntimeError(f"Fehler: {name}")
        if self.fail_at == f"conflict:{name}":
            raise DeployConflictError(name)


def test_happy_path_endet_in_verified():
    m = DeployMachine()
    m.run(FakeOps())
    assert m.phase == DeployPhase.VERIFIED


def test_upload_fehler_fuehrt_zu_failed_safe():
    m = DeployMachine()
    m.run(FakeOps(fail_at="upload_app"))
    assert m.phase == DeployPhase.FAILED_SAFE


def test_failed_safe_verhindert_switch():
    ops = FakeOps(fail_at="upload_app")
    m = DeployMachine()
    m.run(ops)
    assert "switch" not in ops.called


def test_smoke_fehler_fuehrt_zu_rollback():
    m = DeployMachine()
    m.run(FakeOps(smoke=False))
    assert m.phase == DeployPhase.ROLLED_BACK


def test_rollback_wird_nach_smoke_fehler_aufgerufen():
    ops = FakeOps(smoke=False)
    m = DeployMachine()
    m.run(ops)
    assert "rollback" in ops.called


def test_konflikt_fuehrt_zu_manual_intervention():
    m = DeployMachine()
    m.run(FakeOps(fail_at="conflict:load_state"))
    assert m.phase == DeployPhase.MANUAL_INTERVENTION_REQUIRED


def test_history_enthaelt_alle_zwischenphasen():
    m = DeployMachine()
    m.run(FakeOps())
    assert DeployPhase.STARTED in m.history
    assert DeployPhase.SWITCHED in m.history
