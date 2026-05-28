import sys
import unittest
from pathlib import Path
from unittest.mock import patch

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.ci import runner  # noqa: E402
from cli.py.util import compose_runner  # noqa: E402


class CiRunnerTest(unittest.TestCase):
    def test_rejects_missing_pipeline(self):
        self.assertIsNone(runner.resolve_pipeline([]))

    def test_rejects_extra_args(self):
        self.assertIsNone(runner.resolve_pipeline(["preview", "extra"]))

    def test_main_rejects_extra_args(self):
        with patch.object(sys, "argv", ["runner.py", "preview", "extra"]):
            self.assertEqual(runner.main(), 1)

    def test_build_ci_run_id_contains_test_case(self):
        run_id = runner.build_ci_run_id("test-push-deploy")

        self.assertRegex(run_id, r"^ci-test-push-deploy-[0-9a-f]{32}$")

    def test_runner_env_sets_run_id_with_prefix(self):
        env = compose_runner.runner_env("local-deploy")

        self.assertRegex(env["PIPELINE_RUN_ID"], r"^local-deploy-[0-9a-f]{32}$")

    def test_preview_test_env_sets_new_github_run_id(self):
        first = runner.preview_test_env("test-push-deploy")
        second = runner.preview_test_env("test-push-deploy")

        self.assertIn("PIPELINE_RUN_ID", first)
        self.assertNotEqual(first["PIPELINE_RUN_ID"], second["PIPELINE_RUN_ID"])

    def test_preview_test_env_setzt_log_format_fuer_typen_unterdrückung(self):
        env = runner.preview_test_env("test-rollback")

        self.assertEqual(env["LOG_FORMAT"], "json")

    def test_preview_test_env_kein_log_format_ohne_unterdrückung(self):
        env = runner.preview_test_env("test-push-deploy")

        self.assertNotIn("LOG_FORMAT", env)


if __name__ == "__main__":
    unittest.main()
