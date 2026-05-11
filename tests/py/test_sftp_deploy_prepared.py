import configparser
import sys
import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.sftp_deploy_prepared import PreparedDeployStore
from cli.py.deploy.sftp_deploy_state import SlotState


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



if __name__ == "__main__":
    unittest.main()
