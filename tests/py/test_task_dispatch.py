import io
import sys
import types
import unittest
import urllib.error
from pathlib import Path
from unittest.mock import patch


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))
sys.modules.setdefault("paramiko", types.SimpleNamespace(RejectPolicy=object, SSHClient=object))

from cli.py.task import dispatch  # noqa: E402


class FakeResponse:
    status = 200

    def __enter__(self):
        return self

    def __exit__(self, *_args):
        return False


class TaskDispatchTest(unittest.TestCase):
    def test_http_trigger_uses_registered_task_route(self):
        cfg = {"APP_ROOT_URL": "http://preview-web/"}

        with patch.object(dispatch.urllib.request, "urlopen", return_value=FakeResponse()) as urlopen:
            dispatch.TaskDispatch(cfg)._http_trigger()

        request = urlopen.call_args.args[0]
        self.assertEqual(request.full_url, "http://preview-web/tasks/dispatch")
        self.assertEqual(request.get_method(), "GET")

    def test_http_trigger_skips_missing_root_url(self):
        with patch.object(dispatch.urllib.request, "urlopen") as urlopen:
            dispatch.TaskDispatch({})._http_trigger()

        urlopen.assert_not_called()

    def test_http_trigger_reraises_http_error_after_logging(self):
        cfg = {"APP_ROOT_URL": "http://preview-web/"}
        fake_error = urllib.error.HTTPError(
            "http://preview-web/tasks/dispatch", 500, "Internal Server Error",
            {}, io.BytesIO(b"<b>PHP error</b>"),
        )

        with patch.object(dispatch.urllib.request, "urlopen", side_effect=fake_error):
            with self.assertRaises(urllib.error.HTTPError):
                dispatch.TaskDispatch(cfg)._http_trigger()

    def test_http_trigger_reraises_url_error_after_logging(self):
        cfg = {"APP_ROOT_URL": "http://preview-web/"}
        fake_error = urllib.error.URLError("Connection refused")

        with patch.object(dispatch.urllib.request, "urlopen", side_effect=fake_error):
            with self.assertRaises(urllib.error.URLError):
                dispatch.TaskDispatch(cfg)._http_trigger()

    def test_truncate_shortens_long_text(self):
        long_text = "x" * 400
        result = dispatch._truncate(long_text, limit=300)
        self.assertEqual(len(result), 303)
        self.assertTrue(result.endswith("..."))

    def test_truncate_leaves_short_text_unchanged(self):
        short_text = "kurz"
        self.assertEqual(dispatch._truncate(short_text), short_text)


if __name__ == "__main__":
    unittest.main()
