import importlib.util
import json
import sys
import types
import unittest
from pathlib import Path
from unittest.mock import MagicMock, patch

REPO_ROOT = Path(__file__).resolve().parents[2]
CONTACT_SMOKE_PATH = REPO_ROOT / "tests" / "ci" / "contact_smoke.py"
sys.path.insert(0, str(REPO_ROOT / "src"))
sys.modules.setdefault(
    "paramiko",
    types.SimpleNamespace(RejectPolicy=object, SSHClient=object),
)


def load_contact_smoke_module():
    spec = importlib.util.spec_from_file_location(
        "contact_smoke",
        CONTACT_SMOKE_PATH,
    )
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


contact_smoke = load_contact_smoke_module()


class FakeSftpClient:
    def __init__(self, files):
        self.files = files
        self.read_paths = []

    def read_file(self, rel_path):
        self.read_paths.append(rel_path)
        return self.files.get(rel_path, "")


class ContactSmokeTest(unittest.TestCase):
    def test_extracts_hidden_captcha_id(self):
        html = '<input type="hidden" name="captcha_id" value="abc_123">'

        self.assertEqual(
            contact_smoke.extract_captcha_id(html),
            "abc_123",
        )

    def test_rejects_missing_hidden_captcha_id(self):
        html = '<img src="/captcha.png?id=fallback_123" alt="">'

        self.assertEqual(contact_smoke.extract_captcha_id(html), "")

    def test_reads_captcha_solution_via_sftp(self):
        captcha_id = "abc_123"
        files = {
            "app-a/var/tmp/captcha/abc_123.json": json.dumps(
                {"solution_text": "ABC123"}
            ),
        }
        smoke = contact_smoke.ContactSmoke(
            {"APP_ROOT_URL": "http://ci-web"},
            {"MAIL_TO_EMAIL": "ci-test@ci.invalid"},
        )
        sftp = FakeSftpClient(files)

        solution = smoke.read_captcha_solution(sftp, "a", captcha_id)

        self.assertEqual(solution, "ABC123")
        self.assertEqual(
            sftp.read_paths,
            ["app-a/var/tmp/captcha/abc_123.json"],
        )

    def test_missing_captcha_state_names_expected_sftp_path(self):
        smoke = contact_smoke.ContactSmoke(
            {"APP_ROOT_URL": "http://ci-web"},
            {"MAIL_TO_EMAIL": "ci-test@ci.invalid"},
        )
        sftp = FakeSftpClient({})

        expected = "app-a/var/tmp/captcha"

        with self.assertRaisesRegex(RuntimeError, expected):
            smoke.read_captcha_solution(sftp, "a", "missing_123")

    def test_reads_app_slot_from_state(self):
        from cli.py.deploy.slot_store import HtaccessSlotFile
        files = {
            ".htaccess": HtaccessSlotFile.generate("b"),
            "app-b/public/index.php": (
                "$vendorDir  = dirname(__DIR__, 2) . '/vendor-a';\n"
            ),
        }
        sftp = FakeSftpClient(files)
        smoke = contact_smoke.ContactSmoke(
            {"APP_ROOT_URL": "http://ci-web"},
            {"MAIL_TO_EMAIL": "ci-test@ci.invalid"},
        )

        self.assertEqual(smoke.read_active_app_slot(sftp), "b")

    def test_rejects_invalid_deploy_state(self):
        files = {".htaccess": "RewriteEngine On\n"}
        sftp = FakeSftpClient(files)
        smoke = contact_smoke.ContactSmoke(
            {"APP_ROOT_URL": "http://ci-web"},
            {"MAIL_TO_EMAIL": "ci-test@ci.invalid"},
        )

        expected = "Aktiver App-Slot fehlt"

        with self.assertRaisesRegex(RuntimeError, expected):
            smoke.read_active_app_slot(sftp)

    def test_submits_form_to_deployed_contact_url(self):
        smoke = contact_smoke.ContactSmoke(
            {"APP_ROOT_URL": "http://ci-web/"},
            {"MAIL_TO_EMAIL": "ci-test@ci.invalid"},
        )
        response = MagicMock(status_code=200)

        with patch.object(
            contact_smoke.requests,
            "post",
            return_value=response,
        ) as post:
            smoke.submit_contact_form("abc_123", "ABC123")

        url = post.call_args.args[0]
        data = post.call_args.kwargs["data"]
        self.assertEqual(url, "http://ci-web/contact")
        self.assertEqual(data["captcha_id"], "abc_123")
        self.assertEqual(data["captcha_answer"], "ABC123")


if __name__ == "__main__":
    unittest.main()
