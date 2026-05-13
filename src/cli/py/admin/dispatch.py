import argparse
import urllib.request
from pathlib import Path

from cli.py.admin.task import AdminTask
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

ADMIN_TASK_DIR = "var/admin/tasks"
ADMIN_TRIGGER_PATH = "/admin/run"

TASK_SCHEMAS = {
    "cv_token_rotation": {"profile": "default", "count": "1"},
    "deploy_switch": {"app": "", "vendor": "", "run_id": ""},
}


class AdminDispatch:
    def __init__(self, cfg: PipelineCfg):
        self._deploy = cfg

    def submit(self, task: AdminTask) -> None:
        with SftpClient(self._deploy) as client:
            enqueue_with_client(client, task)
        self._http_trigger()

    def _http_trigger(self) -> None:
        root_url = self._deploy.get("APP_ROOT_URL", "").rstrip("/")
        if not root_url:
            return
        url = root_url + ADMIN_TRIGGER_PATH
        req = urllib.request.Request(url, method="GET")
        with urllib.request.urlopen(req, timeout=10) as resp:
            print(f"[dispatch] HTTP-Auslöser: {url} → {resp.status}", flush=True)


def enqueue_with_client(client, task: AdminTask) -> None:
    rel_path = f"{ADMIN_TASK_DIR}/{task.filename()}"
    client.ensure_dir(ADMIN_TASK_DIR)
    client.put_text(rel_path, task.to_ini())
    print(f"[dispatch] Aufgabe via SFTP geschrieben: {rel_path}", flush=True)


def main() -> None:
    args = parse_args()
    cfg = PipelineCfg("deploy")
    task = AdminTask(args.task_type, _build_params(args))
    AdminDispatch(cfg).submit(task)


def _build_params(args: argparse.Namespace) -> dict:
    schema = dict(TASK_SCHEMAS[args.task_type])
    for key, value in vars(args).items():
        if key != "task_type" and value is not None:
            schema[key] = str(value)
    return schema


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Admin-Task anmelden")
    parser.add_argument("task_type", choices=list(TASK_SCHEMAS))
    parser.add_argument("--profile", help="Token-Profil (cv_token_rotation)")
    parser.add_argument("--count", type=int, help="Anzahl Token (cv_token_rotation)")
    parser.add_argument("--app", help="App-Slot (deploy_switch)")
    parser.add_argument("--run-id", dest="run_id", help="Deploy-Lauf-ID (deploy_switch)")
    parser.add_argument("--vendor", help="Vendor-Slot (deploy_switch)")
    return parser.parse_args()


if __name__ == "__main__":
    main()
