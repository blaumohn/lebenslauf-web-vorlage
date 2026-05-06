import configparser
import sys
import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "scripts"))

from sftp_deploy_prepared import AdminTaskStore, PreparedDeployStore
from sftp_deploy_state import SlotState


class PreparedDeployStoreTest(unittest.TestCase):
    def test_format_roundtrip(self):
        state = SlotState("b", "a")
        content = PreparedDeployStore._format(state)

        parser = configparser.ConfigParser()
        parser.read_string(content)

        self.assertEqual(parser["prepared"]["tree"], "b")
        self.assertEqual(parser["prepared"]["vendor"], "a")

    def test_ref_contains_tree_slot(self):
        state = SlotState("b", "a")
        ref = PreparedDeployStore._ref(state)
        self.assertTrue(ref.endswith("-b"))

    def test_ref_contains_timestamp(self):
        state = SlotState("a", "b")
        ref = PreparedDeployStore._ref(state)
        self.assertRegex(ref, r"^\d{8}T\d{6}Z-")


class AdminTaskStoreTest(unittest.TestCase):
    def test_format_deploy_switch(self):
        content = AdminTaskStore._format("var/admin/deploy-prepared/ref.ini")

        parser = configparser.ConfigParser()
        parser.read_string(content)

        self.assertEqual(parser["task"]["type"], "deploy_switch")
        self.assertEqual(parser["task"]["prepared_state"], "var/admin/deploy-prepared/ref.ini")


if __name__ == "__main__":
    unittest.main()
