import json
import os
import stat
import tempfile
from pathlib import Path
import paramiko


def read_config():
    return json.loads(os.environ["SFTP_CFG_JSON"])


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
            cfg["FTP_HOST"],
            port=int(cfg["FTP_PORT"]),
            username=cfg["FTP_USER"],
            password=cfg["FTP_PASS"],
        )
        self.sftp = self._ssh.open_sftp()

    def __enter__(self):
        return self

    def read_file(self, rel_path):
        try:
            with self.sftp.open(self._abs(rel_path)) as f:
                return f.read().decode()
        except IOError:
            return ""

    def put_bytes(self, rel_path, data):
        with tempfile.NamedTemporaryFile(delete=False) as tmp:
            tmp.write(data)
            tmp_path = tmp.name
        try:
            self.sftp.put(tmp_path, self._abs(rel_path))
        finally:
            os.unlink(tmp_path)

    def put_file(self, local_path, rel_path):
        self.sftp.put(str(local_path), self._abs(rel_path))

    def mkdir_p(self, rel_path):
        try:
            self.sftp.mkdir(self._abs(rel_path))
            return True
        except OSError:
            return False

    def ensure_dir(self, rel_path):
        base = self.cfg["FTP_SERVER_DIR"].rstrip("/")
        path = base
        for part in rel_path.split("/"):
            if not part:
                continue
            path += "/" + part
            try:
                self.sftp.mkdir(path)
            except OSError:
                pass

    def remove_dir(self, rel_path):
        self._remove_abs(self._abs(rel_path))

    def listdir_attr(self, rel_path):
        return self.sftp.listdir_attr(self._abs(rel_path))

    def remove_file(self, rel_path):
        self.sftp.remove(self._abs(rel_path))

    def open(self, rel_path, mode="r"):
        return self.sftp.open(self._abs(rel_path), mode)

    def _abs(self, rel_path):
        return self.cfg["FTP_SERVER_DIR"].rstrip("/") + "/" + rel_path

    def _remove_abs(self, abs_path):
        try:
            entries = self.sftp.listdir_attr(abs_path)
        except IOError:
            return
        for entry in entries:
            child = abs_path.rstrip("/") + "/" + entry.filename
            if stat.S_ISDIR(entry.st_mode):
                self._remove_abs(child)
            else:
                try:
                    self.sftp.remove(child)
                except IOError as e:
                    try:
                        dir_attr = self.sftp.lstat(abs_path)
                        dir_info = f"dir_uid={dir_attr.st_uid} dir_mode={oct(dir_attr.st_mode)}"
                    except Exception:
                        dir_info = "dir-stat-failed"
                    raise PermissionError(
                        f"sftp.remove({child!r}) "
                        f"file_uid={entry.st_uid} file_mode={oct(entry.st_mode)} "
                        f"{dir_info}: {e}"
                    ) from e
        try:
            self.sftp.rmdir(abs_path)
        except IOError as e:
            raise PermissionError(f"sftp.rmdir({abs_path!r}): {e}") from e

    def __exit__(self, *_):
        self.close()

    def close(self):
        self.sftp.close()
        transport = self._ssh.get_transport()
        if transport is not None and transport.is_active():
            transport.close()
        self._ssh.close()
