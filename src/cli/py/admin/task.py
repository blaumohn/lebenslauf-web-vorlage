import configparser
import io
from datetime import datetime, timezone


class AdminTask:
    def __init__(self, task_type: str, params: dict):
        self.type = task_type
        self.params = params

    def to_ini(self) -> str:
        config = configparser.ConfigParser()
        config["task"] = {"type": self.type, **self.params}
        out = io.StringIO()
        config.write(out)
        return out.getvalue()

    def filename(self) -> str:
        timestamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        return f"{timestamp}-{self.type}.ini"
