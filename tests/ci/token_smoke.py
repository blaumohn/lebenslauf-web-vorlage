import os
import re
import sys

import requests
from plumbum import FG, local

from cli.py.deploy.sftp_lib import SftpClient
from cli.py.deploy.slot_store import SlotStore
from cli.py.pipeline_cfg import PipelineCfg
from cli.py.task.dispatch import TaskDispatch
from cli.py.task.task import Task
from mailpit import MailpitClient, message_subject

TOKEN_RE = re.compile(r"^[0-9a-f]{32}$")
PRIVATE_HTML_RE = re.compile(r"^cv-private-(?P<profile>.+)\.[^.]+\.html$")


def main() -> int:
    try:
        smoke = TokenSmoke(
            deploy_cfg=PipelineCfg("deploy"),
            runtime_cfg=PipelineCfg("runtime"),
            mailpit=MailpitClient(),
        )
        smoke.run()
        return 0
    except (KeyError, RuntimeError, requests.exceptions.RequestException) as exc:
        print(str(exc), file=sys.stderr)
        return 1


class TokenSmoke:
    def __init__(self, deploy_cfg, runtime_cfg, mailpit: MailpitClient):
        self.deploy_cfg = deploy_cfg
        self.runtime_cfg = runtime_cfg
        self.mailpit = mailpit

    def run(self) -> None:
        profiles = self.private_profiles()
        for profile in profiles:
            token = self.rotate_and_read_token(profile)
            print(f"[token-smoke] Token aus Task-Mail gelesen: {token}", file=sys.stderr)
            self.run_playwright(profile, token)

    def private_profiles(self) -> list[str]:
        with SftpClient(self.deploy_cfg) as client:
            slot_map = SlotStore(client).require_current_slot_map()
            cache_dir = f"{slot_map.app.dir}/var/cache/html"
            profiles = profiles_from_cache_entries(
                entry.filename for entry in client.listdir_attr(cache_dir)
            )
        if not profiles:
            raise RuntimeError("[token-smoke] Keine privaten CV-Profile im HTML-Cache gefunden")
        return profiles

    def rotate_and_read_token(self, profile: str) -> str:
        total_before = self.mailpit.message_total()
        TaskDispatch(self.deploy_cfg).submit(
            Task("cv_token_add", {"profile": profile, "count": "1"})
        )
        message = self.mailpit.wait_for_new_message(total_before)
        subject = message_subject(message)
        assert_token_subject(subject)
        return extract_token(self.mailpit.message_body(message))

    def run_playwright(self, profile: str, token: str) -> None:
        env = {
            **os.environ,
            "PLAYWRIGHT_BASE_URL": self.deploy_cfg["APP_ROOT_URL"],
            "CI_TOKEN": token,
            "CI_PROFILE": profile,
            "CONTENT_LANGS": self.runtime_cfg["CONTENT_LANGS"],
        }
        local["npm"].with_env(**env)["run", "qa:smoke:token"] & FG


def profiles_from_cache_entries(entries) -> list[str]:
    profiles = set()
    for entry in entries:
        match = PRIVATE_HTML_RE.match(str(entry))
        if match:
            profiles.add(match.group("profile"))
    return sorted(profiles)


def assert_token_subject(subject: str) -> None:
    if (
        "/Task]" not in subject
        or "Task abgeschlossen" not in subject
        or "cv_token_add" not in subject
    ):
        raise RuntimeError(f"[token-smoke] Unerwartetes Mail-Subjekt: {subject!r}")


def extract_token(body: str) -> str:
    tokens = [line.strip() for line in body.splitlines() if TOKEN_RE.match(line.strip())]
    if len(tokens) != 1:
        raise RuntimeError(f"[token-smoke] Erwartet genau einen Token, gefunden: {len(tokens)}")
    return tokens[0]


if __name__ == "__main__":
    sys.exit(main())
