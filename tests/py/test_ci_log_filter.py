import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "src"))

from cli.py.ci.log_filter import is_suppressed_log_line, reformat_log_line  # noqa: E402


def test_filtert_json_zeile_mit_unterdruecktem_typ():
    line = json.dumps({
        "prefix": "sftp", "level": "error",
        "type": "ConnectionError", "message": "fail",
    })
    assert is_suppressed_log_line(line, {"ConnectionError"})


def test_laesst_json_zeile_ohne_typ_durch():
    line = json.dumps({"prefix": "sftp", "level": "info", "message": "ok"})
    assert not is_suppressed_log_line(line, {"ConnectionError"})


def test_laesst_nicht_json_zeile_durch():
    assert not is_suppressed_log_line("[sftp] info", {"ConnectionError"})


def test_reformat_gibt_praefix_und_message_aus():
    line = json.dumps({"prefix": "sftp", "level": "info", "message": "Hallo"})
    assert reformat_log_line(line) == "[sftp] Hallo"


def test_reformat_laesst_nicht_json_unveraendert():
    assert reformat_log_line("[sftp] Hallo") == "[sftp] Hallo"
