import io
import stat
import sys
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.sftp_lib import (  # noqa: E402
    SftpAttempt,
    SftpOperationResult,
)
from cli.py.deploy.sftp_shell import (  # noqa: E402
    SftpShell,
    normalize_path,
)


def attr(filename: str, mode: int = 0o100644):
    return SimpleNamespace(
        filename=filename,
        st_mode=mode,
        st_size=4,
        st_uid=1000,
        st_gid=1000,
        st_mtime=1,
        st_atime=2,
    )


def result(operation: str) -> SftpOperationResult:
    return SftpOperationResult([SftpAttempt(operation, "ok")])


class FakeSftpClient:
    def __init__(self):
        self.entries = {
            "": [attr("var", stat.S_IFDIR), attr("index.php")],
            "var": [attr("admin", stat.S_IFDIR), attr("log.txt")],
            "var/admin": [],
        }
        self.files = {"var/log.txt": "Log\n"}
        self.puts = []
        self.cfg = {
            "SFTP_HOST": "sftp.example.test",
            "SFTP_PORT": "22",
            "SFTP_USER": "deploy",
            "SFTP_SERVER_DIR": "/remote/root",
        }
        transport = FakeTransport()
        self._ssh = SimpleNamespace(get_transport=lambda: transport)

    def listdir_attr(self, rel_path: str):
        return self.entries[rel_path]

    def stat(self, rel_path: str):
        name = Path(rel_path).name
        if rel_path in self.entries:
            return attr(name, stat.S_IFDIR)
        if rel_path in self.files:
            return attr(name)
        raise OSError(rel_path)

    def read_file(self, rel_path: str) -> str:
        return self.files[rel_path]

    def open(self, rel_path: str, _mode: str = "r"):
        return io.BytesIO(self.files[rel_path].encode())

    def put_file(self, local_path: Path, rel_path: str) -> None:
        self.puts.append((Path(local_path), rel_path))

    def mkdir(self, rel_path: str) -> SftpOperationResult:
        self.entries.setdefault(rel_path, [])
        return result("sftp.mkdir")

    def mkdir_p(self, rel_path: str) -> bool:
        self.entries.setdefault(rel_path, [])
        return True

    def ensure_dir(self, rel_path: str) -> None:
        self.entries.setdefault(rel_path, [])

    def remove_file(self, rel_path: str) -> None:
        self.files.pop(rel_path)

    def clear_dir(self, rel_path: str) -> SftpOperationResult:
        count = len(self.entries.get(rel_path, []))
        self.entries[rel_path] = []
        attempts = [
            SftpAttempt("sftp.listdir_attr", "ok"),
            SftpAttempt("sftp.remove/rmdir", "ok"),
        ]
        return SftpOperationResult(attempts, count)

    def rmdir(self, rel_path: str) -> SftpOperationResult:
        if self.entries.get(rel_path):
            raise OSError("Directory not empty")
        self.entries.pop(rel_path)
        return result("sftp.rmdir")

    def remove_dir(self, rel_path: str) -> None:
        self.entries.pop(rel_path)

    def rename(self, source: str, target: str) -> SftpOperationResult:
        self.files[target] = self.files.pop(source)
        attempts = [
            SftpAttempt(
                "sftp.posix_rename",
                "unsupported",
                "Operation unsupported",
            ),
            SftpAttempt("sftp.rename", "ok"),
        ]
        return SftpOperationResult(attempts)

    def disk_usage(self, _rel_path: str) -> SftpOperationResult:
        attempts = [SftpAttempt("sftp.statvfs", "unsupported")]
        return SftpOperationResult(attempts)


class FakeTransport:
    remote_version = "SSH-2.0-test"
    local_version = "SSH-2.0-local"
    local_cipher = "aes128-ctr"
    remote_cipher = "aes128-ctr"

    def is_active(self) -> bool:
        return True


class SftpShellTest(unittest.TestCase):
    def test_normalize_path_stays_in_root(self):
        self.assertEqual(normalize_path("/", "var"), "")
        self.assertEqual(normalize_path("admin", "var"), "var/admin")
        self.assertEqual(
            normalize_path("../index.php", "var/admin"),
            "var/index.php",
        )

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
            expected = [
                (Path(source.name), f"var/{Path(source.name).name}")
            ]
            self.assertEqual(shell.client.puts, expected)

    def test_mkdir_reports_sftp_operation(self):
        shell = make_shell()
        shell.onecmd("mkdir var/new")
        self.assertEqual(shell.stdout.getvalue(), "sftp.mkdir: ok\n")

    def test_mkdirp_reports_recursive_sftp_mkdir(self):
        shell = make_shell()
        shell.onecmd("mkdirp var/new/deep")
        self.assertEqual(
            shell.stdout.getvalue(),
            "sftp.mkdir: rekursiv angelegt oder vorhanden\n",
        )

    def test_rm_reports_sftp_remove(self):
        shell = make_shell()
        shell.onecmd("rm var/log.txt")
        self.assertEqual(shell.stdout.getvalue(), "sftp.remove: ok\n")

    def test_rmdir_keeps_posix_meaning(self):
        shell = make_shell()
        shell.onecmd("rmdir var/admin")
        self.assertEqual(shell.stdout.getvalue(), "sftp.rmdir: ok\n")

    def test_rmdir_reports_non_empty_directory_error(self):
        shell = make_shell()
        shell.onecmd("rmdir var")
        self.assertEqual(
            shell.stdout.getvalue(),
            "Fehler: Directory not empty\n",
        )

    def test_clear_removes_directory_contents(self):
        shell = make_shell()
        shell.onecmd("clear var")
        self.assertEqual(
            shell.stdout.getvalue(),
            "sftp.listdir_attr: ok\n"
            "sftp.remove/rmdir: ok\n"
            "clear: 2 Einträge entfernt\n",
        )
        self.assertEqual(shell.client.entries["var"], [])

    def test_rename_reports_attempts(self):
        shell = make_shell()
        shell.onecmd("rename var/log.txt var/moved.txt")
        self.assertEqual(
            shell.stdout.getvalue(),
            "sftp.posix_rename: nicht unterstützt "
            "(Operation unsupported)\n"
            "sftp.rename: ok\n",
        )
        self.assertEqual(shell.client.files["var/moved.txt"], "Log\n")

    def test_stat_prints_remote_metadata(self):
        shell = make_shell()
        shell.onecmd("stat var/log.txt")
        self.assertIn("path: /var/log.txt\n", shell.stdout.getvalue())
        self.assertIn("mode: 0o100644\n", shell.stdout.getvalue())

    def test_df_reports_unsupported_extension(self):
        shell = make_shell()
        shell.onecmd("df /")
        self.assertEqual(
            shell.stdout.getvalue(),
            "sftp.statvfs: nicht unterstützt\n",
        )

    def test_status_prints_connection_context(self):
        shell = make_shell()
        shell.onecmd("status")
        self.assertIn(
            "host: sftp.example.test:22\n",
            shell.stdout.getvalue(),
        )
        self.assertIn("transport: aktiv\n", shell.stdout.getvalue())


def make_shell() -> SftpShell:
    shell = SftpShell(FakeSftpClient())
    shell.stdout = io.StringIO()
    return shell


if __name__ == "__main__":
    unittest.main()
