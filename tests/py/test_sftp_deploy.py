import importlib.util
import stat
import sys
import tempfile
import types
import unittest
from pathlib import Path
from unittest.mock import MagicMock, patch

import requests.exceptions


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.sftp_deploy_state import SlotState  # noqa: E402


ACTIVE_STATE_INI = "[state]\napp = a\nvendor = a\n\n"
CHECKSUM = "abc123def456abcd"
STATE_FILE = ".deploy-state.ini"


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
    def __init__(self):
        self.texts = {}
        self.files = []
        self._file_contents = {}
        self._file_exists_set = set()

    def set_file(self, path, content):
        self._file_contents[path] = content
        self._file_exists_set.add(path)

    def read_file(self, path):
        return self._file_contents.get(path, "")

    def put_text(self, path, content):
        self.texts[path] = content

    def put_file(self, local_path, path):
        self.files.append((str(local_path), path))

    def file_exists(self, path):
        return path in self._file_exists_set

    def remove_file(self, path):
        self.texts.pop(path, None)
        self._file_contents.pop(path, None)
        self._file_exists_set.discard(path)

    def ensure_dir(self, _path):
        pass

    def mkdir_p(self, _path):
        return False


class FakeClientFailing(FakeClient):
    def __init__(self, fail_at_call):
        super().__init__()
        self._call_count = 0
        self._fail_at = fail_at_call

    def _check_fail(self):
        self._call_count += 1
        if self._call_count >= self._fail_at:
            raise OSError("SFTP write failed")

    def put_text(self, path, content):
        self._check_fail()
        super().put_text(path, content)

    def put_file(self, local_path, path):
        self._check_fail()
        super().put_file(local_path, path)


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

    def http_reachable(self):
        return True

    def submit(self, task):
        FakeDispatch.submitted.append(task)


class FakeDispatchUnreachable:
    def __init__(self, cfg, logger=None):
        pass

    def http_reachable(self):
        return False

    def submit(self, _task):
        raise AssertionError("submit darf bei nicht-erreichbar nicht aufgerufen werden")


class FakeDispatchHttpError:
    def __init__(self, cfg, logger=None):
        pass

    def http_reachable(self):
        return True

    def submit(self, _task):
        raise requests.exceptions.HTTPError(response=None)


def make_stubbed_deploy(module, run_id="run-1"):
    deploy = module.SftpDeploy({}, run_id, logger=lambda _: None)
    deploy.client = FakeClient()
    deploy.upload_app_tree = lambda _tree: None
    deploy.upload_static_entry_files = lambda: None
    deploy.publish_switch = lambda _target: None
    deploy.migrate_tokens = lambda _a, _b: None
    deploy.dispatch_switch = lambda _target, _active: None
    return deploy


def make_scenario_deploy(module, state_ini=""):
    vendor_uploads = []
    deploy = module.SftpDeploy({}, "run-1", logger=lambda _: None)
    client = FakeClient()
    if state_ini:
        client.set_file(STATE_FILE, state_ini)
        client.set_file("vendor-a/.meta", CHECKSUM)
        client.set_file("app-a/.deploy-run", "run-prev")
    deploy.client = client
    deploy.upload_app_tree = lambda _: None
    deploy.upload_static_entry_files = lambda: None
    deploy.migrate_tokens = lambda _a, _b: None
    deploy.upload_vendor_dir = lambda slot: vendor_uploads.append(slot)
    return deploy, vendor_uploads


class DispatchSwitchTest(unittest.TestCase):
    ACTIVE = SlotState("a", "a")
    TARGET = SlotState("b", "a")

    def setUp(self):
        FakeDispatch.submitted = []

    def _make_deploy(self, module):
        deploy = module.SftpDeploy({}, "run-42", logger=lambda _: None)
        client = FakeClient()
        client.set_file("vendor-a/.meta", CHECKSUM)
        client.set_file("app-a/.deploy-run", "run-prev")
        deploy.client = client
        return deploy

    def test_submits_task_when_system_valid(self):
        module = load_sftp_deploy_module()
        deploy = self._make_deploy(module)
        with (
            patch.object(module, "TaskDispatch", FakeDispatch),
            patch.object(module, "vendor_checksum", return_value=CHECKSUM),
        ):
            deploy.dispatch_switch(self.TARGET, self.ACTIVE)
        self.assertEqual(len(FakeDispatch.submitted), 1)
        task = FakeDispatch.submitted[0]
        self.assertEqual(task.type, "deploy_switch")
        self.assertEqual(task.params["app"], "b")
        self.assertEqual(task.params["vendor"], "a")
        self.assertEqual(task.params["run_id"], "run-42")
        self.assertNotIn(STATE_FILE, deploy.client.texts)

    def test_falls_back_to_sftp_when_http_unreachable(self):
        module = load_sftp_deploy_module()
        deploy = self._make_deploy(module)
        with (
            patch.object(module, "TaskDispatch", FakeDispatchUnreachable),
            patch.object(module, "vendor_checksum", return_value=CHECKSUM),
        ):
            deploy.dispatch_switch(self.TARGET, self.ACTIVE)
        self.assertIn(STATE_FILE, deploy.client.texts)
        self.assertEqual(len(FakeDispatch.submitted), 0)

    def test_raises_on_http_error_from_submit(self):
        module = load_sftp_deploy_module()
        deploy = self._make_deploy(module)
        with (
            patch.object(module, "TaskDispatch", FakeDispatchHttpError),
            patch.object(module, "vendor_checksum", return_value=CHECKSUM),
        ):
            with self.assertRaises(requests.exceptions.HTTPError):
                deploy.dispatch_switch(self.TARGET, self.ACTIVE)


class SystemInvalidReasonTest(unittest.TestCase):
    ACTIVE = SlotState("a", "a")

    def _make_deploy(self, module, vendor_checksum_ok, app_sentinel_ok):
        deploy = module.SftpDeploy({}, "run-1", logger=lambda _: None)
        client = FakeClient()
        stored = CHECKSUM if vendor_checksum_ok else "wrong_checksum_000"
        client.set_file("vendor-a/.meta", stored)
        if app_sentinel_ok:
            client.set_file("app-a/.deploy-run", "run-prev")
        deploy.client = client
        return deploy

    MATRIX = [
        # (name, vendor_ok, app_ok, http_ok, expect_none, fragment)
        ("all_ok",           True,  True,  True,  True,  None),
        ("vendor_mismatch",  False, True,  True,  False, "Vendor-Sentinel"),
        ("no_app_sentinel",  True,  False, True,  False, "App-Sentinel"),
        ("http_down",        True,  True,  False, False, "nicht erreichbar"),
        ("vendor_wins_over_http", False, True, False, False, "Vendor-Sentinel"),
    ]

    def test_matrix(self):
        module = load_sftp_deploy_module()
        for name, vendor_ok, app_ok, http_ok, expect_none, fragment in self.MATRIX:
            with self.subTest(name=name):
                deploy = self._make_deploy(module, vendor_ok, app_ok)
                dispatch_cls = FakeDispatch if http_ok else FakeDispatchUnreachable
                with (
                    patch.object(module, "TaskDispatch", dispatch_cls),
                    patch.object(module, "vendor_checksum", return_value=CHECKSUM),
                ):
                    reason = deploy._system_invalid_reason(self.ACTIVE)
                if expect_none:
                    self.assertIsNone(reason, name)
                else:
                    self.assertIsNotNone(reason, name)
                    self.assertIn(fragment, reason, name)


class UploadSentinelTest(unittest.TestCase):
    def _make_deploy(self, module, staging_dir):
        deploy = module.SftpDeploy({}, "run-1", logger=lambda _: None)
        deploy.client = FakeClient()
        deploy.STAGING_DIR = staging_dir
        return deploy

    def test_vendor_sentinel_written_on_success(self):
        module = load_sftp_deploy_module()
        with tempfile.TemporaryDirectory() as tmp:
            vendor_dir = Path(tmp) / "vendor"
            vendor_dir.mkdir()
            deploy = self._make_deploy(module, Path(tmp))
            with patch.object(
                module, "vendor_checksum", return_value=CHECKSUM
            ):
                deploy.upload_vendor_dir("vendor-b")
        self.assertEqual(
            deploy.client.texts.get("vendor-b/.meta"),
            CHECKSUM,
        )

    def test_vendor_sentinel_absent_on_failure(self):
        module = load_sftp_deploy_module()
        with tempfile.TemporaryDirectory() as tmp:
            vendor_dir = Path(tmp) / "vendor"
            vendor_dir.mkdir()
            (vendor_dir / "autoload.php").write_text("<?php")
            deploy = self._make_deploy(module, Path(tmp))
            with (
                patch.object(
                    module, "vendor_checksum", return_value=CHECKSUM
                ),
                patch.object(
                    deploy, "upload_file", side_effect=OSError("fail")
                ),
            ):
                with self.assertRaises(OSError):
                    deploy.upload_vendor_dir("vendor-b")
        self.assertNotIn("vendor-b/.meta", deploy.client.texts)

    def test_app_sentinel_written_on_success(self):
        module = load_sftp_deploy_module()
        with tempfile.TemporaryDirectory() as tmp:
            deploy = self._make_deploy(module, Path(tmp))
            deploy.upload_app_tree("app-b")
        self.assertEqual(
            deploy.client.texts.get("app-b/.deploy-run"), "run-1"
        )

    def test_app_sentinel_absent_on_failure(self):
        module = load_sftp_deploy_module()
        with tempfile.TemporaryDirectory() as tmp:
            staging = Path(tmp)
            (staging / "index.php").write_text("<?php")
            deploy = self._make_deploy(module, staging)
            with patch.object(
                deploy, "upload_file", side_effect=OSError("fail")
            ):
                with self.assertRaises(OSError):
                    deploy.upload_app_tree("app-b")
        self.assertNotIn("app-b/.deploy-run", deploy.client.texts)


class SftpDeployPathTest(unittest.TestCase):
    def test_deploy_fresh_always_uploads_vendor(self):
        module = load_sftp_deploy_module()
        deploy = make_stubbed_deploy(module)
        vendor_uploads = []
        deploy.upload_vendor_dir = lambda slot: vendor_uploads.append(slot)
        with patch.object(module, "vendor_checksum", return_value=CHECKSUM):
            deploy.deploy_fresh()
        self.assertEqual(len(vendor_uploads), 1)

    def test_deploy_swap_uploads_vendor_when_sentinel_mismatch(self):
        module = load_sftp_deploy_module()
        deploy = make_stubbed_deploy(module)
        deploy.client.set_file("vendor-a/.meta", "old_checksum")
        vendor_uploads = []
        deploy.upload_vendor_dir = lambda slot: vendor_uploads.append(slot)
        with patch.object(module, "vendor_checksum", return_value=CHECKSUM):
            deploy.deploy_swap(SlotState("a", "a"))
        self.assertEqual(len(vendor_uploads), 1)

    def test_deploy_swap_skips_vendor_when_sentinel_matches(self):
        module = load_sftp_deploy_module()
        deploy = make_stubbed_deploy(module)
        deploy.client.set_file("vendor-a/.meta", CHECKSUM)
        vendor_uploads = []
        deploy.upload_vendor_dir = lambda slot: vendor_uploads.append(slot)
        with patch.object(module, "vendor_checksum", return_value=CHECKSUM):
            deploy.deploy_swap(SlotState("a", "a"))
        self.assertEqual(len(vendor_uploads), 0)

    def test_deploy_swap_uploads_vendor_when_sentinel_missing(self):
        module = load_sftp_deploy_module()
        deploy = make_stubbed_deploy(module)
        vendor_uploads = []
        deploy.upload_vendor_dir = lambda slot: vendor_uploads.append(slot)
        with patch.object(module, "vendor_checksum", return_value=CHECKSUM):
            deploy.deploy_swap(SlotState("a", "a"))
        self.assertEqual(len(vendor_uploads), 1)


class SftpDeployScenarioTest(unittest.TestCase):
    def setUp(self):
        FakeDispatch.submitted = []

    def test_deploy_fresh_writes_state_and_uploads_vendor(self):
        module = load_sftp_deploy_module()
        deploy, vendor_uploads = make_scenario_deploy(module, state_ini="")
        with patch.object(module, "vendor_checksum", return_value=CHECKSUM):
            deploy.deploy()
        self.assertIn(STATE_FILE, deploy.client.texts)
        self.assertEqual(len(vendor_uploads), 1)

    def test_deploy_swap_normal_dispatches_task_without_writing_state(self):
        module = load_sftp_deploy_module()
        deploy, vendor_uploads = make_scenario_deploy(module, state_ini=ACTIVE_STATE_INI)
        with (
            patch.object(module, "TaskDispatch", FakeDispatch),
            patch.object(module, "vendor_checksum", return_value=CHECKSUM),
        ):
            deploy.deploy()
        self.assertNotIn(STATE_FILE, deploy.client.texts)
        self.assertEqual(len(FakeDispatch.submitted), 1)
        self.assertEqual(len(vendor_uploads), 0)

    def test_deploy_swap_unreachable_app_writes_state_directly(self):
        module = load_sftp_deploy_module()
        deploy, _vendor_uploads = make_scenario_deploy(module, state_ini=ACTIVE_STATE_INI)
        with (
            patch.object(module, "TaskDispatch", FakeDispatchUnreachable),
            patch.object(module, "vendor_checksum", return_value=CHECKSUM),
        ):
            deploy.deploy()
        self.assertIn(STATE_FILE, deploy.client.texts)


class SftpDeployStaticFilesTest(unittest.TestCase):
    def test_upload_static_entry_files_uploads_runtime_state(self):
        module = load_sftp_deploy_module()
        deploy = module.SftpDeploy(
            {"SFTP_WEBROOT": "public"},
            "run-1",
        )
        deploy.client = FakeClient()
        deploy.upload_static_entry_files()
        paths = [path for _local, path in deploy.client.files]
        self.assertEqual(
            paths,
            [
                "public/.htaccess",
                "public/deploy-state.php",
                "public/index.php",
            ],
        )


class TokenMigrationTest(unittest.TestCase):
    def test_migrate_tokens_copies_token_files(self):
        module = load_sftp_deploy_module()
        deploy = module.SftpDeploy({}, "run-1", logger=lambda _: None)

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
        deploy = module.SftpDeploy({}, "run-1", logger=lambda _: None)

        client = MagicMock()
        client.listdir_attr.side_effect = OSError("No such directory")
        deploy.client = client

        deploy.migrate_tokens("app-a", "app-b")

        client.put_bytes.assert_not_called()


if __name__ == "__main__":
    unittest.main()
