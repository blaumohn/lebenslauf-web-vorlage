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
    def __init__(self):
        self.texts = {}

    def put_text(self, path, content):
        self.texts[path] = content


class FakeDispatch:
    submitted = []

    def __init__(self, cfg):
        self.cfg = cfg

    def submit(self, task):
        self.submitted.append(task)


class SftpDeployTest(unittest.TestCase):
    def setUp(self):
        FakeDispatch.submitted = []

    def test_dispatch_switch_writes_run_markers_and_task(self):
        module = load_sftp_deploy_module()
        deploy = module.SftpDeploy({}, False, "run-42", logger=lambda _message: None)
        deploy.client = FakeClient()
        target = SlotState("b", "a")

        with patch.object(module, "AdminDispatch", FakeDispatch):
            deploy.dispatch_switch(target)

        self.assertEqual(deploy.client.texts["b/.deploy-run"], "run-42")
        self.assertEqual(deploy.client.texts["vendor-a/.deploy-run"], "run-42")
        self.assertEqual(len(FakeDispatch.submitted), 1)

        task = FakeDispatch.submitted[0]
        self.assertEqual(task.type, "deploy_switch")
        self.assertEqual(task.params["app"], "b")
        self.assertEqual(task.params["vendor"], "a")
        self.assertEqual(task.params["run_id"], "run-42")


if __name__ == "__main__":
    unittest.main()
