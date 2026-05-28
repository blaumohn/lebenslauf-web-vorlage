import json
import logging
import os
import traceback
from contextlib import suppress
from datetime import datetime, timezone
from pathlib import Path


class Logger:
    def __init__(
        self,
        prefix: str,
        file_path: Path | None = None,
        level: int = logging.WARNING,
    ):
        self._prefix = prefix
        self._file_path = file_path
        self._handler = _PrefixedHandler(prefix, level)
        logging.root.addHandler(self._handler)
        if logging.root.level == logging.NOTSET:
            logging.root.setLevel(level)

    def __call__(self, message: str) -> None:
        print(self._format_info(message), flush=True)
        self._append_file(message)

    def error(self, exc: Exception) -> None:
        print(self._format_error(exc), flush=True)
        self._append_file(str(exc))

    def close(self) -> None:
        logging.root.removeHandler(self._handler)

    def _format_info(self, message: str) -> str:
        if os.environ.get("LOG_FORMAT") == "json":
            return json.dumps(
                {"prefix": self._prefix, "level": "info", "message": message}
            )
        return f"[{self._prefix}] {message}"

    def _format_error(self, exc: Exception) -> str:
        if os.environ.get("LOG_FORMAT") == "json":
            return json.dumps({
                "prefix": self._prefix,
                "level": "error",
                "type": type(exc).__name__,
                "message": str(exc),
            })
        tb = "".join(traceback.format_exception(exc))
        return f"[{self._prefix}] Fehler:\n{tb}"

    def _append_file(self, message: str) -> None:
        if not self._file_path:
            return
        with suppress(Exception):
            ts = datetime.now(timezone.utc).isoformat(timespec="seconds")
            with self._file_path.open("a") as f:
                f.write(f"{ts} {message}\n")


class _PrefixedHandler(logging.Handler):
    def __init__(self, prefix: str, level: int = logging.WARNING):
        super().__init__(level=level)
        self._prefix = prefix

    def emit(self, record: logging.LogRecord) -> None:
        with suppress(Exception):
            msg = record.getMessage()
            if record.levelno >= logging.WARNING:
                line = f"[{self._prefix}] {record.levelname}: {msg}"
            else:
                line = f"[{self._prefix}] {msg}"
            print(line, flush=True)
