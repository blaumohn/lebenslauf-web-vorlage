import sys
import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "scripts"))

from sftp_deploy_state import DeployState, DeploymentPlan, SlotState
from sftp_deploy_templates import (
    render_entry_htaccess,
    render_fallback_entry_htaccess,
    render_router,
    resource_path,
)


class SftpDeployStateTest(unittest.TestCase):
    def test_deploy_state_json_roundtrip(self):
        state = SlotState("b", "a")
        content = DeployState.format(state)

        self.assertEqual(DeployState.parse(content), state)
        self.assertEqual(content, '{"tree": "b", "vendor": "a"}\n')

    def test_invalid_deploy_state_returns_none(self):
        self.assertIsNone(DeployState.parse('{"tree": "x", "vendor": "a"}'))
        self.assertIsNone(DeployState.parse("[]"))
        self.assertIsNone(DeployState.parse("{"))

    def test_deployment_plan_swaps_tree_and_optional_vendor(self):
        active = SlotState("a", "b")

        with_vendor = DeploymentPlan.swap(active, True)
        without_vendor = DeploymentPlan.swap(active, False)

        self.assertEqual(with_vendor.target, SlotState("b", "a"))
        self.assertEqual(without_vendor.target, SlotState("b", "b"))

    def test_templates_render_current_deploy_files(self):
        state = SlotState("b", "a")

        self.assertNotIn("deploy-state", render_router(state))
        self.assertIn("vendor-a", render_router(state))
        self.assertIn("b/public/$1", render_entry_htaccess(state))
        self.assertIn("RewriteRule ^ index.php [L]", render_fallback_entry_htaccess())
        self.assertTrue(resource_path(".htaccess-fallback").is_file())


if __name__ == "__main__":
    unittest.main()
