import importlib.util
import stat
import sys
import types
import unittest
from pathlib import Path
from unittest.mock import MagicMock, patch

import requests.exceptions


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.sftp_deploy_state import DeployState, SlotState  # noqa: E402


ACTIVE_STATE_INI = "[state]\napp = a\nvendor = a\n\n"


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
    def __init__(self, vendor_slot_valid=True, state_ini=""):
        self.texts = {}
        self.vendor_slot_valid = vendor_slot_valid
        self._state_ini = state_ini

    def read_file(self, _path):
        return self._state_ini

    def put_text(self, path, content):
        self.texts[path] = content

    def file_exists(self, _path):
        return self.vendor_slot_valid

    def ensure_dir(self, _path):
        pass


class FakeTokenFile:
    def __init__(self, data):
        self._data = data

    def __enter__(self):
        return self

    def __exit__(self, *_):
        return False

    def read(self):
        return self._data


class FakeDispatch:
    submitted = []

    def __init__(self, cfg, logger=None):
        self.cfg = cfg

    def submit(self, task):
        self.submitted.append(task)


class FakeDispatchUnreachable:
    def __init__(self, cfg, logger=None):
        pass

    def submit(self, _task):
        raise requests.exceptions.ConnectionError("Connection refused")


class FakeDispatchHttpError:
    def __init__(self, cfg, logger=None):
        pass

    def submit(self, _task):
        raise requests.exceptions.HTTPError(response=None)


def make_stubbed_deploy(module, run_id, composer_lock_changed, vendor_slot_valid=True):
    deploy = module.SftpDeploy({}, run_id, composer_lock_changed, logger=lambda _: None)
    deploy.client = FakeClient(vendor_slot_valid)
    deploy.upload_app_tree = lambda _tree: None
    deploy.upload_static_entry_files = lambda: None
    deploy.publish_switch = lambda _target: None
    deploy.migrate_tokens = lambda _a, _b: None
    deploy.dispatch_switch = lambda _target: None
    return deploy


def make_scenario_deploy(module, state_ini="", vendor_slot_valid=True, composer_lock_changed=False):
    vendor_uploads = []
    deploy = module.SftpDeploy({}, "run-1", composer_lock_changed, logger=lambda _: None)
    deploy.client = FakeClient(vendor_slot_valid=vendor_slot_valid, state_ini=state_ini)
    deploy.upload_app_tree = lambda _: None
    deploy.upload_static_entry_files = lambda: None
    deploy.migrate_tokens = lambda _a, _b: None
    deploy.upload_vendor_dir = lambda slot: vendor_uploads.append(slot)
    return deploy, vendor_uploads


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

    def test_dispatch_switch_falls_back_to_sftp_when_app_unreachable(self):
        module = load_sftp_deploy_module()
        deploy = module.SftpDeploy({}, "run-1", False, logger=lambda _: None)
        deploy.client = FakeClient()
        target = SlotState("b", "a")

        with patch.object(module, "TaskDispatch", FakeDispatchUnreachable):
            deploy.dispatch_switch(target)

        self.assertIn(".deploy-state.ini", deploy.client.texts)

    def test_dispatch_switch_raises_on_http_error(self):
        module = load_sftp_deploy_module()
        deploy = module.SftpDeploy({}, "run-1", False, logger=lambda _: None)
        deploy.client = FakeClient()
        target = SlotState("b", "a")

        with patch.object(module, "TaskDispatch", FakeDispatchHttpError):
            with self.assertRaises(requests.exceptions.HTTPError):
                deploy.dispatch_switch(target)


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


class SftpDeployScenarioTest(unittest.TestCase):
    def setUp(self):
        FakeDispatch.submitted = []

    def test_deploy_fresh_writes_state_and_uploads_vendor(self):
        module = load_sftp_deploy_module()
        deploy, vendor_uploads = make_scenario_deploy(module, state_ini="")

        deploy.deploy()

        self.assertIn(".deploy-state.ini", deploy.client.texts)
        self.assertIn("a/.deploy-run", deploy.client.texts)
        self.assertIn("vendor-a/.deploy-run", deploy.client.texts)
        self.assertEqual(len(vendor_uploads), 1)

    def test_deploy_swap_normal_dispatches_task_without_writing_state(self):
        module = load_sftp_deploy_module()
        deploy, vendor_uploads = make_scenario_deploy(module, state_ini=ACTIVE_STATE_INI)

        with patch.object(module, "TaskDispatch", FakeDispatch):
            deploy.deploy()

        self.assertNotIn(".deploy-state.ini", deploy.client.texts)
        self.assertEqual(len(FakeDispatch.submitted), 1)
        self.assertEqual(len(vendor_uploads), 0)

    def test_deploy_swap_unreachable_app_writes_state_directly(self):
        module = load_sftp_deploy_module()
        deploy, _vendor_uploads = make_scenario_deploy(module, state_ini=ACTIVE_STATE_INI)

        with patch.object(module, "TaskDispatch", FakeDispatchUnreachable):
            deploy.deploy()

        self.assertIn(".deploy-state.ini", deploy.client.texts)


class TokenMigrationTest(unittest.TestCase):
    def test_migrate_tokens_copies_token_files(self):
        module = load_sftp_deploy_module()
        deploy = module.SftpDeploy({}, "run-1", False, logger=lambda _: None)

        entry = MagicMock()
        entry.filename = "tok-abc"
        entry.st_mode = stat.S_IFREG

        client = MagicMock()
        client.listdir_attr.return_value = [entry]
        client.open.return_value = FakeTokenFile(b"token-data")
        deploy.client = client

        deploy.migrate_tokens("app-a", "app-b")

        client.ensure_dir.assert_called_once_with("app-b/var/state/tokens")
        client.put_bytes.assert_called_once_with(
            "app-b/var/state/tokens/tok-abc", b"token-data"
        )

    def test_migrate_tokens_skips_missing_directory(self):
        module = load_sftp_deploy_module()
        deploy = module.SftpDeploy({}, "run-1", False, logger=lambda _: None)

        client = MagicMock()
        client.listdir_attr.side_effect = OSError("No such directory")
        deploy.client = client

        deploy.migrate_tokens("app-a", "app-b")

        client.put_bytes.assert_not_called()


if __name__ == "__main__":
    unittest.main()
