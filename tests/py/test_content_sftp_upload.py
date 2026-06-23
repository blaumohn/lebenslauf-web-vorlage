import importlib.util
import os
import sys
import tempfile
import unittest
from contextlib import contextmanager
from pathlib import Path
from unittest.mock import patch

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))


def load_module():
    path = REPO_ROOT / "scripts" / "content-sftp-upload.py"
    spec = importlib.util.spec_from_file_location("content_sftp_upload", path)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


class FakeSftpClient:
    def __init__(self):
        self.uploads = []
        self.ensured_dirs = []

    def dir_exists(self, _path):
        return False

    def ensure_dir(self, path):
        self.ensured_dirs.append(path)

    def put_file(self, local_path, remote_path):
        self.uploads.append((Path(local_path).name, remote_path))


class ContentSftpUploadTest(unittest.TestCase):
    def setUp(self):
        self.module = load_module()

    def test_upload_relatives_content_verzeichnis(self):
        with tempfile.TemporaryDirectory() as work_dir:
            content_path = Path(work_dir) / ".local" / "content"
            self.write_fixture(content_path / "lebenslauf", "daten-demo.yaml")
            self.write_fixture(content_path / "home", "home.yaml")
            client = self.run_upload(work_dir, str(content_path))

        self.assertIn("etc/content/lebenslauf", client.ensured_dirs)
        self.assertIn("etc/content/home", client.ensured_dirs)
        self.assertIn(("daten-demo.yaml", "etc/content/lebenslauf/daten-demo.yaml"), client.uploads)
        self.assertIn(("home.yaml", "etc/content/home/home.yaml"), client.uploads)

    def test_upload_absolutes_content_verzeichnis(self):
        with tempfile.TemporaryDirectory() as work_dir:
            content_path = Path(work_dir) / "absolute-content"
            self.write_fixture(content_path / "lebenslauf", "daten-demo.yaml")
            client = self.run_upload(work_dir, str(content_path))

        self.assertIn(("daten-demo.yaml", "etc/content/lebenslauf/daten-demo.yaml"), client.uploads)

    def run_upload(self, work_dir, content_path):
        client = FakeSftpClient()
        configs = {
            "build": {"CONTENT_PATH": content_path},
            "deploy": {},
        }
        with patch.object(self.module, "PipelineCfg", lambda phase: configs[phase]):
            with patch.object(self.module, "SftpClient", lambda _cfg: context(client)):
                with change_dir(work_dir):
                    self.module.main()
        return client

    def write_fixture(self, subdir: Path, name: str) -> None:
        subdir.mkdir(parents=True, exist_ok=True)
        (subdir / name).write_text("titel: Beispiel\n")


class context:
    def __init__(self, value):
        self.value = value

    def __enter__(self):
        return self.value

    def __exit__(self, _exc_type, _exc, _tb):
        return False


class change_dir:
    def __init__(self, path):
        self.path = path
        self.previous = None

    def __enter__(self):
        self.previous = Path.cwd()
        os.chdir(self.path)

    def __exit__(self, _exc_type, _exc, _tb):
        os.chdir(self.previous)
        return False


if __name__ == "__main__":
    unittest.main()
