"""
Recovery-Matrix für den Deploy-Ablauf (J01-147).

Szenarien:
  1a. Upload schlägt fehl → State unverändert (FAILED_SAFE)
  1b. Folgelauf nach 1a → Zielslot bereinigt, Deploy erfolgreich
  2.  Switch ok, Smoke fehlgeschlagen → ROLLED_BACK        [Schritt 6]
  3.  State fehlt, beide Slots vorhanden → MANUAL_REQUIRED  [Schritt 5]
"""
import importlib.util
import sys
import tempfile
import types
import unittest
from contextlib import ExitStack
from pathlib import Path
from unittest.mock import patch

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.machine import DeployMachine  # noqa: E402
from cli.py.deploy.slot_store import HtaccessSlotFile  # noqa: E402

class FakeLogger:
    def __call__(self, _): pass
    def error(self, _): pass


CHECKSUM = "abc123def456abcd"
STATE_FILE = ".htaccess"
ACTIVE_HTACCESS = HtaccessSlotFile.generate("a")
ACTIVE_BOOTSTRAP = (
    "$vendorDir  = dirname(__DIR__, 2) . '/vendor-a';\n"
)
VENDOR_META = f"[vendor]\nchecksum = {CHECKSUM}\n\n"

def enter_common_patches(stack):
    stack.enter_context(patch(
            "cli.py.deploy.sftp_deploy_uploader.SftpDeployUploader"
            "._inject_vendor_dir",
            return_value=None,
        )
    )
    stack.enter_context(patch(
            "cli.py.deploy.token_migrator.RuntimeTokenMigrator"
            ".migrate",
            return_value=None,
        )
    )


def load_module():
    sys.modules.setdefault("paramiko", types.SimpleNamespace(
        RejectPolicy=object, SSHClient=object,
    ))
    path = REPO_ROOT / "scripts" / "sftp-deploy.py"
    spec = importlib.util.spec_from_file_location(
        "sftp_deploy_script",
        path,
    )
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


class FakeClient:
    def __init__(self):
        self.texts = {}
        self.files = []
        self.removed_dirs = []
        self._contents = {}
        self._exists = set()

    def set_file(self, path, content):
        self._contents[path] = content
        self._exists.add(path)

    def read_file(self, path):
        return self._contents.get(path, "")

    def file_exists(self, path):
        return path in self._exists

    def dir_exists(self, _path):
        return False

    def put_text(self, path, content):
        self.texts[path] = content
        self._contents[path] = content
        self._exists.add(path)

    def put_file(self, _local, path):
        self.files.append(path)

    def put_bytes(self, _path, _data):
        pass

    def append_line(self, path, line):
        existing = self._contents.get(path, "")
        self._contents[path] = existing + line + "\n"

    def ensure_dir(self, _path):
        pass

    def remove_dir(self, path):
        self.removed_dirs.append(path)

    def mkdir_p(self, _path):
        return False

    def listdir_attr(self, _path):
        raise OSError("leer")


def make_swap_deploy(module, run_id="run-2"):
    deploy = module.SftpDeploy(
        {},
        {"CONTENT_LANGS": "de"},
        run_id,
        logger=FakeLogger(),
    )
    client = FakeClient()
    client.set_file(".htaccess", ACTIVE_HTACCESS)
    client.set_file("app-a/public/index.php", ACTIVE_BOOTSTRAP)
    client.set_file("vendor-a/.meta", VENDOR_META)
    client.set_file("app-a/.deploy-run", "run-prev")
    deploy.client = client
    return deploy, client


class Szenario1aTest(unittest.TestCase):
    """
    start:  active_app=app-a, active_vendor=vendor-a
    run_1:  fail_at=upload_app
    expect: state unverändert, kein .deploy-run auf app-b
    """

    def setUp(self):
        self.module = load_module()

    def test_state_unveraendert_wenn_upload_fehlschlaegt(self):
        with tempfile.TemporaryDirectory() as tmp:
            deploy, client = make_swap_deploy(self.module)
            deploy.STAGING_DIR = Path(tmp)
            (Path(tmp) / "index.php").write_text("<?php")
            with ExitStack() as stack:
                stack.enter_context(patch.object(
                    self.module,
                    "vendor_checksum",
                    return_value=CHECKSUM,
                ))
                enter_common_patches(stack)
                stack.enter_context(patch(
                    "cli.py.deploy.tree_uploader.SftpTreeUploader"
                    ".upload_file",
                    side_effect=OSError("fail"),
                ))
                with self.assertRaises(RuntimeError):
                    deploy.deploy()
        self.assertNotIn(STATE_FILE, client.texts)

    def test_app_sentinel_fehlt_nach_upload_fehler(self):
        with tempfile.TemporaryDirectory() as tmp:
            deploy, client = make_swap_deploy(self.module)
            deploy.STAGING_DIR = Path(tmp)
            (Path(tmp) / "index.php").write_text("<?php")
            with ExitStack() as stack:
                stack.enter_context(patch.object(
                    self.module,
                    "vendor_checksum",
                    return_value=CHECKSUM,
                ))
                enter_common_patches(stack)
                stack.enter_context(patch(
                    "cli.py.deploy.tree_uploader.SftpTreeUploader"
                    ".upload_file",
                    side_effect=OSError("fail"),
                ))
                with self.assertRaises(RuntimeError):
                    deploy.deploy()
        self.assertNotIn("app-b/.deploy-run", client.texts)


class Szenario1bTest(unittest.TestCase):
    """
    start:  active_app=app-a (State unverändert nach run_1-Fehler)
            app-b: partiell, kein .deploy-run
    run_2:  kein Fehler
    expect: app-b bereinigt vor Upload, .deploy-run auf app-b
    """

    def setUp(self):
        self.module = load_module()

    def test_zielslot_bereinigt_vor_upload(self):
        with tempfile.TemporaryDirectory() as tmp:
            deploy, client = make_swap_deploy(self.module)
            deploy.STAGING_DIR = Path(tmp)
            with ExitStack() as stack:
                stack.enter_context(patch.object(
                    self.module,
                    "vendor_checksum",
                    return_value=CHECKSUM,
                ))
                enter_common_patches(stack)
                stack.enter_context(patch(
                    "cli.py.deploy.slot_switch.SlotSwitchDispatcher"
                    ".dispatch",
                    return_value=None,
                ))
                deploy.deploy()
        self.assertIn("app-b", client.removed_dirs)

    def test_app_sentinel_nach_erfolgreichem_folgelauf(self):
        with tempfile.TemporaryDirectory() as tmp:
            deploy, client = make_swap_deploy(self.module)
            deploy.STAGING_DIR = Path(tmp)
            with ExitStack() as stack:
                stack.enter_context(patch.object(
                    self.module,
                    "vendor_checksum",
                    return_value=CHECKSUM,
                ))
                enter_common_patches(stack)
                stack.enter_context(patch(
                    "cli.py.deploy.slot_switch.SlotSwitchDispatcher"
                    ".dispatch",
                    return_value=None,
                ))
                deploy.deploy()
        self.assertEqual(client.texts.get("app-b/.deploy-run"), "run-2")


class Szenario2Test(unittest.TestCase):
    """
    run:    switch=success, post_switch_smoke=fail
    expect: ROLLED_BACK, active_app=app-a wiederhergestellt
    """

    def setUp(self):
        self.module = load_module()

    def _make_deploy(self, tmp):
        deploy, client = make_swap_deploy(self.module)
        deploy.STAGING_DIR = Path(tmp)
        return deploy, client

    def test_smoke_fehler_loest_rollback_aus(self):
        with tempfile.TemporaryDirectory() as tmp:
            deploy, _ = self._make_deploy(tmp)
            with ExitStack() as stack:
                stack.enter_context(patch.object(
                    self.module,
                    "vendor_checksum",
                    return_value=CHECKSUM,
                ))
                stack.enter_context(patch.object(
                    self.module,
                    "smoke_check",
                    side_effect=RuntimeError("Smoke fehlgeschlagen"),
                ))
                enter_common_patches(stack)
                stack.enter_context(patch(
                    "cli.py.deploy.slot_switch.SlotSwitchDispatcher"
                    ".dispatch",
                    return_value=None,
                ))
                deploy.deploy()
        self.assertEqual(deploy.deploy_phase, DeployMachine.rolled_back)

    def test_rollback_stellt_alten_state_wieder_her(self):
        with tempfile.TemporaryDirectory() as tmp:
            deploy, client = self._make_deploy(tmp)
            with ExitStack() as stack:
                stack.enter_context(patch.object(
                    self.module,
                    "vendor_checksum",
                    return_value=CHECKSUM,
                ))
                stack.enter_context(patch.object(
                    self.module,
                    "smoke_check",
                    side_effect=RuntimeError("Smoke fehlgeschlagen"),
                ))
                enter_common_patches(stack)
                stack.enter_context(patch(
                    "cli.py.deploy.slot_switch.SlotSwitchDispatcher"
                    ".dispatch",
                    return_value=None,
                ))
                deploy.deploy()
        written = client.texts.get(STATE_FILE, "")
        self.assertIn("/app-a/", written)


class Szenario3Test(unittest.TestCase):
    """
    run:    Fresh-Deploy, switch=success, post_switch_smoke=fail
    expect: MANUAL_INTERVENTION — kein vorheriger Slot für Rollback
    """

    def setUp(self):
        self.module = load_module()

    def _make_fresh_deploy(self, tmp):
        deploy = self.module.SftpDeploy(
            {},
            {"CONTENT_LANGS": "de"},
            "run-1",
            logger=FakeLogger(),
        )
        client = FakeClient()
        deploy.client = client
        deploy.STAGING_DIR = Path(tmp)
        return deploy, client

    def test_fresh_smoke_fehler_loest_manual_aus(self):
        with tempfile.TemporaryDirectory() as tmp:
            deploy, _ = self._make_fresh_deploy(tmp)
            with (
                patch.object(
                    self.module, "vendor_checksum",
                    return_value=CHECKSUM,
                ),
                patch.object(
                    self.module, "smoke_check",
                    side_effect=RuntimeError("Smoke fehlgeschlagen"),
                ),
                patch(
                    "cli.py.deploy.sftp_deploy_uploader.SftpDeployUploader"
                    "._inject_vendor_dir",
                    return_value=None,
                ),
                patch(
                    "cli.py.deploy.sftp_deploy_uploader.SftpDeployUploader"
                    ".upload_vendor_dir",
                    return_value=None,
                ),
                patch(
                    "cli.py.deploy.slot_switch.SlotPublisher"
                    ".publish",
                    return_value=None,
                ),
                self.assertRaises(RuntimeError),
            ):
                deploy.deploy()
        self.assertEqual(
            deploy.deploy_phase,
            DeployMachine.manual_intervention_required,
        )


if __name__ == "__main__":
    unittest.main()
