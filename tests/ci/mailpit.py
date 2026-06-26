import time
from typing import Any

import requests

MAILPIT_API_URL = "http://mailpit:8025"


class MailpitClient:
    def __init__(self, api_url: str = MAILPIT_API_URL):
        self.api_url = api_url.rstrip("/")

    def message_total(self) -> int:
        return int(self._message_index()["total"])

    def messages(self) -> list[dict[str, Any]]:
        return list(self._message_index()["messages"])

    def latest_message(self) -> dict[str, Any]:
        messages = self.messages()
        if not messages:
            raise RuntimeError("[mailpit] Keine Nachricht gefunden")
        return messages[0]

    def wait_for_new_message(self, total_before: int, timeout_s: int = 10) -> dict[str, Any]:
        deadline = time.monotonic() + timeout_s
        while time.monotonic() < deadline:
            if self.message_total() > total_before:
                return self.latest_message()
            time.sleep(0.5)
        raise RuntimeError("[mailpit] Keine neue Nachricht empfangen")

    def message_body(self, message: dict[str, Any]) -> str:
        details = self.message_details(message)
        for field in ("Text", "HTML", "Body", "Snippet"):
            value = details.get(field) or message.get(field)
            if value:
                return str(value)
        raise RuntimeError("[mailpit] Nachricht hat keinen lesbaren Body")

    def message_details(self, message: dict[str, Any]) -> dict[str, Any]:
        message_id = str(message.get("ID") or message.get("Id") or "")
        if message_id == "":
            return message
        response = requests.get(f"{self.api_url}/api/v1/message/{message_id}", timeout=10)
        response.raise_for_status()
        return dict(response.json())

    def _message_index(self) -> dict[str, Any]:
        response = requests.get(f"{self.api_url}/api/v1/messages", timeout=10)
        response.raise_for_status()
        return dict(response.json())


def message_subject(message: dict[str, Any]) -> str:
    return str(message.get("Subject", ""))


def message_to_address(message: dict[str, Any]) -> str:
    to_list = message.get("To") or [{}]
    return str(to_list[0].get("Address", ""))
