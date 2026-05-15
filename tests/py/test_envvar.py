import sys
import unittest
from pathlib import Path
from unittest.mock import patch


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.util.envvar import env  # noqa: E402


class EnvVarTest(unittest.TestCase):
    def test_require_nonempty_rejects_missing_value(self):
        with patch.dict("os.environ", {}, clear=True):
            with self.assertRaisesRegex(RuntimeError, "PIPELINE_RUN_ID is required"):
                env("PIPELINE_RUN_ID").require_nonempty()

    def test_require_nonempty_rejects_empty_value(self):
        with patch.dict("os.environ", {"PIPELINE_RUN_ID": ""}, clear=True):
            with self.assertRaisesRegex(RuntimeError, "PIPELINE_RUN_ID must not be empty"):
                env("PIPELINE_RUN_ID").require_nonempty()

    def test_require_bool_rejects_invalid_value(self):
        with patch.dict("os.environ", {"SFTP_INCLUDE_VENDOR": "yes"}, clear=True):
            with self.assertRaisesRegex(RuntimeError, "SFTP_INCLUDE_VENDOR must be one of"):
                env("SFTP_INCLUDE_VENDOR").require_bool()


if __name__ == "__main__":
    unittest.main()
