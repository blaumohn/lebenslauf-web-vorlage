import os
import subprocess
from pathlib import Path

import requests
import requests.exceptions

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.deploy.slot_store import SlotStore
from cli.py.deploy.tree_uploader import SftpTreeUploader
from cli.py.pipeline_cfg import PipelineCfg
from cli.py.task.dispatch import TaskDispatch
from cli.py.task.task import Task
from cli.py.util.log import Logger

_LOCAL_HTML_PATH = Path("var/cache/html")
_STAGING_SUBPATH = "var/tmp/html-publish"


def main() -> None:
    log = Logger("publish")
    cfg = PipelineCfg("deploy")
    log(f"Verbinde zu {_format_target(cfg)}")
    _html_quality_check(log)
    _upload_and_dispatch(cfg, log)
    _smoke_check(cfg, log)
    _accessibility_check(cfg, log)


def _html_quality_check(log) -> None:
    log("Prüfe lokalen HTML-Cache")
    subprocess.run(["npm", "run", "qa:html"], check=True)


def _upload_and_dispatch(cfg, log) -> None:
    with SftpClient(cfg) as client:
        slot = SlotStore(client).current_slot_map()
        if slot is None:
            raise RuntimeError("Kein aktiver Slot — publish nicht möglich")
        staging = f"{slot.app.dir}/{_STAGING_SUBPATH}"
        log(f"Lade HTML-Cache nach {staging}")
        SftpTreeUploader(client, log).upload_dir(_LOCAL_HTML_PATH, staging)
        TaskDispatch(cfg, log).submit(Task("cv_publish", {}))


def _smoke_check(cfg, log) -> None:
    url = cfg.get("APP_ROOT_URL", "")
    if not url:
        log("Warnung: APP_ROOT_URL nicht konfiguriert — Smoke übersprungen")
        return
    resp = requests.get(url, timeout=10, allow_redirects=True)
    if resp.status_code != 200:
        raise RuntimeError(f"Smoke fehlgeschlagen: HTTP {resp.status_code} — {url}")
    if len(resp.text) < 500 or "<html" not in resp.text:
        raise RuntimeError(f"Smoke: Inhalt ungültig — {url}")
    log(f"Smoke bestanden: {url}")


def _accessibility_check(cfg, log) -> None:
    url = cfg.get("APP_ROOT_URL", "")
    if not url:
        log("Warnung: APP_ROOT_URL nicht konfiguriert — A11y-QA übersprungen")
        return
    env = os.environ.copy()
    env["PLAYWRIGHT_BASE_URL"] = url.rstrip("/")
    log(f"Prüfe A11y: {env['PLAYWRIGHT_BASE_URL']}")
    subprocess.run(["npm", "run", "qa:a11y"], check=True, env=env)


def _format_target(cfg) -> str:
    return f"{cfg['SFTP_HOST']}:{cfg['SFTP_PORT']}{cfg['SFTP_SERVER_DIR']}"


if __name__ == "__main__":
    main()
