# ruff: noqa: E402, I001
import importlib.util
import sys
import types
import unittest
from pathlib import Path
from unittest.mock import patch

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.vendor_sentinel import ComposerInputChecksum  # noqa: E402

CHECKSUM = "abc123def456abcd"
RUN_ID = "run-42"


def load_module():
    sys.modules.setdefault(
        "paramiko",
        types.SimpleNamespace(RejectPolicy=object, SSHClient=object),
    )
    path = REPO_ROOT / "scripts" / "sftp-verify-deploy.py"
    spec = importlib.util.spec_from_file_location(
        "sftp_verify_deploy", path
    )
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


class VerifyRunIdTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.mod = load_module()

    def test_kein_fehler_bei_ubereinstimmung(self):
        self.mod.verify_run_id(RUN_ID, RUN_ID)

    def test_exit_bei_abweichung(self):
        with self.assertRaises(SystemExit):
            self.mod.verify_run_id("run-99", RUN_ID)

    def test_exit_bei_leerer_run_id(self):
        with self.assertRaises(SystemExit):
            self.mod.verify_run_id("", RUN_ID)


class VerifyChecksumTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.mod = load_module()

    def test_kein_fehler_bei_ubereinstimmung(self):
        with patch.object(
            ComposerInputChecksum, "from_repo", return_value=CHECKSUM
        ):
            self.mod.verify_checksum(CHECKSUM)

    def test_exit_bei_abweichung(self):
        with patch.object(
            ComposerInputChecksum, "from_repo", return_value=CHECKSUM
        ):
            with self.assertRaises(SystemExit):
                self.mod.verify_checksum("falsch")

    def test_exit_bei_leerer_checksum(self):
        with patch.object(
            ComposerInputChecksum, "from_repo", return_value=CHECKSUM
        ):
            with self.assertRaises(SystemExit):
                self.mod.verify_checksum("")


if __name__ == "__main__":
    unittest.main()
