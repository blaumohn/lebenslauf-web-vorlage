import stat
import sys
import tempfile
import unittest
from dataclasses import dataclass
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.content_tree import fetch_content_tree, upload_content_tree  # noqa: E402


@dataclass
class Entry:
    filename: str
    st_mode: int


class FakeClient:
    def __init__(self):
        self.downloads = []
        self.uploads = []
        self.ensured_dirs = []
        self.remote_tree = {}

    def listdir_attr(self, path):
        return self.remote_tree[path]

    def get_file(self, remote_path, local_path):
        self.downloads.append((remote_path, local_path))
        Path(local_path).write_text(remote_path)

    def ensure_dir(self, path):
        self.ensured_dirs.append(path)

    def put_file(self, local_path, remote_path):
        self.uploads.append((local_path, remote_path))


class ContentTreeFetchTest(unittest.TestCase):
    def test_fetches_remote_tree_recursively(self):
        client = FakeClient()
        client.remote_tree = {
            "etc/content": [
                Entry("home", stat.S_IFDIR),
                Entry("site.yaml", stat.S_IFREG),
            ],
            "etc/content/home": [
                Entry("nested", stat.S_IFDIR),
                Entry("home.yaml", stat.S_IFREG),
            ],
            "etc/content/home/nested": [
                Entry("data.yaml", stat.S_IFREG),
            ],
        }

        with tempfile.TemporaryDirectory() as tmp:
            total = fetch_content_tree(client, "etc/content", Path(tmp))
            nested_file = Path(tmp) / "home" / "nested" / "data.yaml"

            self.assertEqual(total, 3)
            self.assertTrue(nested_file.is_file())

        self.assertIn(
            ("etc/content/home/home.yaml", Path(tmp) / "home" / "home.yaml"),
            client.downloads,
        )
        self.assertIn(
            (
                "etc/content/home/nested/data.yaml",
                Path(tmp) / "home" / "nested" / "data.yaml",
            ),
            client.downloads,
        )


class ContentTreeUploadTest(unittest.TestCase):
    def test_uploads_local_tree_recursively(self):
        client = FakeClient()

        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / "home" / "nested").mkdir(parents=True)
            (root / "home" / "home.yaml").write_text("home")
            (root / "home" / "nested" / "data.yaml").write_text("data")

            total = upload_content_tree(client, root, "etc/content")

        self.assertEqual(total, 2)
        self.assertIn("etc/content/home", client.ensured_dirs)
        self.assertIn("etc/content/home/nested", client.ensured_dirs)
        self.assertIn(
            "etc/content/home/home.yaml",
            [remote_path for _, remote_path in client.uploads],
        )
        self.assertIn(
            "etc/content/home/nested/data.yaml",
            [remote_path for _, remote_path in client.uploads],
        )


if __name__ == "__main__":
    unittest.main()
