import io
import stat
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace

from cli.py.deploy.sftp_shell import SftpShell, normalize_path


def attr(filename: str, mode: int = 0o100644):
    return SimpleNamespace(filename=filename, st_mode=mode)


class FakeSftpClient:
    def __init__(self):
        self.entries = {
            "": [attr("var", stat.S_IFDIR), attr("index.php")],
            "var": [attr("admin", stat.S_IFDIR), attr("log.txt")],
            "var/admin": [],
        }
        self.files = {"var/log.txt": "Log\n"}
        self.puts = []

    def listdir_attr(self, rel_path: str):
        return self.entries[rel_path]

    def read_file(self, rel_path: str) -> str:
        return self.files[rel_path]

    def open(self, rel_path: str, _mode: str = "r"):
        return io.BytesIO(self.files[rel_path].encode())

    def put_file(self, local_path: Path, rel_path: str) -> None:
        self.puts.append((Path(local_path), rel_path))

    def mkdir_p(self, rel_path: str) -> bool:
        self.entries.setdefault(rel_path, [])
        return True

    def remove_file(self, rel_path: str) -> None:
        self.files.pop(rel_path)

    def remove_dir(self, rel_path: str) -> None:
        self.entries.pop(rel_path)


class SftpShellTest(unittest.TestCase):
    def test_normalize_path_stays_in_root(self):
        self.assertEqual(normalize_path("/", "var"), "")
        self.assertEqual(normalize_path("admin", "var"), "var/admin")
        self.assertEqual(normalize_path("../index.php", "var/admin"), "var/index.php")

    def test_pwd_and_cd_update_prompt(self):
        shell = make_shell()
        shell.onecmd("cd var")
        shell.onecmd("pwd")
        self.assertEqual(shell.stdout.getvalue(), "/var\n")
        self.assertEqual(shell.prompt, "sftp:/var> ")

    def test_ls_marks_directories(self):
        shell = make_shell()
        shell.onecmd("ls /")
        self.assertEqual(shell.stdout.getvalue(), "index.php\nvar/\n")

    def test_cat_prints_remote_file(self):
        shell = make_shell()
        shell.onecmd("cat var/log.txt")
        self.assertEqual(shell.stdout.getvalue(), "Log\n")

    def test_get_writes_local_file(self):
        shell = make_shell()
        with tempfile.TemporaryDirectory() as tmp_dir:
            target = Path(tmp_dir) / "log.txt"
            shell.onecmd(f"get var/log.txt {target}")
            self.assertEqual(target.read_text(), "Log\n")

    def test_put_uses_current_remote_directory(self):
        shell = make_shell()
        with tempfile.NamedTemporaryFile() as source:
            shell.onecmd("cd var")
            shell.onecmd(f"put {source.name}")
            expected = [(Path(source.name), f"var/{Path(source.name).name}")]
            self.assertEqual(shell.client.puts, expected)


def make_shell() -> SftpShell:
    shell = SftpShell(FakeSftpClient())
    shell.stdout = io.StringIO()
    return shell


if __name__ == "__main__":
    unittest.main()
