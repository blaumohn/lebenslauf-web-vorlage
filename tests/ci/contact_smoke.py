import json
import sys
from html.parser import HTMLParser

import requests

from cli.py.deploy.exceptions import DeployConflictError
from cli.py.deploy.slot_store import SlotStore
from cli.py.deploy.sftp_lib import SftpClient
from cli.py.pipeline_cfg import PipelineCfg

MAILPIT_API_URL = "http://mailpit:8025"


class CaptchaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.captcha_id = ""

    def handle_starttag(self, tag, attrs):
        if tag != "input":
            return
        values = dict(attrs)
        if values.get("name") == "captcha_id":
            self.captcha_id = values.get("value", "")


def main() -> int:
    try:
        deploy_cfg = PipelineCfg("deploy")
        runtime_cfg = PipelineCfg("runtime")
        smoke = ContactSmoke(deploy_cfg, runtime_cfg)
        smoke.run()
        return 0
    except (KeyError, RuntimeError, requests.exceptions.RequestException) as exc:
        print(str(exc), file=sys.stderr)
        return 1


class ContactSmoke:
    def __init__(self, deploy_cfg, runtime_cfg):
        self.deploy_cfg = deploy_cfg
        self.runtime_cfg = runtime_cfg
        self.root_url = deploy_cfg["APP_ROOT_URL"].rstrip("/")
        if not self.root_url:
            raise RuntimeError("[contact-smoke] APP_ROOT_URL fehlt")
        self.lang = runtime_cfg["CONTENT_LANGS"].split(",")[0]

    def run(self) -> None:
        with SftpClient(self.deploy_cfg) as sftp:
            captcha_id = self.fetch_captcha_id()
            app_slot = self.read_active_app_slot(sftp)
            solution = self.read_captcha_solution(sftp, app_slot, captcha_id)
            total_before = self.mailpit_message_total()
            self.submit_contact_form(captcha_id, solution)
            total_after = self.mailpit_message_total()

        self.assert_mail_received(total_before, total_after)
        self.assert_contact_mail_content()
        print("[contact-smoke] OK: Formular-Mail empfangen")

    def fetch_captcha_id(self) -> str:
        response = requests.get(
            f"{self.root_url}/contact",
            headers={"Accept-Language": self.lang},
            timeout=10,
        )
        response.raise_for_status()
        captcha_id = extract_captcha_id(response.text)
        if not captcha_id:
            raise RuntimeError("[contact-smoke] Captcha-ID nicht gefunden")
        return captcha_id

    def read_active_app_slot(self, sftp) -> str:
        try:
            slot_map = SlotStore(sftp).current_slot_map()
        except DeployConflictError as e:
            raise RuntimeError(f"[contact-smoke] Aktiver App-Slot fehlt: {e}")
        if slot_map is None:
            raise RuntimeError("[contact-smoke] Aktiver App-Slot fehlt")
        return slot_map.app.label

    def read_captcha_solution(self, sftp, app_slot: str, captcha_id: str) -> str:
        path = f"app-{app_slot}/var/tmp/captcha/{captcha_id}.json"
        raw = sftp.read_file(path)
        if not raw:
            raise RuntimeError(
                "[contact-smoke] Captcha-State fehlt: "
                f"{captcha_id} ({path})"
            )
        data = json.loads(raw)
        solution = str(data.get("solution_text", ""))
        if not solution:
            raise RuntimeError(f"[contact-smoke] Captcha-Lösung fehlt: {captcha_id}")
        return solution

    def submit_contact_form(self, captcha_id: str, solution: str) -> None:
        response = requests.post(
            f"{self.root_url}/contact",
            headers={"Accept-Language": self.lang},
            data={
                "name": "CI Test",
                "email": "ci@ci.invalid",
                "message": "Testformular aus CI",
                "captcha_id": captcha_id,
                "captcha_answer": solution,
            },
            timeout=10,
        )
        if response.status_code != 200:
            raise RuntimeError(
                f"[contact-smoke] Formular-POST fehlgeschlagen "
                f"(HTTP {response.status_code})"
            )

    def mailpit_message_total(self) -> int:
        response = requests.get(f"{MAILPIT_API_URL}/api/v1/messages", timeout=10)
        response.raise_for_status()
        return int(response.json()["total"])

    def assert_mail_received(self, total_before: int, total_after: int) -> None:
        if total_after > total_before:
            return
        raise RuntimeError("[contact-smoke] Keine neue Mail nach Formular-Submit")

    def assert_contact_mail_content(self) -> None:
        subject, to_address = self.latest_mail_subject_and_to()
        expected_to = self.runtime_cfg["MAIL_TO_EMAIL"]
        if "/Contact]" not in subject:
            raise RuntimeError(f"[contact-smoke] Unerwartetes Mail-Subjekt: {subject!r}")
        if to_address != expected_to:
            raise RuntimeError(f"[contact-smoke] Unerwarteter Empfänger: {to_address!r}")

    def latest_mail_subject_and_to(self) -> tuple[str, str]:
        response = requests.get(f"{MAILPIT_API_URL}/api/v1/messages", timeout=10)
        response.raise_for_status()
        messages = response.json()["messages"]
        if not messages:
            raise RuntimeError("[contact-smoke] Keine Mailpit-Nachricht gefunden")
        message = messages[0]
        to_list = message.get("To") or [{}]
        return str(message.get("Subject", "")), str(to_list[0].get("Address", ""))


def extract_captcha_id(html: str) -> str:
    parser = CaptchaParser()
    parser.feed(html)
    return parser.captcha_id


if __name__ == "__main__":
    sys.exit(main())
