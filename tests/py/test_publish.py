import importlib.util
import sys
import types
import unittest
from pathlib import Path
from unittest.mock import MagicMock, patch

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))
sys.modules.setdefault("paramiko", types.SimpleNamespace(RejectPolicy=object, SSHClient=object))


def load_module():
    path = REPO_ROOT / "scripts" / "publish.py"
    spec = importlib.util.spec_from_file_location("publish", path)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


publish = load_module()


def _make_slot(app_dir="app-a"):
    slot = MagicMock()
    slot.app.dir = app_dir
    return slot


def _make_sftp_ctx():
    ctx = MagicMock()
    ctx.__enter__.return_value = ctx
    return ctx


def _ok_response():
    resp = MagicMock()
    resp.status_code = 200
    resp.text = "<html>" + "x" * 600
    return resp


class UploadAndDispatchTest(unittest.TestCase):
    def test_laedt_html_in_staging_pfad_hoch(self):
        uploader = MagicMock()
        with patch.object(publish, "SftpClient", return_value=_make_sftp_ctx()), \
             patch.object(publish, "SlotStore") as MockSlot, \
             patch.object(publish, "SftpTreeUploader", return_value=uploader), \
             patch.object(publish, "TaskDispatch"):
            MockSlot.return_value.current_slot_map.return_value = _make_slot()
            publish._upload_and_dispatch({}, lambda m: None)
        _, staging = uploader.upload_dir.call_args.args
        self.assertIn("html-publish", staging)

    def test_reicht_cv_publish_task_ein(self):
        task_dispatch = MagicMock()
        with patch.object(publish, "SftpClient", return_value=_make_sftp_ctx()), \
             patch.object(publish, "SlotStore") as MockSlot, \
             patch.object(publish, "SftpTreeUploader"), \
             patch.object(publish, "TaskDispatch", return_value=task_dispatch):
            MockSlot.return_value.current_slot_map.return_value = _make_slot()
            publish._upload_and_dispatch({}, lambda m: None)
        submitted = task_dispatch.submit.call_args.args[0]
        self.assertEqual(submitted.type, "cv_publish")

    def test_wirft_fehler_wenn_kein_aktiver_slot(self):
        with patch.object(publish, "SftpClient", return_value=_make_sftp_ctx()), \
             patch.object(publish, "SlotStore") as MockSlot:
            MockSlot.return_value.current_slot_map.return_value = None
            with self.assertRaises(RuntimeError):
                publish._upload_and_dispatch({}, lambda m: None)


class SmokeCheckTest(unittest.TestCase):
    def test_ueberspringt_wenn_url_fehlt(self):
        with patch.object(publish.requests, "get") as mock_get:
            publish._smoke_check({}, {}, lambda m: None)
        mock_get.assert_not_called()

    def test_ueberspringt_wenn_url_leer(self):
        with patch.object(publish.requests, "get") as mock_get:
            publish._smoke_check({"APP_ROOT_URL": ""}, {}, lambda m: None)
        mock_get.assert_not_called()

    def test_besteht_bei_gueltigem_inhalt(self):
        with patch.object(publish.requests, "get", return_value=_ok_response()) as mock_get:
            publish._smoke_check(
                {"APP_ROOT_URL": "http://example.com"},
                {"CONTENT_LANGS": "es,de"},
                lambda m: None,
            )
        self.assertEqual(
            mock_get.call_args.kwargs["headers"],
            {"Accept-Language": "es"},
        )

    def test_wirft_fehler_wenn_content_langs_fehlt(self):
        with self.assertRaises(KeyError):
            publish._smoke_check({"APP_ROOT_URL": "http://example.com"}, {}, lambda m: None)

    def test_wirft_fehler_bei_nicht_200(self):
        resp = MagicMock()
        resp.status_code = 503
        with patch.object(publish.requests, "get", return_value=resp):
            with self.assertRaises(RuntimeError):
                publish._smoke_check(
                    {"APP_ROOT_URL": "http://example.com"},
                    {"CONTENT_LANGS": "de"},
                    lambda m: None,
                )

    def test_wirft_fehler_bei_zu_kurzem_inhalt(self):
        resp = MagicMock()
        resp.status_code = 200
        resp.text = "<html>kurz"
        with patch.object(publish.requests, "get", return_value=resp):
            with self.assertRaises(RuntimeError):
                publish._smoke_check(
                    {"APP_ROOT_URL": "http://example.com"},
                    {"CONTENT_LANGS": "de"},
                    lambda m: None,
                )

    def test_wirft_fehler_bei_fehlendem_html_tag(self):
        resp = MagicMock()
        resp.status_code = 200
        resp.text = "kein-tag-" + "x" * 600
        with patch.object(publish.requests, "get", return_value=resp):
            with self.assertRaises(RuntimeError):
                publish._smoke_check(
                    {"APP_ROOT_URL": "http://example.com"},
                    {"CONTENT_LANGS": "de"},
                    lambda m: None,
                )


class QualityCheckTest(unittest.TestCase):
    def test_html_qa_prueft_lokalen_cache(self):
        with patch.object(publish.subprocess, "run") as mock_run:
            publish._html_quality_check(lambda m: None)
        mock_run.assert_called_once_with(["npm", "run", "qa:html"], check=True)

    def test_a11y_ueberspringt_wenn_url_fehlt(self):
        with patch.object(publish.subprocess, "run") as mock_run:
            publish._accessibility_check({}, {}, lambda m: None)
        mock_run.assert_not_called()

    def test_a11y_prueft_veroeffentlichte_web_ansicht(self):
        with patch.object(publish.subprocess, "run") as mock_run:
            publish._accessibility_check(
                {"APP_ROOT_URL": "http://example.com/"},
                {"CONTENT_LANGS": "de,es"},
                lambda m: None,
            )
        args, kwargs = mock_run.call_args
        self.assertEqual(args[0], ["npm", "run", "qa:a11y"])
        self.assertTrue(kwargs["check"])
        self.assertEqual(kwargs["env"]["PLAYWRIGHT_BASE_URL"], "http://example.com")
        self.assertEqual(kwargs["env"]["CONTENT_LANGS"], "de,es")

    def test_a11y_wirft_fehler_wenn_content_langs_fehlt(self):
        with patch.object(publish.subprocess, "run") as mock_run:
            with self.assertRaises(KeyError):
                publish._accessibility_check(
                    {"APP_ROOT_URL": "http://example.com/"},
                    {},
                    lambda m: None,
                )
        mock_run.assert_not_called()


class MainTest(unittest.TestCase):
    def test_ruft_quality_upload_smoke_a11y_in_reihenfolge(self):
        order = []
        with patch.object(publish, "PipelineCfg", side_effect=[MagicMock(), MagicMock()]), \
             patch.object(publish, "Logger", return_value=lambda msg: None), \
             patch.object(publish, "_html_quality_check", side_effect=lambda *a: order.append("html")), \
             patch.object(publish, "_upload_and_dispatch", side_effect=lambda *a: order.append("upload")), \
             patch.object(publish, "_smoke_check", side_effect=lambda *a: order.append("smoke")), \
             patch.object(publish, "_accessibility_check", side_effect=lambda *a: order.append("a11y")):
            publish.main()
        self.assertEqual(order, ["html", "upload", "smoke", "a11y"])


if __name__ == "__main__":
    unittest.main()
