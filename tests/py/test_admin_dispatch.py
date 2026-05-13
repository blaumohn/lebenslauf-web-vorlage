import sys
import types
import unittest
from pathlib import Path
from unittest.mock import patch


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))
sys.modules.setdefault("paramiko", types.SimpleNamespace(RejectPolicy=object, SSHClient=object))

from cli.py.admin import dispatch  # noqa: E402


class FakeResponse:
    status = 200

    def __enter__(self):
        return self

    def __exit__(self, *_args):
        return False


class AdminDispatchTest(unittest.TestCase):
    def test_http_trigger_uses_registered_admin_route(self):
        cfg = {"APP_ROOT_URL": "http://preview-web/"}

        with patch.object(dispatch.urllib.request, "urlopen", return_value=FakeResponse()) as urlopen:
            dispatch.AdminDispatch(cfg)._http_trigger()

        request = urlopen.call_args.args[0]
        self.assertEqual(request.full_url, "http://preview-web/admin/run")
        self.assertEqual(request.get_method(), "GET")

    def test_http_trigger_skips_missing_root_url(self):
        with patch.object(dispatch.urllib.request, "urlopen") as urlopen:
            dispatch.AdminDispatch({})._http_trigger()

        urlopen.assert_not_called()


if __name__ == "__main__":
    unittest.main()
