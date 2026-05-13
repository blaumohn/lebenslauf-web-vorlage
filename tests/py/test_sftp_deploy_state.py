import configparser
import sys
import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.sftp_deploy_state import DeployState, DeploymentPlan, SlotState
from cli.py.deploy.sftp_deploy_templates import resource_path


class SftpDeployStateTest(unittest.TestCase):
    def test_deploy_state_ini_roundtrip(self):
        state = SlotState("b", "a")
        content = DeployState.format(state)

        self.assertEqual(DeployState.parse(content), state)
        parser = configparser.ConfigParser()
        parser.read_string(content)
        self.assertEqual(parser["state"]["app"], "b")
        self.assertEqual(parser["state"]["vendor"], "a")

    def test_invalid_deploy_state_returns_none(self):
        self.assertIsNone(DeployState.parse("[state]\napp=x\nvendor=a\n"))
        self.assertIsNone(DeployState.parse("[]"))
        self.assertIsNone(DeployState.parse(""))

    def test_deploy_state_format_rejects_missing_state(self):
        with self.assertRaises(ValueError):
            DeployState.format(None)

    def test_deployment_plan_swaps_tree_and_optional_vendor(self):
        active = SlotState("a", "b")

        with_vendor = DeploymentPlan.swap(active, True)
        without_vendor = DeploymentPlan.swap(active, False)

        self.assertEqual(with_vendor.target, SlotState("b", "a"))
        self.assertEqual(without_vendor.target, SlotState("b", "b"))

    def test_static_entry_resources_exist(self):
        router = resource_path("index.php").read_text(encoding="utf-8")
        htaccess = resource_path(".htaccess").read_text(encoding="utf-8")

        self.assertIn("parse_ini_file", router)
        self.assertIn(".deploy-state.ini", router)
        self.assertNotIn("// deploy-state:", router)
        self.assertIn("RewriteRule ^ index.php [L]", htaccess)


if __name__ == "__main__":
    unittest.main()
