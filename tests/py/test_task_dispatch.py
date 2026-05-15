import sys
import types
import unittest
from pathlib import Path
from unittest.mock import MagicMock, patch

import requests
import requests.exceptions


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))
sys.modules.setdefault("paramiko", types.SimpleNamespace(RejectPolicy=object, SSHClient=object))

from cli.py.task import dispatch  # noqa: E402


def fake_ok_response(status_code=200):
    resp = MagicMock()
    resp.status_code = status_code
    resp.raise_for_status.return_value = None
    return resp


def fake_http_error(status_code=500, body="Server Error"):
    resp = MagicMock()
    resp.status_code = status_code
    resp.text = body
    exc = requests.exceptions.HTTPError(response=resp)
    return exc


class HttpTriggerTest(unittest.TestCase):
    def test_sends_get_to_task_trigger_path(self):
        cfg = {"APP_ROOT_URL": "http://preview-web/"}

        with patch.object(dispatch.requests, "get", return_value=fake_ok_response()) as mock_get:
            dispatch.TaskDispatch(cfg)._http_trigger()

        mock_get.assert_called_once_with("http://preview-web/tasks/dispatch", timeout=10)

    def test_adds_https_scheme_when_missing(self):
        cfg = {"APP_ROOT_URL": "s1094367114.online.de"}

        with patch.object(dispatch.requests, "get", return_value=fake_ok_response()) as mock_get:
            dispatch.TaskDispatch(cfg)._http_trigger()

        mock_get.assert_called_once_with(
            "https://s1094367114.online.de/tasks/dispatch", timeout=10
        )

    def test_strips_trailing_slash_before_path(self):
        cfg = {"APP_ROOT_URL": "http://preview-web"}

        with patch.object(dispatch.requests, "get", return_value=fake_ok_response()) as mock_get:
            dispatch.TaskDispatch(cfg)._http_trigger()

        url = mock_get.call_args.args[0]
        self.assertFalse(url.count("//") > 1, "Doppelter Schrägstrich im Pfad")
        self.assertEqual(url, "http://preview-web/tasks/dispatch")

    def test_skips_trigger_when_root_url_empty(self):
        with patch.object(dispatch.requests, "get") as mock_get:
            dispatch.TaskDispatch({})._http_trigger()

        mock_get.assert_not_called()

    def test_skips_trigger_when_root_url_missing(self):
        cfg = {"OTHER_KEY": "value"}

        with patch.object(dispatch.requests, "get") as mock_get:
            dispatch.TaskDispatch(cfg)._http_trigger()

        mock_get.assert_not_called()

    def test_reraises_http_error_after_logging(self):
        cfg = {"APP_ROOT_URL": "http://preview-web/"}
        exc = fake_http_error(500, "<b>PHP error</b>")
        mock_resp = MagicMock()
        mock_resp.raise_for_status.side_effect = exc

        with patch.object(dispatch.requests, "get", return_value=mock_resp):
            with self.assertRaises(requests.exceptions.HTTPError):
                dispatch.TaskDispatch(cfg)._http_trigger()

    def test_reraises_connection_error_after_logging(self):
        cfg = {"APP_ROOT_URL": "http://preview-web/"}

        with patch.object(dispatch.requests, "get", side_effect=requests.exceptions.ConnectionError("refused")):
            with self.assertRaises(requests.exceptions.RequestException):
                dispatch.TaskDispatch(cfg)._http_trigger()

    def test_reraises_timeout_after_logging(self):
        cfg = {"APP_ROOT_URL": "http://preview-web/"}

        with patch.object(dispatch.requests, "get", side_effect=requests.exceptions.Timeout("timed out")):
            with self.assertRaises(requests.exceptions.RequestException):
                dispatch.TaskDispatch(cfg)._http_trigger()

    def test_keeps_http_scheme(self):
        self.assertEqual(dispatch._with_scheme("http://example.com"), "http://example.com")

    def test_keeps_https_scheme(self):
        self.assertEqual(dispatch._with_scheme("https://example.com"), "https://example.com")

    def test_adds_https_to_bare_host(self):
        self.assertEqual(dispatch._with_scheme("example.com"), "https://example.com")

    def test_adds_https_to_host_with_path(self):
        self.assertEqual(dispatch._with_scheme("example.com/sub"), "https://example.com/sub")


class TruncateTest(unittest.TestCase):
    def test_shortens_long_text(self):
        long_text = "x" * 400
        result = dispatch._truncate(long_text, limit=300)
        self.assertEqual(len(result), 303)
        self.assertTrue(result.endswith("..."))

    def test_leaves_short_text_unchanged(self):
        self.assertEqual(dispatch._truncate("kurz"), "kurz")

    def test_leaves_exact_limit_unchanged(self):
        text = "x" * 300
        self.assertEqual(dispatch._truncate(text, limit=300), text)


if __name__ == "__main__":
    unittest.main()
