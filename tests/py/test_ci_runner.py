import sys
import unittest
from pathlib import Path
from unittest.mock import patch

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.ci import runner  # noqa: E402


class CiRunnerTest(unittest.TestCase):
    def test_rejects_missing_pipeline(self):
        self.assertIsNone(runner.resolve_pipeline([]))

    def test_rejects_extra_args(self):
        self.assertIsNone(runner.resolve_pipeline(["preview", "extra"]))

    def test_main_rejects_extra_args(self):
        with patch.object(sys, "argv", ["runner.py", "preview", "extra"]):
            self.assertEqual(runner.main(), 1)


if __name__ == "__main__":
    unittest.main()
