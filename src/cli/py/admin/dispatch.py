#!/usr/bin/env python3
import argparse
import json
import os
import urllib.request
from pathlib import Path

from cli.py.admin.task import AdminTask

ADMIN_TASK_DIR = "var/admin/tasks"
ADMIN_TRIGGER_PATH = "/admin/run-tasks"

TASK_SCHEMAS = {
    "cv_token_rotation": {"profile": "default", "count": "1"},
    "deploy_switch": {"prepared_state": ""},
}


class AdminDispatch:
    def __init__(self, pipeline_cfg: dict):
        self._deploy = pipeline_cfg.get("deploy", {})

    def enqueue(self, task: AdminTask) -> None:
        if self._deploy.get("SFTP_HOST"):
            self._enqueue_sftp(task)
        else:
            self._enqueue_local(task)
        self._http_trigger()

    def _enqueue_sftp(self, task: AdminTask) -> None:
        from cli.py.deploy.sftp_lib import SftpClient
        with SftpClient(self._deploy) as client:
            enqueue_with_client(client, task)

    def _enqueue_local(self, task: AdminTask) -> None:
        path = Path(ADMIN_TASK_DIR) / task.filename()
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(task.to_ini(), encoding="utf-8")
        print(f"[dispatch] Aufgabe lokal geschrieben: {path}", flush=True)

    def _http_trigger(self) -> None:
        root_url = self._deploy.get("APP_ROOT_URL", "").rstrip("/")
        if not root_url:
            return
        url = root_url + ADMIN_TRIGGER_PATH
        req = urllib.request.Request(url, method="POST")
        with urllib.request.urlopen(req, timeout=10) as resp:
            print(f"[dispatch] HTTP-Auslöser: {url} → {resp.status}", flush=True)


def enqueue_with_client(client, task: AdminTask) -> None:
    rel_path = f"{ADMIN_TASK_DIR}/{task.filename()}"
    client.ensure_dir(ADMIN_TASK_DIR)
    client.put_text(rel_path, task.to_ini())
    print(f"[dispatch] Aufgabe via SFTP geschrieben: {rel_path}", flush=True)


def main() -> None:
    args = parse_args()
    cfg = json.loads(os.environ.get("PIPELINE_CFG_JSON", "{}"))
    task = AdminTask(args.task_type, _build_params(args))
    AdminDispatch(cfg).enqueue(task)


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
    parser.add_argument("--prepared-state", dest="prepared_state",
                        help="Vorbereiteter Zustand (deploy_switch)")
    return parser.parse_args()


if __name__ == "__main__":
    main()
