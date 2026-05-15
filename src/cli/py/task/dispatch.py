import argparse
import sys

import requests
import requests.exceptions

from cli.py.task.task import Task
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

TASK_DIR = "var/tasks"
TASK_TRIGGER_PATH = "/tasks/dispatch"

TASK_SCHEMAS = {
    "cv_token_rotation": {"profile": "default", "count": "1"},
    "deploy_switch": {"app": "", "vendor": "", "run_id": ""},
}


def _default_log(message):
    print(f"[dispatch] {message}", flush=True)


class TaskDispatch:
    def __init__(self, cfg, logger=_default_log):
        self._deploy = cfg
        self._log = logger

    def submit(self, task: Task) -> None:
        with SftpClient(self._deploy) as client:
            self._enqueue(client, task)
        self._http_trigger()

    def _enqueue(self, client, task: Task) -> None:
        rel_path = f"{TASK_DIR}/{task.filename()}"
        client.ensure_dir(TASK_DIR)
        client.put_text(rel_path, task.to_ini())
        self._log(f"Aufgabe via SFTP geschrieben: {rel_path}")

    def _http_trigger(self) -> None:
        root_url = self._deploy.get("APP_ROOT_URL", "").rstrip("/")
        if not root_url:
            return
        url = _with_scheme(root_url) + TASK_TRIGGER_PATH
        try:
            resp = requests.get(url, timeout=10)
            resp.raise_for_status()
            self._log(f"HTTP-Auslöser: {url} → {resp.status_code}")
        except requests.exceptions.HTTPError as exc:
            _log_http_error(url, exc)
            raise
        except requests.exceptions.RequestException as exc:
            _log_request_error(url, exc)
            raise


def _with_scheme(url: str) -> str:
    if url.startswith(("http://", "https://")):
        return url
    return "https://" + url


def _log_http_error(url: str, exc: requests.exceptions.HTTPError) -> None:
    status = exc.response.status_code if exc.response is not None else "?"
    body = _truncate(exc.response.text if exc.response is not None else "")
    print(
        f"[dispatch] HTTP-Auslöser fehlgeschlagen: {url}\n"
        f"  Status: {status}\n"
        f"  Body: {body}",
        file=sys.stderr, flush=True,
    )


def _log_request_error(url: str, exc: requests.exceptions.RequestException) -> None:
    print(
        f"[dispatch] HTTP-Auslöser nicht erreichbar: {url}\n"
        f"  Fehler: {exc}",
        file=sys.stderr, flush=True,
    )


def _truncate(text: str, limit: int = 300) -> str:
    return text[:limit] + "..." if len(text) > limit else text


def main() -> None:
    args = parse_args()
    cfg = PipelineCfg("deploy")
    task = Task(args.task_type, _build_params(args))
    TaskDispatch(cfg).submit(task)


def _build_params(args: argparse.Namespace) -> dict:
    schema = dict(TASK_SCHEMAS[args.task_type])
    for key, value in vars(args).items():
        if key != "task_type" and value is not None:
            schema[key] = str(value)
    return schema


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Task anmelden")
    parser.add_argument("task_type", choices=list(TASK_SCHEMAS))
    parser.add_argument("--profile", help="Token-Profil (cv_token_rotation)")
    parser.add_argument("--count", type=int, help="Anzahl Token (cv_token_rotation)")
    parser.add_argument("--app", help="App-Slot (deploy_switch)")
    parser.add_argument("--run-id", dest="run_id", help="Lauf-ID (deploy_switch)")
    parser.add_argument("--vendor", help="Vendor-Slot (deploy_switch)")
    return parser.parse_args()


if __name__ == "__main__":
    main()
