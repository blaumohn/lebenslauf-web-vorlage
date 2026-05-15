import argparse
import sys
import urllib.error
import urllib.request
from pathlib import Path

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
    def __init__(self, cfg: PipelineCfg, logger=_default_log):
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
        url = root_url + TASK_TRIGGER_PATH
        req = urllib.request.Request(url, method="GET")
        try:
            with urllib.request.urlopen(req, timeout=10) as resp:
                self._log(f"HTTP-Auslöser: {url} → {resp.status}")
        except urllib.error.HTTPError as exc:
            body = _truncate(exc.read().decode(errors="replace"))
            print(
                f"[dispatch] HTTP-Auslöser fehlgeschlagen: {url}\n"
                f"  Status: {exc.code}\n"
                f"  Body: {body}",
                file=sys.stderr, flush=True,
            )
            raise
        except urllib.error.URLError as exc:
            print(
                f"[dispatch] HTTP-Auslöser nicht erreichbar: {url}\n"
                f"  Fehler: {exc.reason}",
                file=sys.stderr, flush=True,
            )
            raise


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
    parser.add_argument("--run-id", dest="run_id", help="Deploy-Lauf-ID (deploy_switch)")
    parser.add_argument("--vendor", help="Vendor-Slot (deploy_switch)")
    return parser.parse_args()


if __name__ == "__main__":
    main()
