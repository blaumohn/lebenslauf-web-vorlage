import json
import sys
import tempfile
import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.vendor_sentinel import ComposerInputChecksum, VendorSentinel  # noqa: E402


class VendorSentinelTest(unittest.TestCase):
    def test_vendor_sentinel_ini_roundtrip(self):
        sentinel = VendorSentinel("checksum-1")

        content = sentinel.to_text()
        parsed = VendorSentinel.from_text(content)

        self.assertEqual(parsed.vendor_checksum, "checksum-1")
        self.assertEqual(content, "[vendor]\nchecksum = checksum-1\n\n")

    def test_vendor_sentinel_rejects_missing_checksum(self):
        with self.assertRaises(KeyError):
            VendorSentinel.from_text("[vendor]\n")

    def test_composer_checksum_uses_composer_inputs(self):
        with tempfile.TemporaryDirectory() as tmp:
            repo = Path(tmp)
            write_json(repo / "composer.json", {"autoload": {"psr-4": {"App\\": "src/"}}})
            write_json(repo / "composer.lock", {"packages": []})

            checksum_before = ComposerInputChecksum.from_repo(repo)
            write_json(repo / "composer.lock", {"packages": [{"name": "a/b"}]})
            checksum_after = ComposerInputChecksum.from_repo(repo)

        self.assertNotEqual(checksum_before, checksum_after)

    def test_composer_checksum_ignores_json_key_order(self):
        with tempfile.TemporaryDirectory() as tmp_a, tempfile.TemporaryDirectory() as tmp_b:
            repo_a = Path(tmp_a)
            repo_b = Path(tmp_b)
            write_json(repo_a / "composer.json", {"b": 1, "a": 2})
            write_json(repo_a / "composer.lock", {"packages": []})
            write_json(repo_b / "composer.json", {"a": 2, "b": 1})
            write_json(repo_b / "composer.lock", {"packages": []})

            checksum_a = ComposerInputChecksum.from_repo(repo_a)
            checksum_b = ComposerInputChecksum.from_repo(repo_b)

        self.assertEqual(checksum_a, checksum_b)


def write_json(path: Path, data: dict) -> None:
    path.write_text(json.dumps(data), encoding="utf-8")


if __name__ == "__main__":
    unittest.main()
