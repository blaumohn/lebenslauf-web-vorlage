import json
import logging
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "src"))

from cli.py.util.log import Logger  # noqa: E402


def test_aufruf_gibt_praefix_auf_stdout_aus(capsys):
    m = Logger("test")
    m("Hallo")
    m.close()
    assert "[test] Hallo" in capsys.readouterr().out


def test_error_gibt_traceback_auf_stdout_aus(capsys):
    m = Logger("test")
    try:
        raise ValueError("Testfehler")
    except ValueError as exc:
        m.error(exc)
    m.close()
    out = capsys.readouterr().out
    assert "ValueError" in out
    assert "Testfehler" in out


def test_python_logging_warning_wird_weitergeleitet(capsys):
    m = Logger("test")
    logging.getLogger("test.modul").warning("Warnung!")
    m.close()
    out = capsys.readouterr().out
    assert "[test]" in out
    assert "Warnung!" in out


def test_python_logging_info_wird_unterdrueckt(capsys):
    m = Logger("test")
    logging.getLogger("test.modul.info").info("Info-Nachricht")
    m.close()
    assert "Info-Nachricht" not in capsys.readouterr().out


def test_schreibt_nachricht_in_datei(tmp_path):
    log_file = tmp_path / "deploy.log"
    m = Logger("test", file_path=log_file)
    m("Nachricht in Datei")
    m.close()
    assert "Nachricht in Datei" in log_file.read_text()


def test_datei_eintrag_enthaelt_zeitstempel(tmp_path):
    log_file = tmp_path / "deploy.log"
    m = Logger("test", file_path=log_file)
    m("Mit Zeitstempel")
    m.close()
    content = log_file.read_text()
    assert "T" in content


def test_close_entfernt_handler():
    vorher = len(logging.root.handlers)
    m = Logger("test")
    assert len(logging.root.handlers) == vorher + 1
    m.close()
    assert len(logging.root.handlers) == vorher


def test_aufruf_gibt_json_aus_wenn_log_format_json(capsys, monkeypatch):
    monkeypatch.setenv("LOG_FORMAT", "json")
    m = Logger("sftp")
    m("Hallo")
    m.close()
    obj = json.loads(capsys.readouterr().out.strip())
    assert obj == {"prefix": "sftp", "level": "info", "message": "Hallo"}


def test_error_gibt_type_feld_im_json_aus(capsys, monkeypatch):
    monkeypatch.setenv("LOG_FORMAT", "json")
    m = Logger("sftp")
    try:
        raise ValueError("Testfehler")
    except ValueError as exc:
        m.error(exc)
    m.close()
    obj = json.loads(capsys.readouterr().out.strip())
    assert obj["prefix"] == "sftp"
    assert obj["level"] == "error"
    assert obj["type"] == "ValueError"
    assert "Testfehler" in obj["message"]


