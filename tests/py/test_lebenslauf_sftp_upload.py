import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))


def load_module():
    path = REPO_ROOT / "scripts" / "lebenslauf-sftp-upload.py"
    spec = importlib.util.spec_from_file_location("lebenslauf_sftp_upload", path)
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


class LebenslaufSftpUploadTest(unittest.TestCase):
    def setUp(self):
        self.module = load_module()

    def test_upload_relatives_daten_verzeichnis(self):
        with tempfile.TemporaryDirectory() as work_dir:
            source = Path(work_dir) / ".local" / "lebenslauf"
            self.write_fixture(source, "daten-relativ.yaml")
            client = self.run_upload(work_dir, ".local")

        self.assert_uploads(client, "daten-relativ.yaml")

    def test_upload_absolutes_daten_verzeichnis(self):
        with tempfile.TemporaryDirectory() as work_dir:
            content_base = Path(work_dir) / "absolute-content"
            source = content_base / "lebenslauf"
            self.write_fixture(source, "daten-absolut.yaml")
            client = self.run_upload(work_dir, str(content_base))

        self.assert_uploads(client, "daten-absolut.yaml")

    def run_upload(self, work_dir, data_path):
        client = FakeSftpClient()
        configs = {
            "build": {"CONTENT_PATH": data_path},
            "deploy": {},
        }
        with patch.object(self.module, "PipelineCfg", lambda phase: configs[phase]):
            with patch.object(self.module, "SftpClient", lambda _cfg: context(client)):
                with change_dir(work_dir):
                    self.module.main()
        return client

    def write_fixture(self, source, name):
        source.mkdir(parents=True)
        (source / name).write_text("name: Test\n")

    def assert_uploads(self, client, name):
        self.assertEqual(["etc/lebenslauf"], client.ensured_dirs)
        self.assertEqual([(name, f"etc/lebenslauf/{name}")], client.uploads)


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
        import os
        os.chdir(self.path)

    def __exit__(self, _exc_type, _exc, _tb):
        import os
        os.chdir(self.previous)
        return False


if __name__ == "__main__":
    unittest.main()
