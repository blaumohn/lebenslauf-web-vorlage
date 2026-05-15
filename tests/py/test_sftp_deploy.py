import importlib.util
import sys
import types
import unittest
from pathlib import Path
from unittest.mock import patch


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.sftp_deploy_state import SlotState  # noqa: E402


def load_sftp_deploy_module():
    sys.modules.setdefault("paramiko", fake_paramiko_module())
    path = REPO_ROOT / "scripts" / "sftp-deploy.py"
    spec = importlib.util.spec_from_file_location("sftp_deploy_script", path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def fake_paramiko_module():
    return types.SimpleNamespace(
        RejectPolicy=object,
        SSHClient=object,
    )


class FakeClient:
    def __init__(self, vendor_slot_valid=True):
        self.texts = {}
        self.vendor_slot_valid = vendor_slot_valid

    def put_text(self, path, content):
        self.texts[path] = content

    def file_exists(self, _path):
        return self.vendor_slot_valid


class FakeDispatch:
    submitted = []

    def __init__(self, cfg):
        self.cfg = cfg

    def submit(self, task):
        self.submitted.append(task)


def make_stubbed_deploy(module, run_id, composer_lock_changed, vendor_slot_valid=True):
    deploy = module.SftpDeploy({}, run_id, composer_lock_changed, logger=lambda _: None)
    deploy.client = FakeClient(vendor_slot_valid)
    deploy.upload_app_tree = lambda _tree: None
    deploy.upload_static_entry_files = lambda: None
    deploy.publish_switch = lambda _target: None
    deploy.migrate_tokens = lambda _a, _b: None
    deploy.dispatch_switch = lambda _target: None
    return deploy


class SftpDeployTest(unittest.TestCase):
    def setUp(self):
        FakeDispatch.submitted = []

    def test_dispatch_switch_writes_run_markers_and_task(self):
        module = load_sftp_deploy_module()
        deploy = module.SftpDeploy({}, "run-42", False, logger=lambda _message: None)
        deploy.client = FakeClient()
        target = SlotState("b", "a")

        with patch.object(module, "TaskDispatch", FakeDispatch):
            deploy.dispatch_switch(target)

        self.assertEqual(deploy.client.texts["b/.deploy-run"], "run-42")
        self.assertEqual(deploy.client.texts["vendor-a/.deploy-run"], "run-42")
        self.assertEqual(len(FakeDispatch.submitted), 1)

        task = FakeDispatch.submitted[0]
        self.assertEqual(task.type, "deploy_switch")
        self.assertEqual(task.params["app"], "b")
        self.assertEqual(task.params["vendor"], "a")
        self.assertEqual(task.params["run_id"], "run-42")


class SftpDeployPathTest(unittest.TestCase):
    def test_deploy_fresh_always_uploads_vendor(self):
        module = load_sftp_deploy_module()
        deploy = make_stubbed_deploy(module, "run-1", False)
        vendor_uploads = []
        deploy.upload_vendor_dir = lambda slot: vendor_uploads.append(slot)

        deploy.deploy_fresh()

        self.assertEqual(len(vendor_uploads), 1)

    def test_deploy_swap_uploads_vendor_when_lock_changed(self):
        module = load_sftp_deploy_module()
        deploy = make_stubbed_deploy(module, "run-1", True)
        vendor_uploads = []
        deploy.upload_vendor_dir = lambda slot: vendor_uploads.append(slot)

        deploy.deploy_swap(SlotState("a", "a"))

        self.assertEqual(len(vendor_uploads), 1)

    def test_deploy_swap_skips_vendor_when_lock_unchanged(self):
        module = load_sftp_deploy_module()
        deploy = make_stubbed_deploy(module, "run-1", False, vendor_slot_valid=True)
        vendor_uploads = []
        deploy.upload_vendor_dir = lambda slot: vendor_uploads.append(slot)

        deploy.deploy_swap(SlotState("a", "a"))

        self.assertEqual(len(vendor_uploads), 0)

    def test_deploy_swap_uploads_vendor_when_slot_invalid(self):
        module = load_sftp_deploy_module()
        deploy = make_stubbed_deploy(module, "run-1", False, vendor_slot_valid=False)
        vendor_uploads = []
        deploy.upload_vendor_dir = lambda slot: vendor_uploads.append(slot)

        deploy.deploy_swap(SlotState("a", "a"))

        self.assertEqual(len(vendor_uploads), 1)


if __name__ == "__main__":
    unittest.main()
