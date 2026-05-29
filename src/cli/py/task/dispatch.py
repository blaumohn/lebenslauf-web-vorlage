import argparse
import logging

import requests
import requests.exceptions

from cli.py.task.task import Task
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.deploy.slot_store import SlotStore
from cli.py.pipeline_cfg import PipelineCfg
from cli.py.util.poll import poll_until

_TASK_SUBDIR = "var/tasks"
_RESULT_SUBDIR = "var/tasks/results"
TASK_TRIGGER_PATH = "/tasks/dispatch"
POLL_INTERVAL_S = 2
POLL_TIMEOUT_S = 60

TASK_SCHEMAS = {
    "cv_token_rotation": {"profile": "default", "count": "1"},
    "deploy_switch": {"app": "", "vendor": "", "run_id": ""},
}

_logger = logging.getLogger(__name__)


class TaskDispatch:
    def __init__(self, cfg, logger=None):
        self._deploy = cfg
        self._log = logger if logger is not None else _logger.info

    def http_reachable(self) -> bool:
        root_url = self._deploy.get("APP_ROOT_URL", "").rstrip("/")
        if not root_url:
            return False
        try:
            requests.head(_with_scheme(root_url), timeout=5)
            return True
        except requests.exceptions.RequestException:
            return False

    def submit(self, task: Task) -> None:
        with SftpClient(self._deploy) as client:
            app_root = self._resolve_app_root(client)
            self._enqueue(client, task, app_root)
            self._http_trigger()
            self._await_result(client, task, app_root)

    def _resolve_app_root(self, client) -> str:
        slot_map = SlotStore(client).current_slot_map()
        if slot_map is None:
            raise RuntimeError("Kein aktiver Slot — TaskDispatch nicht möglich")
        return slot_map.app.dir

    def _enqueue(self, client, task: Task, app_root: str) -> None:
        task_dir = f"{app_root}/{_TASK_SUBDIR}"
        rel_path = f"{task_dir}/{task.filename()}"
        client.ensure_dir(task_dir)
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
            status = exc.response.status_code if exc.response is not None else "?"
            body = _truncate(exc.response.text if exc.response is not None else "")
            _logger.error("HTTP-Auslöser fehlgeschlagen: %s\n  Status: %s\n  Body: %s", url, status, body)
            raise
        except requests.exceptions.RequestException as exc:
            _logger.error("HTTP-Auslöser nicht erreichbar: %s\n  Fehler: %s", url, exc)
            raise

    def _await_result(self, client, task: Task, app_root: str) -> None:
        result_path = f"{app_root}/{_RESULT_SUBDIR}/{task.task_id}.result"

        def check_result():
            return self._check_result(client, result_path)

        poll_until(check_result, timeout_s=POLL_TIMEOUT_S, interval_s=POLL_INTERVAL_S,
                   label=task.task_id[:8])
        self._log(f"Task bestätigt: {task.task_id[:8]}")

    def _check_result(self, client, result_path: str) -> str | None:
        content = client.read_file(result_path).strip()
        if content == "ok":
            return content
        if content:
            raise RuntimeError(f"Task fehlgeschlagen: {content}")
        return None


def _with_scheme(url: str) -> str:
    if url.startswith(("http://", "https://")):
        return url
    return "https://" + url


def _truncate(text: str, limit: int = 300) -> str:
    return text[:limit] + "..." if len(text) > limit else text


def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(message)s")
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
