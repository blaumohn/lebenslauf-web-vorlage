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
from cli.py.task.task import Task  # noqa: E402
from cli.py.deploy.slots import SlotMap  # noqa: E402


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
        cfg = {"APP_ROOT_URL": "http://ci-web/"}

        with patch.object(dispatch.requests, "get", return_value=fake_ok_response()) as mock_get:
            dispatch.TaskDispatch(cfg)._http_trigger()

        mock_get.assert_called_once_with("http://ci-web/tasks/dispatch", timeout=10)

    def test_adds_https_scheme_when_missing(self):
        cfg = {"APP_ROOT_URL": "s1094367114.online.de"}

        with patch.object(dispatch.requests, "get", return_value=fake_ok_response()) as mock_get:
            dispatch.TaskDispatch(cfg)._http_trigger()

        mock_get.assert_called_once_with(
            "https://s1094367114.online.de/tasks/dispatch", timeout=10
        )

    def test_strips_trailing_slash_before_path(self):
        cfg = {"APP_ROOT_URL": "http://ci-web"}

        with patch.object(dispatch.requests, "get", return_value=fake_ok_response()) as mock_get:
            dispatch.TaskDispatch(cfg)._http_trigger()

        url = mock_get.call_args.args[0]
        self.assertFalse(url.count("//") > 1, "Doppelter Schrägstrich im Pfad")
        self.assertEqual(url, "http://ci-web/tasks/dispatch")

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
        cfg = {"APP_ROOT_URL": "http://ci-web/"}
        exc = fake_http_error(500, "<b>PHP error</b>")
        mock_resp = MagicMock()
        mock_resp.raise_for_status.side_effect = exc

        with patch.object(dispatch.requests, "get", return_value=mock_resp):
            with self.assertRaises(requests.exceptions.HTTPError):
                dispatch.TaskDispatch(cfg)._http_trigger()

    def test_loggt_http_fehler_mit_url_und_status(self):
        cfg = {"APP_ROOT_URL": "http://ci-web/"}
        exc = fake_http_error(500, "<b>PHP error</b>")
        mock_resp = MagicMock()
        mock_resp.raise_for_status.side_effect = exc

        with patch.object(dispatch.requests, "get", return_value=mock_resp):
            with self.assertLogs("cli.py.task.dispatch", level="ERROR") as cm:
                with self.assertRaises(requests.exceptions.HTTPError):
                    dispatch.TaskDispatch(cfg)._http_trigger()

        self.assertTrue(any("500" in line for line in cm.output), cm.output)
        self.assertTrue(
            any("http://ci-web/tasks/dispatch" in line for line in cm.output),
            cm.output,
        )

    def test_reraises_connection_error_after_logging(self):
        cfg = {"APP_ROOT_URL": "http://ci-web/"}

        with patch.object(dispatch.requests, "get", side_effect=requests.exceptions.ConnectionError("refused")):
            with self.assertRaises(requests.exceptions.RequestException):
                dispatch.TaskDispatch(cfg)._http_trigger()

    def test_loggt_verbindungsfehler_mit_url(self):
        cfg = {"APP_ROOT_URL": "http://ci-web/"}

        with patch.object(dispatch.requests, "get", side_effect=requests.exceptions.ConnectionError("refused")):
            with self.assertLogs("cli.py.task.dispatch", level="ERROR") as cm:
                with self.assertRaises(requests.exceptions.RequestException):
                    dispatch.TaskDispatch(cfg)._http_trigger()

        self.assertTrue(
            any("http://ci-web/tasks/dispatch" in line for line in cm.output),
            cm.output,
        )

    def test_reraises_timeout_after_logging(self):
        cfg = {"APP_ROOT_URL": "http://ci-web/"}

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


class AppRootTest(unittest.TestCase):
    def test_returns_active_app_dir(self):
        client = MagicMock()
        slot_map = SlotMap.from_labels(app="b", vendor="a")
        with patch.object(dispatch.SlotStore, "require_current_slot_map", return_value=slot_map):
            result = dispatch.TaskDispatch({})._resolve_app_root(client)
        self.assertEqual(result, "app-b")

    def test_raises_when_no_active_slot(self):
        client = MagicMock()
        with patch.object(dispatch.SlotStore, "require_current_slot_map", side_effect=RuntimeError("Kein aktiver Slot")):
            with self.assertRaises(RuntimeError):
                dispatch.TaskDispatch({})._resolve_app_root(client)


class EnqueueTest(unittest.TestCase):
    def _make_task(self):
        return Task("deploy_switch", {"app": "a", "vendor": "b", "run_id": "42"})

    def test_creates_file_in_task_dir(self):
        client = MagicMock()
        dispatch.TaskDispatch({})._enqueue(client, self._make_task(), "app-a")
        path_arg = client.put_text.call_args.args[0]
        self.assertTrue(path_arg.startswith("app-a/var/tasks/"), path_arg)
        self.assertTrue(path_arg.endswith("-deploy_switch.ini"), path_arg)

    def test_ensures_task_dir_exists(self):
        client = MagicMock()
        dispatch.TaskDispatch({})._enqueue(client, self._make_task(), "app-a")
        client.ensure_dir.assert_called_once_with("app-a/var/tasks")

    def test_writes_valid_ini_content(self):
        client = MagicMock()
        dispatch.TaskDispatch({})._enqueue(client, self._make_task(), "app-a")
        content = client.put_text.call_args.args[1]
        self.assertIn("[task]", content)
        self.assertIn("type = deploy_switch", content)
        self.assertIn("app = a", content)

    def test_calls_logger_with_file_path(self):
        client = MagicMock()
        logged = []
        dispatch.TaskDispatch({}, logger=logged.append)._enqueue(client, self._make_task(), "app-a")
        self.assertTrue(any("app-a/var/tasks" in m for m in logged), logged)


class LoggerTest(unittest.TestCase):
    def test_accepts_logger_callable_without_error(self):
        dispatch.TaskDispatch({}, logger=lambda msg: None)

    def test_logger_called_on_http_success(self):
        logged = []
        cfg = {"APP_ROOT_URL": "http://example.com/"}
        with patch.object(dispatch.requests, "get", return_value=fake_ok_response()):
            dispatch.TaskDispatch(cfg, logger=logged.append)._http_trigger()
        self.assertTrue(any("HTTP-Auslöser" in m for m in logged), logged)

    def test_no_logger_does_not_raise(self):
        cfg = {"APP_ROOT_URL": "http://example.com/"}
        with patch.object(dispatch.requests, "get", return_value=fake_ok_response()):
            dispatch.TaskDispatch(cfg)._http_trigger()


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
