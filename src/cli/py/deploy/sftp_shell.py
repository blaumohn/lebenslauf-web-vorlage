import cmd
import posixpath
import shlex
import stat
from pathlib import Path

from cli.py.deploy.sftp_lib import SftpClient, SftpOperationError

ATTEMPT_STATUS_TEXT = {
    "ok": "ok",
    "unsupported": "nicht unterstützt",
    "failed": "fehlgeschlagen",
}


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
        except SftpOperationError as error:
            self.write_attempts(error.attempts)
            self.write_error(error)
            return False
        except (OSError, ValueError) as error:
            self.write_error(error)
            return False

    def do_pwd(self, _arg: str) -> None:
        """Aktuelles Remote-Verzeichnis anzeigen."""
        self.write_line(display_path(self.cwd))

    def do_ls(self, arg: str) -> None:
        """ls [pfad] - Remote-Verzeichnis auflisten."""
        path = self.resolve_path(arg or ".")
        entries = sorted(
            self.client.listdir_attr(path),
            key=lambda entry: entry.filename,
        )
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
        self.write_result(self.client.mkdir(self.required_path(arg)))

    def do_mkdirp(self, arg: str) -> None:
        """mkdirp <pfad> - Remote-Verzeichnis rekursiv anlegen."""
        self.client.ensure_dir(self.required_path(arg))
        self.write_line("sftp.mkdir: rekursiv angelegt oder vorhanden")

    def do_rm(self, arg: str) -> None:
        """rm <pfad> - Remote-Datei entfernen."""
        self.client.remove_file(self.required_path(arg))
        self.write_line("sftp.remove: ok")

    def do_rmdir(self, arg: str) -> None:
        """rmdir <pfad> - Leeres Remote-Verzeichnis entfernen."""
        self.write_result(self.client.rmdir(self.required_path(arg)))

    def do_clear(self, arg: str) -> None:
        """clear <pfad> - Remote-Verzeichnis leeren."""
        result = self.client.clear_dir(self.required_path(arg))
        self.write_result(result)
        self.write_line(f"clear: {result.value} Einträge entfernt")

    def do_rename(self, arg: str) -> None:
        """rename <alt> <neu> - Remote-Pfad umbenennen."""
        source, target = self.remote_remote_args(arg)
        self.write_result(self.client.rename(source, target))

    def do_stat(self, arg: str) -> None:
        """stat <pfad> - Remote-Metadaten anzeigen."""
        path = self.required_path(arg)
        self.write_lines(format_stat(path, self.client.stat(path)))

    def do_df(self, arg: str) -> None:
        """df [pfad] - Dateisysteminfo anzeigen, falls unterstützt."""
        path = self.resolve_path(arg or ".")
        result = self.client.disk_usage(path)
        self.write_result(result)
        if result.value is not None:
            self.write_lines(format_df(result.value))

    def do_status(self, _arg: str) -> None:
        """status - Ziel und Verbindungszustand anzeigen."""
        self.write_lines(format_status(self.client, self.cwd))

    def do_version(self, _arg: str) -> None:
        """version - SSH-/SFTP-Versionsinformationen anzeigen."""
        self.write_lines(format_version(self.client))

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

    def remote_remote_args(self, arg: str) -> tuple[str, str]:
        parts = split_args(arg)
        if len(parts) != 2:
            raise ValueError("erwartet: <alt> <neu>")
        return self.resolve_path(parts[0]), self.resolve_path(parts[1])

    def resolve_path(self, path: str) -> str:
        return normalize_path(path, self.cwd)

    def write_line(self, text: str) -> None:
        print(text, file=self.stdout)

    def write_lines(self, lines: list[str]) -> None:
        for line in lines:
            self.write_line(line)

    def write_result(self, result) -> None:
        self.write_attempts(result.attempts)

    def write_attempts(self, attempts) -> None:
        lines = [format_attempt(attempt) for attempt in attempts]
        self.write_lines(lines)

    def write_error(self, error: Exception) -> None:
        print(f"Fehler: {error}", file=self.stdout)


def split_args(arg: str) -> list[str]:
    return shlex.split(arg)


def optional_arg(parts: list[str], index: int) -> str | None:
    return parts[index] if len(parts) > index else None


def normalize_path(path: str, cwd: str) -> str:
    if path.startswith("/"):
        raw_path = path
    else:
        raw_path = posixpath.join("/", cwd, path)
    normalized = posixpath.normpath(raw_path)
    if normalized == "/":
        return ""
    return normalized.lstrip("/")


def display_path(path: str) -> str:
    return "/" if path == "" else f"/{path}"


def format_entry(entry) -> str:
    suffix = "/" if stat.S_ISDIR(entry.st_mode) else ""
    return f"{entry.filename}{suffix}"


def format_attempt(attempt) -> str:
    status = ATTEMPT_STATUS_TEXT.get(attempt.status, attempt.status)
    line = f"{attempt.operation}: {status}"
    if attempt.detail:
        return f"{line} ({attempt.detail})"
    return line


def format_stat(path: str, entry) -> list[str]:
    return [
        f"path: {display_path(path)}",
        f"size: {entry.st_size}",
        f"mode: {oct(entry.st_mode)}",
        f"uid: {entry.st_uid}",
        f"gid: {entry.st_gid}",
        f"mtime: {entry.st_mtime}",
        f"atime: {entry.st_atime}",
    ]


def format_df(info) -> list[str]:
    fields = [
        "f_bsize",
        "f_frsize",
        "f_blocks",
        "f_bfree",
        "f_bavail",
        "f_files",
        "f_ffree",
        "f_favail",
    ]
    return [f"{field}: {getattr(info, field)}" for field in fields]


def format_status(client: SftpClient, cwd: str) -> list[str]:
    cfg = client.cfg
    transport = client._ssh.get_transport()
    lines = [
        f"host: {cfg['SFTP_HOST']}:{cfg['SFTP_PORT']}",
        f"user: {cfg['SFTP_USER']}",
        f"root: {cfg['SFTP_SERVER_DIR']}",
        f"cwd: {display_path(cwd)}",
    ]
    lines.extend(format_transport_status(transport))
    return lines


def format_version(client: SftpClient) -> list[str]:
    transport = client._ssh.get_transport()
    return format_transport_status(transport)


def format_transport_status(transport) -> list[str]:
    if transport is None:
        return ["transport: nicht vorhanden"]
    return [
        f"transport: {transport_state(transport)}",
        f"remote_version: {optional_attr(transport, 'remote_version')}",
        f"local_version: {optional_attr(transport, 'local_version')}",
        f"local_cipher: {optional_attr(transport, 'local_cipher')}",
        f"remote_cipher: {optional_attr(transport, 'remote_cipher')}",
    ]


def transport_state(transport) -> str:
    return "aktiv" if transport.is_active() else "inaktiv"


def optional_attr(obj, name: str) -> str:
    return str(getattr(obj, name, "unbekannt"))
