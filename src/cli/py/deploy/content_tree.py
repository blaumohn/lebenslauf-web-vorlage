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
        path = remote_path(remote_root, relative_path)
        if is_remote_dir(client, entry, path):
            yield from iter_remote_files(client, remote_root, relative_path)
        else:
            yield path, relative_path


def is_remote_dir(client: SftpClient, entry, path: str) -> bool:
    if entry.st_mode is not None:
        return _is_dir_from_mode_bits(entry.st_mode)
    return _is_dir_by_listing_probe(client, path)


def _is_dir_from_mode_bits(mode: int) -> bool:
    return stat.S_ISDIR(mode)


def _is_dir_by_listing_probe(client: SftpClient, path: str) -> bool:
    try:
        client.listdir_attr(path)
        return True
    except IOError:
        return False


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
