import argparse
import logging

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

logger = logging.getLogger(__name__)


class TaskDispatch:
    def __init__(self, cfg):
        self._deploy = cfg

    def submit(self, task: Task) -> None:
        with SftpClient(self._deploy) as client:
            self._enqueue(client, task)
        self._http_trigger()

    def _enqueue(self, client, task: Task) -> None:
        rel_path = f"{TASK_DIR}/{task.filename()}"
        client.ensure_dir(TASK_DIR)
        client.put_text(rel_path, task.to_ini())
        logger.info("Aufgabe via SFTP geschrieben: %s", rel_path)

    def _http_trigger(self) -> None:
        root_url = self._deploy.get("APP_ROOT_URL", "").rstrip("/")
        if not root_url:
            return
        url = _with_scheme(root_url) + TASK_TRIGGER_PATH
        try:
            resp = requests.get(url, timeout=10)
            resp.raise_for_status()
            logger.info("HTTP-Auslöser: %s → %s", url, resp.status_code)
        except requests.exceptions.HTTPError as exc:
            status = exc.response.status_code if exc.response is not None else "?"
            body = _truncate(exc.response.text if exc.response is not None else "")
            logger.error("HTTP-Auslöser fehlgeschlagen: %s\n  Status: %s\n  Body: %s", url, status, body)
            raise
        except requests.exceptions.RequestException as exc:
            logger.error("HTTP-Auslöser nicht erreichbar: %s\n  Fehler: %s", url, exc)
            raise


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
