import json
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from pathlib import Path

import jsonschema

_SCHEMA_PATH = (
    Path(__file__).parent.parent.parent.parent
    / "resources/schemas/deploy-history-entry.schema.json"
)
_SCHEMA = json.loads(_SCHEMA_PATH.read_text(encoding="utf-8"))

HISTORY_PATH = "log/deploy-history.ndjson"


@dataclass(frozen=True)
class DeployHistoryEntry:
    source: str
    event: str
    run_id: str
    ts: str = field(default_factory=lambda: _now_utc())
    task_id: str = ""
    target_app: str = ""
    target_vendor: str = ""
    app_before: str = ""
    vendor_before: str = ""
    app_after: str = ""
    vendor_after: str = ""
    outcome: str = ""

    def to_json(self) -> str:
        data = {k: v for k, v in asdict(self).items() if v}
        jsonschema.validate(data, _SCHEMA)
        return json.dumps(data, ensure_ascii=False)


class DeployHistoryWriter:
    def __init__(self, client, source: str, run_id: str, logger=None):
        self._client = client
        self._source = source
        self._run_id = run_id
        self._log = logger

    def record(self, event: str, **kwargs) -> None:
        try:
            entry = DeployHistoryEntry(
                source=self._source,
                event=event,
                run_id=self._run_id,
                **kwargs,
            )
            self._client.ensure_dir("log")
            self._client.append_line(HISTORY_PATH, entry.to_json())
        except Exception as exc:
            if self._log:
                self._log(f"History-Schreiben fehlgeschlagen: {exc}")


def _now_utc() -> str:
    return (
        datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    )
