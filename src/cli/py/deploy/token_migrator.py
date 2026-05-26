import stat


class RuntimeTokenMigrator:
    def __init__(self, client, logger):
        self.client = client
        self.log = logger

    def migrate(self, active_dir: str, inactive_dir: str) -> None:
        src = f"{active_dir}/var/state/tokens"
        dst = f"{inactive_dir}/var/state/tokens"
        try:
            entries = self.client.listdir_attr(src)
        except OSError:
            return
        self._copy_entries(entries, src, dst)

    def _copy_entries(self, entries, src: str, dst: str) -> None:
        self.client.ensure_dir(dst)
        count = 0
        for entry in entries:
            count += self._copy_entry(entry, src, dst)
        if count:
            self.log(f"Tokens migriert: {count}")

    def _copy_entry(self, entry, src: str, dst: str) -> int:
        if not stat.S_ISREG(entry.st_mode):
            return 0
        with self.client.open(f"{src}/{entry.filename}", "rb") as file:
            data = file.read()
        self.client.put_bytes(f"{dst}/{entry.filename}", data)
        return 1
