import configparser
import io
import uuid
from datetime import datetime, timezone


class Task:
    def __init__(self, task_type: str, params: dict):
        self.type = task_type
        self.params = params
        self.task_id = uuid.uuid4().hex

    def to_ini(self) -> str:
        config = configparser.ConfigParser()
        config["task"] = {"type": self.type, "task_id": self.task_id, **self.params}
        out = io.StringIO()
        config.write(out)
        return out.getvalue()

    def filename(self) -> str:
        timestamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        return f"{timestamp}-{self.type}.ini"
