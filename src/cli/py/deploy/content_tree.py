import stat
from pathlib import Path, PurePosixPath
from typing import Iterator

from cli.py.deploy.sftp_lib import SftpClient


def fetch_content_tree(client: SftpClient, remote_root: str, local_root: Path) -> int:
    total = 0
    for remote_file, relative_path in iter_remote_files(client, remote_root):
        local_file = local_root / Path(relative_path)
        local_file.parent.mkdir(parents=True, exist_ok=True)
        client.get_file(remote_file, local_file)
        total += 1
    return total


def upload_content_tree(client: SftpClient, source: Path, remote_root: str) -> int:
    total = 0
    for local_file, relative_path in iter_local_files(source):
        remote_file = posix_join(remote_root, relative_path)
        client.ensure_dir(str(PurePosixPath(remote_file).parent))
        client.put_file(local_file, remote_file)
        total += 1
    return total


def iter_remote_files(
    client: SftpClient,
    remote_root: str,
    relative_root: PurePosixPath = PurePosixPath(),
) -> Iterator[tuple[str, PurePosixPath]]:
    for entry in sorted(client.listdir_attr(remote_path(remote_root, relative_root)), key=filename):
        relative_path = relative_root / entry.filename
        if stat.S_ISDIR(entry.st_mode):
            yield from iter_remote_files(client, remote_root, relative_path)
        else:
            yield remote_path(remote_root, relative_path), relative_path


def iter_local_files(source: Path) -> Iterator[tuple[Path, PurePosixPath]]:
    for item in sorted(source.rglob("*")):
        if item.is_file():
            yield item, PurePosixPath(item.relative_to(source).as_posix())


def remote_path(remote_root: str, relative_path: PurePosixPath) -> str:
    if str(relative_path) == ".":
        return remote_root
    return posix_join(remote_root, relative_path)


def posix_join(root: str, relative_path: PurePosixPath) -> str:
    return f"{root.rstrip('/')}/{relative_path.as_posix()}"


def filename(entry) -> str:
    return entry.filename
