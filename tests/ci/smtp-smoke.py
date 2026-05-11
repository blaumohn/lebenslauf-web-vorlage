#!/usr/bin/env python3

import json
import os
import smtplib
import ssl
import sys
import time
import urllib.request
from email.message import EmailMessage


MAILPIT_API_URL = "http://mailpit:8025"


def main():
    try:
        config = read_config()
        await_mailpit()
        assert_bad_password_rejected(config)
        send_test_mail(config)
        check_mail_received()
    except RuntimeError as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)


def read_config():
    data = json.loads(os.environ["PIPELINE_CFG_JSON"])
    return data.get("runtime", {})


def await_mailpit():
    url = f"{MAILPIT_API_URL}/api/v1/info"
    for _ in range(10):
        try:
            urllib.request.urlopen(url, timeout=2)
            return
        except Exception:
            time.sleep(1)
    raise RuntimeError(f"[smtp-smoke] Mailpit nicht erreichbar: {MAILPIT_API_URL}")


def assert_bad_password_rejected(config):
    try:
        with connect(config) as smtp:
            smtp.login(config["SMTP_USER"], "wrong-" + config["SMTP_PASS"])
    except smtplib.SMTPAuthenticationError:
        print("[smtp-smoke] Falsches SMTP-Passwort abgelehnt")
        return
    raise RuntimeError("Falsches SMTP-Passwort wurde akzeptiert.")


def send_test_mail(config):
    message = build_message(config)
    with connect(config) as smtp:
        smtp.login(config["SMTP_USER"], config["SMTP_PASS"])
        smtp.send_message(message)
    print("[smtp-smoke] Testmail gesendet.")


def build_message(config):
    message = EmailMessage()
    from_name = config["SMTP_FROM_NAME"]
    message["From"] = f"{from_name} <{config['SMTP_FROM_EMAIL']}>"
    message["To"] = config["MAIL_TO_EMAIL"]
    message["Subject"] = "[SMTP-Smoke] Testmail"
    message.set_content("SMTP-Smoke-Test erfolgreich.")
    return message


def check_mail_received():
    url = f"{MAILPIT_API_URL}/api/v1/messages"
    with urllib.request.urlopen(url) as resp:
        data = json.loads(resp.read())
    total = data.get("total", 0)
    if total < 1:
        raise RuntimeError(f"[smtp-smoke] Keine Mail empfangen (total={total})")
    print(f"[smtp-smoke] OK: {total} Mail(s)")


def connect(config):
    smtp = smtplib.SMTP(config["SMTP_HOST"], int(config["SMTP_PORT"]), timeout=10)
    smtp.ehlo()
    if config["SMTP_ENCRYPTION"] == "tls":
        smtp.starttls(context=tls_context())
        smtp.ehlo()
        return smtp
    if config["SMTP_ENCRYPTION"] == "none":
        return smtp
    smtp.close()
    raise RuntimeError("SMTP_ENCRYPTION erlaubt nur tls oder none.")


def tls_context():
    context = ssl.create_default_context(cafile="tests/ci/ca.crt")
    context.check_hostname = False
    return context


if __name__ == "__main__":
    main()
