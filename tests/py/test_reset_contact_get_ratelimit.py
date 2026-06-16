import importlib.util
import sys
import types
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
RESET_SCRIPT_PATH = (
    REPO_ROOT / "tests" / "ci" / "reset_contact_get_ratelimit.py"
)
sys.path.insert(0, str(REPO_ROOT / "src"))
sys.modules.setdefault(
    "paramiko",
    types.SimpleNamespace(RejectPolicy=object, SSHClient=object),
)


def load_reset_module():
    spec = importlib.util.spec_from_file_location(
        "reset_contact_get_ratelimit",
        RESET_SCRIPT_PATH,
    )
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


reset_module = load_reset_module()


class FakeSftpClient:
    def __init__(self, entries_by_dir):
        self.entries_by_dir = entries_by_dir
        self.removed_files = []

    def listdir_attr(self, directory):
        entries = self.entries_by_dir.get(directory)
        if entries is None:
            raise OSError("missing")
        return [
            types.SimpleNamespace(filename=name)
            for name in entries
        ]

    def remove_file(self, path):
        self.removed_files.append(path)


class ResetContactGetRateLimitTest(unittest.TestCase):
    def test_entfernt_nur_contact_get_dateien(self):
        sftp = FakeSftpClient({
            "app-a/var/tmp/ratelimit": [
                "contact_get_abc.json",
                "contact_post_abc.json",
                "contact_get_not-json.txt",
            ],
            "app-b/var/tmp/ratelimit": [
                "contact_get_def.json",
            ],
        })

        removed = reset_module.reset_contact_get_ratelimit(sftp)

        self.assertEqual(2, removed)
        self.assertEqual(
            [
                "app-a/var/tmp/ratelimit/contact_get_abc.json",
                "app-b/var/tmp/ratelimit/contact_get_def.json",
            ],
            sftp.removed_files,
        )

    def test_ignoriert_fehlende_ratelimit_verzeichnisse(self):
        sftp = FakeSftpClient({})

        removed = reset_module.reset_contact_get_ratelimit(sftp)

        self.assertEqual(0, removed)
        self.assertEqual([], sftp.removed_files)


if __name__ == "__main__":
    unittest.main()
