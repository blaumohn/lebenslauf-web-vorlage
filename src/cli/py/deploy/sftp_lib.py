import errno
import os
import stat
import tempfile
from contextlib import suppress
from dataclasses import dataclass
from pathlib import Path

import paramiko


@dataclass
class SftpAttempt:
    operation: str
    status: str
    detail: str = ""


@dataclass
class SftpOperationResult:
    attempts: list[SftpAttempt]
    value: object | None = None


class SftpOperationError(OSError):
    def __init__(self, message, attempts):
        super().__init__(message)
        self.attempts = attempts


class SftpClient:
    def __init__(self, cfg):
        self.cfg = cfg
        known_hosts = Path("/tmp/sftp-known-hosts")
        known_hosts.write_text(cfg["SSH_KNOWN_HOST_LINE"] + "\n")
        known_hosts.chmod(0o600)
        self._ssh = paramiko.SSHClient()
        self._ssh.load_system_host_keys(str(known_hosts))
        self._ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
        self._ssh.connect(
            cfg["SFTP_HOST"],
            port=int(cfg["SFTP_PORT"]),
            username=cfg["SFTP_USER"],
            password=cfg["SFTP_PASS"],
        )
        self.sftp = self._ssh.open_sftp()

    def __enter__(self):
        return self

    def read_file(self, rel_path):
        try:
            with self.sftp.open(self._abs(rel_path)) as f:
                return f.read().decode()
        except OSError:
            return ""

    def file_exists(self, rel_path):
        try:
            self.sftp.stat(self._abs(rel_path))
            return True
        except OSError as e:
            if e.errno == errno.ENOENT:
                return False
            raise

    def dir_exists(self, rel_path):
        try:
            attrs = self.sftp.stat(self._abs(rel_path))
            return stat.S_ISDIR(attrs.st_mode)
        except OSError as e:
            if e.errno == errno.ENOENT:
                return False
            raise

    def put_bytes(self, rel_path, data):
        with tempfile.NamedTemporaryFile(delete=False) as tmp:
            tmp.write(data)
            tmp_path = tmp.name
        try:
            self.sftp.put(tmp_path, self._abs(rel_path))
        finally:
            os.unlink(tmp_path)

    def put_text(self, rel_path, text):
        self.put_bytes(rel_path, text.encode("utf-8"))

    def append_line(self, rel_path, line):
        with self.sftp.open(self._abs(rel_path), "a") as f:
            f.write((line + "\n").encode("utf-8"))

    def put_file(self, local_path, rel_path):
        self.sftp.put(str(local_path), self._abs(rel_path))

    def get_file(self, rel_path, local_path):
        self.sftp.get(self._abs(rel_path), str(local_path))

    def mkdir(self, rel_path):
        self.sftp.mkdir(self._abs(rel_path))
        return ok_result("sftp.mkdir")

    def mkdir_p(self, rel_path):
        try:
            self.sftp.mkdir(self._abs(rel_path))
            return True
        except OSError:
            return False

    def ensure_dir(self, rel_path):
        base = self.cfg["SFTP_SERVER_DIR"].rstrip("/")
        path = base
        for part in rel_path.split("/"):
            if not part:
                continue
            path += "/" + part
            with suppress(OSError):
                self.sftp.mkdir(path)

    def clear_dir(self, rel_path):
        count = self._clear_abs(self._abs(rel_path))
        attempts = [
            SftpAttempt("sftp.listdir_attr", "ok"),
            SftpAttempt("sftp.remove/rmdir", "ok"),
        ]
        return SftpOperationResult(attempts, count)

    def rmdir(self, rel_path):
        self.sftp.rmdir(self._abs(rel_path))
        return ok_result("sftp.rmdir")

    def remove_dir(self, rel_path):
        self._remove_abs(self._abs(rel_path))

    def listdir_attr(self, rel_path):
        return self.sftp.listdir_attr(self._abs(rel_path))

    def stat(self, rel_path):
        return self.sftp.stat(self._abs(rel_path))

    def rename(self, source, target):
        attempts = []
        if self._try_rename("posix_rename", attempts, source, target):
            return SftpOperationResult(attempts)
        if self._try_rename("rename", attempts, source, target):
            return SftpOperationResult(attempts)
        self._rename_file_fallback(source, target, attempts)
        attempts.append(SftpAttempt("fallback.file_move", "ok"))
        return SftpOperationResult(attempts)

    def disk_usage(self, rel_path):
        method = getattr(self.sftp, "statvfs", None)
        if method is None:
            attempt = SftpAttempt("sftp.statvfs", "unsupported")
            return SftpOperationResult([attempt])
        try:
            return SftpOperationResult(
                [SftpAttempt("sftp.statvfs", "ok")],
                method(self._abs(rel_path)),
            )
        except OSError as error:
            return SftpOperationResult(
                [SftpAttempt("sftp.statvfs", "failed", str(error))]
            )

    def remove_file(self, rel_path):
        self.sftp.remove(self._abs(rel_path))

    def open(self, rel_path, mode="r"):
        return self.sftp.open(self._abs(rel_path), mode)

    def _abs(self, rel_path):
        return self.cfg["SFTP_SERVER_DIR"].rstrip("/") + "/" + rel_path

    def _try_rename(self, method_name, attempts, source, target):
        method = getattr(self.sftp, method_name, None)
        operation = f"sftp.{method_name}"
        if method is None:
            attempts.append(SftpAttempt(operation, "unsupported"))
            return False
        try:
            method(self._abs(source), self._abs(target))
            attempts.append(SftpAttempt(operation, "ok"))
            return True
        except OSError as error:
            status = error_status(error)
            attempts.append(SftpAttempt(operation, status, str(error)))
            return False

    def _rename_file_fallback(self, source, target, attempts):
        try:
            self._copy_regular_file(source, target)
            self.sftp.remove(self._abs(source))
        except OSError as error:
            raise SftpOperationError(str(error), attempts) from error

    def _copy_regular_file(self, source, target):
        source_abs = self._abs(source)
        source_attr = self.sftp.stat(source_abs)
        if not stat.S_ISREG(source_attr.st_mode):
            raise OSError("Fallback unterstützt nur reguläre Dateien")
        with self.sftp.open(source_abs, "rb") as source_file:
            self.put_bytes(target, source_file.read())

    def _remove_abs(self, abs_path):
        try:
            entries = self.sftp.listdir_attr(abs_path)
        except OSError:
            return
        for entry in entries:
            child = abs_path.rstrip("/") + "/" + entry.filename
            if stat.S_ISDIR(entry.st_mode):
                self._remove_abs(child)
            else:
                try:
                    self.sftp.remove(child)
                except OSError as e:
                    dir_info = self._dir_info(abs_path)
                    raise PermissionError(
                        f"sftp.remove({child!r}) "
                        f"file_uid={entry.st_uid} "
                        f"file_mode={oct(entry.st_mode)} "
                        f"{dir_info}: {e}"
                    ) from e
        try:
            self.sftp.rmdir(abs_path)
        except OSError as e:
            message = f"sftp.rmdir({abs_path!r}): {e}"
            raise PermissionError(message) from e

    def _dir_info(self, abs_path):
        try:
            dir_attr = self.sftp.lstat(abs_path)
            return (
                f"dir_uid={dir_attr.st_uid} "
                f"dir_mode={oct(dir_attr.st_mode)}"
            )
        except Exception:
            return "dir-stat-failed"

    def _clear_abs(self, abs_path):
        entries = self.sftp.listdir_attr(abs_path)
        count = 0
        for entry in entries:
            child = abs_path.rstrip("/") + "/" + entry.filename
            if stat.S_ISDIR(entry.st_mode):
                count += self._clear_abs(child)
                self.sftp.rmdir(child)
            else:
                self.sftp.remove(child)
            count += 1
        return count

    def __exit__(self, *_):
        self.close()

    def close(self):
        self.sftp.close()
        transport = self._ssh.get_transport()
        if transport is not None and transport.is_active():
            transport.close()
        self._ssh.close()


def ok_result(operation):
    return SftpOperationResult([SftpAttempt(operation, "ok")])


def error_status(error):
    text = str(error).lower()
    if "unsupported" in text or "not supported" in text:
        return "unsupported"
    return "failed"
