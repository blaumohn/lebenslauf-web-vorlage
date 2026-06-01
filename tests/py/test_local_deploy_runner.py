import sys
import unittest
from pathlib import Path
from unittest.mock import patch

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))
sys.path.insert(0, str(REPO_ROOT / "scripts" / "local"))

import deploy  # noqa: E402


class LocalDeployRunnerTest(unittest.TestCase):
    def test_run_deploy_uses_deploy_local_service(self):
        with patch.object(deploy, "compose") as compose:
            deploy.run_deploy()

        compose.assert_called_once()
        args = compose.call_args.args
        kwargs = compose.call_args.kwargs
        self.assertEqual(args[:4], ("run", "--rm", "--no-deps", "deploy-local"))
        self.assertEqual(kwargs["label"], "Lokaler Deploy")
        self.assertIn("PIPELINE_RUN_ID", kwargs["env"])


if __name__ == "__main__":
    unittest.main()
