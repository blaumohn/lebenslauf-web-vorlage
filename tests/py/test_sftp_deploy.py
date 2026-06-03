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

from cli.py.deploy.slot_store import (  # noqa: E402
    HtaccessSlotFile,
    SlotStore,
)
from cli.py.deploy.slot_switch import (  # noqa: E402
    SlotPublisher,
    SlotSwitchDispatcher,
)
from cli.py.deploy.slots import SlotMap  # noqa: E402
from cli.py.deploy.token_migrator import (  # noqa: E402
    RuntimeTokenMigrator,
)
from cli.py.deploy.sftp_deploy_uploader import SftpDeployUploader  # noqa: E402

class FakeLogger:
    def __call__(self, _): pass
    def error(self, _): pass


CHECKSUM = "abc123def456abcd"
VENDOR_META = f"[vendor]\nchecksum = {CHECKSUM}\n\n"
STATE_FILE = ".htaccess"
ACTIVE_HTACCESS = HtaccessSlotFile.generate("a")
ACTIVE_BOOTSTRAP = (
    "$vendorDir  = dirname(__DIR__, 2) . '/vendor-a';\n"
)
VENDOR_INJECT_LINE = "$vendorDir  = $appSlot . '/vendor';"


def load_sftp_deploy_module():
    sys.modules.setdefault("paramiko", fake_paramiko_module())
    path = REPO_ROOT / "scripts" / "sftp-deploy.py"
    spec = importlib.util.spec_from_file_location(
        "sftp_deploy_script",
        path,
    )
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
        self.removed_dirs = []
        self._file_contents = {}
        self._file_exists_set = set()

    def set_file(self, path, content):
        self._file_contents[path] = content
        self._file_exists_set.add(path)

    def read_file(self, path):
        return self._file_contents.get(path, "")

    def put_text(self, path, content):
        self.texts[path] = content
        self._file_contents[path] = content
        self._file_exists_set.add(path)

    def put_file(self, local_path, path):
        self.files.append((str(local_path), path))

    def put_bytes(self, path, data):
        self.texts[path] = data

    def file_exists(self, path):
        return path in self._file_exists_set

    def dir_exists(self, _path):
        return False

    def ensure_dir(self, _path):
        pass

    def remove_dir(self, path):
        self.removed_dirs.append(path)

    def mkdir_p(self, _path):
        return False

    def listdir_attr(self, _path):
        raise OSError("leer")


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
        _ = logger
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
        raise AssertionError(
            "submit darf bei nicht-erreichbar nicht laufen"
        )


class FakeDispatchHttpError:
    def __init__(self, cfg, logger=None):
        pass

    def http_reachable(self):
        return True

    def submit(self, _task):
        raise requests.exceptions.HTTPError(response=None)


def write_staging_bootstrap(staging_dir):
    path = Path(staging_dir) / "public" / "index.php"
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(VENDOR_INJECT_LINE, encoding="utf-8")


def make_uploader(client, staging_dir, run_id="run-1"):
    return SftpDeployUploader(
        client,
        Path(staging_dir),
        run_id,
        lambda: CHECKSUM,
        lambda _: None,
    )


class DispatchSwitchTest(unittest.TestCase):
    ACTIVE = SlotMap.from_labels(app="a", vendor="a")
    TARGET = SlotMap.from_labels(app="b", vendor="a")

    def setUp(self):
        FakeDispatch.submitted = []

    def _dispatcher(self, dispatch_cls, client):
        publisher = SlotPublisher(SlotStore(client), lambda _: None)
        module = load_sftp_deploy_module()
        return SlotSwitchDispatcher(
            {},
            "run-42",
            client,
            lambda _: None,
            dispatch_cls,
            module.Task,
            publisher,
        )

    def test_submits_task_when_system_valid(self):
        client = FakeClient()
        client.set_file("app-a/.deploy-run", "run-prev")
        dispatcher = self._dispatcher(FakeDispatch, client)

        dispatcher.dispatch(self.TARGET, self.ACTIVE)

        self.assertEqual(len(FakeDispatch.submitted), 1)
        task = FakeDispatch.submitted[0]
        self.assertEqual(task.type, "deploy_switch")
        self.assertEqual(task.params["app"], "b")
        self.assertEqual(task.params["vendor"], "a")
        self.assertEqual(task.params["pipeline_run_id"], "run-42")
        self.assertNotIn(STATE_FILE, client.texts)

    def test_falls_back_to_sftp_when_http_unreachable(self):
        client = FakeClient()
        client.set_file("app-a/.deploy-run", "run-prev")
        dispatcher = self._dispatcher(FakeDispatchUnreachable, client)

        dispatcher.dispatch(self.TARGET, self.ACTIVE)

        self.assertIn(STATE_FILE, client.texts)
        self.assertEqual(len(FakeDispatch.submitted), 0)

    def test_raises_on_http_error_from_submit(self):
        client = FakeClient()
        client.set_file("app-a/.deploy-run", "run-prev")
        dispatcher = self._dispatcher(FakeDispatchHttpError, client)

        with self.assertRaises(requests.exceptions.HTTPError):
            dispatcher.dispatch(self.TARGET, self.ACTIVE)


class UploadSentinelTest(unittest.TestCase):
    def test_vendor_sentinel_written_on_success(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            vendor_dir = Path(tmp) / "vendor"
            vendor_dir.mkdir()
            make_uploader(client, tmp).upload_vendor_dir("vendor-b")

        self.assertEqual(
            client.texts.get("vendor-b/.meta"),
            VENDOR_META,
        )

    def test_vendor_sentinel_absent_on_failure(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            vendor_dir = Path(tmp) / "vendor"
            vendor_dir.mkdir()
            (vendor_dir / "autoload.php").write_text("<?php")
            uploader = make_uploader(client, tmp)
            with patch.object(
                uploader._tree,
                "upload_file",
                side_effect=OSError("fail"),
            ), self.assertRaises(OSError):
                uploader.upload_vendor_dir("vendor-b")

        self.assertNotIn("vendor-b/.meta", client.texts)

    def test_app_sentinel_written_on_success(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            write_staging_bootstrap(tmp)
            make_uploader(client, tmp).upload_app_tree(
                "app-b",
                "vendor-a",
            )

        self.assertEqual(client.texts.get("app-b/.deploy-run"), "run-1")

    def test_app_sentinel_absent_on_failure(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            write_staging_bootstrap(tmp)
            (Path(tmp) / "index.php").write_text("<?php")
            uploader = make_uploader(client, tmp)
            with patch.object(
                uploader._tree,
                "upload_file",
                side_effect=OSError("fail"),
            ), self.assertRaises(OSError):
                uploader.upload_app_tree("app-b", "vendor-a")

        self.assertNotIn("app-b/.deploy-run", client.texts)


class PrepareSlotTest(unittest.TestCase):
    def test_app_slot_removed_before_upload(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            write_staging_bootstrap(tmp)
            make_uploader(client, tmp).upload_app_tree(
                "app-b",
                "vendor-a",
            )

        self.assertIn("app-b", client.removed_dirs)

    def test_vendor_slot_removed_before_upload(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            vendor_dir = Path(tmp) / "vendor"
            vendor_dir.mkdir()
            make_uploader(client, tmp).upload_vendor_dir("vendor-b")

        self.assertIn("vendor-b", client.removed_dirs)


class SftpDeployScenarioTest(unittest.TestCase):
    def setUp(self):
        FakeDispatch.submitted = []

    def _make_deploy(self, module, active=False):
        deploy = module.SftpDeploy({}, "run-1", logger=FakeLogger())
        client = FakeClient()
        if active:
            client.set_file(".htaccess", ACTIVE_HTACCESS)
            client.set_file(
                "app-a/public/index.php",
                ACTIVE_BOOTSTRAP,
            )
            client.set_file("vendor-a/.meta", VENDOR_META)
            client.set_file("app-a/.deploy-run", "run-prev")
        deploy.client = client
        return deploy, client

    def test_deploy_fresh_writes_slots_and_uploads_vendor(self):
        module = load_sftp_deploy_module()
        deploy, client = self._make_deploy(module)
        uploader = MagicMock()
        deploy._tree_uploader = lambda: uploader

        with patch.object(
            module,
            "vendor_checksum",
            return_value=CHECKSUM,
        ):
            deploy.deploy()

        self.assertIn(STATE_FILE, client.texts)
        uploader.upload_vendor_dir.assert_called_once_with("vendor-a")

    def test_deploy_swap_dispatches_task_without_writing_slots(self):
        module = load_sftp_deploy_module()
        deploy, client = self._make_deploy(module, active=True)
        uploader = MagicMock()
        deploy._tree_uploader = lambda: uploader
        with (
            patch.object(module, "TaskDispatch", FakeDispatch),
            patch.object(
                module,
                "vendor_checksum",
                return_value=CHECKSUM,
            ),
        ):
            deploy.deploy()

        self.assertNotIn(STATE_FILE, client.texts)
        self.assertEqual(len(FakeDispatch.submitted), 1)
        uploader.upload_vendor_dir.assert_not_called()

    def test_deploy_swap_unreachable_app_writes_slots_directly(self):
        module = load_sftp_deploy_module()
        deploy, client = self._make_deploy(module, active=True)
        deploy._tree_uploader = lambda: MagicMock()
        with (
            patch.object(
                module,
                "TaskDispatch",
                FakeDispatchUnreachable,
            ),
            patch.object(
                module,
                "vendor_checksum",
                return_value=CHECKSUM,
            ),
        ):
            deploy.deploy()

        self.assertIn(STATE_FILE, client.texts)


class TokenMigrationTest(unittest.TestCase):
    def test_migrate_tokens_copies_token_files(self):
        entry = MagicMock()
        entry.filename = "tok-abc"
        entry.st_mode = stat.S_IFREG

        client = MagicMock()
        client.listdir_attr.return_value = [entry]
        client.open.return_value = FakeTokenFile(b"token-data")

        RuntimeTokenMigrator(client, lambda _: None).migrate(
            "app-a",
            "app-b",
        )

        client.ensure_dir.assert_called_once_with("app-b/var/state/tokens")
        client.put_bytes.assert_called_once_with(
            "app-b/var/state/tokens/tok-abc",
            b"token-data",
        )

    def test_migrate_tokens_skips_missing_directory(self):
        client = MagicMock()
        client.listdir_attr.side_effect = OSError("No such directory")

        RuntimeTokenMigrator(client, lambda _: None).migrate(
            "app-a",
            "app-b",
        )

        client.put_bytes.assert_not_called()


if __name__ == "__main__":
    unittest.main()
