import sys
import tempfile
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.tree_uploader import SftpTreeUploader, UploadStats  # noqa: E402


class FakeClient:
    def __init__(self):
        self.uploaded = []
        self.dirs_created = []

    def ensure_dir(self, path):
        pass

    def mkdir_p(self, path):
        self.dirs_created.append(path)
        return True

    def put_file(self, local_path, remote_path):
        self.uploaded.append((str(local_path), remote_path))


def make_uploader(client):
    return SftpTreeUploader(client, lambda _: None)


class UploadDirTest(unittest.TestCase):
    def test_uploads_all_files_flat(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            (Path(tmp) / "a.html").write_text("A")
            (Path(tmp) / "b.html").write_text("B")
            stats = make_uploader(client).upload_dir(Path(tmp), "remote/html")

        remote_names = [r for _, r in client.uploaded]
        self.assertIn("remote/html/a.html", remote_names)
        self.assertIn("remote/html/b.html", remote_names)
        self.assertEqual(stats.files, 2)

    def test_recurses_into_subdirectory(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            sub = Path(tmp) / "sub"
            sub.mkdir()
            (sub / "c.html").write_text("C")
            make_uploader(client).upload_dir(Path(tmp), "remote/html")

        remote_names = [r for _, r in client.uploaded]
        self.assertIn("remote/html/sub/c.html", remote_names)

    def test_counts_bytes(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            (Path(tmp) / "x.html").write_bytes(b"12345")
            stats = make_uploader(client).upload_dir(Path(tmp), "remote/html")

        self.assertEqual(stats.bytes, 5)

    def test_counts_directories(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            (Path(tmp) / "sub").mkdir()
            stats = make_uploader(client).upload_dir(Path(tmp), "remote/html")

        self.assertGreaterEqual(stats.directories, 1)

    def test_creates_remote_root_dir(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            make_uploader(client).upload_dir(Path(tmp), "remote/html")

        self.assertIn("remote/html", client.dirs_created)


class UploadItemTest(unittest.TestCase):
    def test_file_item_uploaded(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            f = Path(tmp) / "page.html"
            f.write_text("X")
            stats = make_uploader(client).upload_item(f, "remote")

        self.assertEqual(stats.files, 1)
        self.assertIn(("remote/page.html"), [r for _, r in client.uploaded])

    def test_dir_item_recurses(self):
        client = FakeClient()
        with tempfile.TemporaryDirectory() as tmp:
            sub = Path(tmp) / "assets"
            sub.mkdir()
            (sub / "style.css").write_text("*{}")
            make_uploader(client).upload_item(sub, "remote")

        remote_names = [r for _, r in client.uploaded]
        self.assertIn("remote/assets/style.css", remote_names)


class UploadStatsTest(unittest.TestCase):
    def test_merge_accumulates_fields(self):
        a = UploadStats(directories=1, files=2, bytes=100)
        b = UploadStats(directories=3, files=4, bytes=200)
        a.merge(b)
        self.assertEqual(a.directories, 4)
        self.assertEqual(a.files, 6)
        self.assertEqual(a.bytes, 300)


if __name__ == "__main__":
    unittest.main()
