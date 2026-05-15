import cmd
import posixpath
import shlex
import stat
from pathlib import Path

from cli.py.deploy.sftp_lib import SftpClient


class SftpShell(cmd.Cmd):
    intro = "SFTP-REPL. help oder ? für Hilfe."
    prompt = "sftp:/> "

    def __init__(self, client: SftpClient):
        super().__init__()
        self.client = client
        self.cwd = ""

    def emptyline(self) -> None:
        return None

    def onecmd(self, line: str) -> bool:
        try:
            return bool(super().onecmd(line))
        except (OSError, ValueError) as error:
            self.write_error(error)
            return False

    def do_pwd(self, _arg: str) -> None:
        """Aktuelles Remote-Verzeichnis anzeigen."""
        self.write_line(display_path(self.cwd))

    def do_ls(self, arg: str) -> None:
        """ls [pfad] - Remote-Verzeichnis auflisten."""
        path = self.resolve_path(arg or ".")
        entries = sorted(self.client.listdir_attr(path), key=lambda entry: entry.filename)
        for entry in entries:
            self.write_line(format_entry(entry))

    def do_cd(self, arg: str) -> None:
        """cd [pfad] - Remote-Verzeichnis wechseln."""
        path = self.resolve_path(arg or "/")
        self.client.listdir_attr(path)
        self.cwd = path
        self.prompt = f"sftp:{display_path(self.cwd)}> "

    def do_cat(self, arg: str) -> None:
        """cat <pfad> - Remote-Datei ausgeben."""
        path = self.required_path(arg)
        text = self.client.read_file(path)
        self.stdout.write(text)
        if text and not text.endswith("\n"):
            self.stdout.write("\n")

    def do_get(self, arg: str) -> None:
        """get <remote> [lokal] - Remote-Datei herunterladen."""
        remote, local = self.remote_local_args(arg)
        local_path = Path(local or Path(remote).name)
        with self.client.open(remote, "rb") as source:
            local_path.write_bytes(source.read())
        self.write_line(f"get {remote} -> {local_path}")

    def do_put(self, arg: str) -> None:
        """put <lokal> [remote] - Lokale Datei hochladen."""
        local, remote = self.local_remote_args(arg)
        local_path = Path(local)
        target = remote or local_path.name
        remote_path = self.resolve_path(target)
        self.client.put_file(local_path, remote_path)
        self.write_line(f"put {local_path} -> {remote_path}")

    def do_mkdir(self, arg: str) -> None:
        """mkdir <pfad> - Remote-Verzeichnis anlegen."""
        self.client.mkdir_p(self.required_path(arg))

    def do_rm(self, arg: str) -> None:
        """rm <pfad> - Remote-Datei entfernen."""
        self.client.remove_file(self.required_path(arg))

    def do_rmdir(self, arg: str) -> None:
        """rmdir <pfad> - Remote-Verzeichnis rekursiv entfernen."""
        self.client.remove_dir(self.required_path(arg))

    def do_exit(self, _arg: str) -> bool:
        """REPL beenden."""
        return True

    do_quit = do_exit
    do_EOF = do_exit

    def required_path(self, arg: str) -> str:
        parts = split_args(arg)
        if len(parts) != 1:
            raise ValueError("genau ein Pfad erwartet")
        return self.resolve_path(parts[0])

    def remote_local_args(self, arg: str) -> tuple[str, str | None]:
        parts = split_args(arg)
        if len(parts) not in (1, 2):
            raise ValueError("erwartet: <remote> [lokal]")
        return self.resolve_path(parts[0]), optional_arg(parts, 1)

    def local_remote_args(self, arg: str) -> tuple[str, str | None]:
        parts = split_args(arg)
        if len(parts) not in (1, 2):
            raise ValueError("erwartet: <lokal> [remote]")
        return parts[0], optional_arg(parts, 1)

    def resolve_path(self, path: str) -> str:
        return normalize_path(path, self.cwd)

    def write_line(self, text: str) -> None:
        print(text, file=self.stdout)

    def write_error(self, error: Exception) -> None:
        print(f"Fehler: {error}", file=self.stdout)


def split_args(arg: str) -> list[str]:
    return shlex.split(arg)


def optional_arg(parts: list[str], index: int) -> str | None:
    return parts[index] if len(parts) > index else None


def normalize_path(path: str, cwd: str) -> str:
    raw_path = path if path.startswith("/") else posixpath.join("/", cwd, path)
    normalized = posixpath.normpath(raw_path)
    if normalized == "/":
        return ""
    return normalized.lstrip("/")


def display_path(path: str) -> str:
    return "/" if path == "" else f"/{path}"


def format_entry(entry) -> str:
    suffix = "/" if stat.S_ISDIR(entry.st_mode) else ""
    return f"{entry.filename}{suffix}"
