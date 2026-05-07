#!/usr/bin/env python3

import json
import os
import smtplib
import ssl
from email.message import EmailMessage


REQUIRED_KEYS = [
    "SMTP_HOST",
    "SMTP_PORT",
    "SMTP_USER",
    "SMTP_PASS",
    "SMTP_ENCRYPTION",
    "SMTP_FROM_EMAIL",
    "SMTP_FROM_NAME",
    "MAIL_TO_EMAIL",
]


def main():
    config = read_config()
    assert_required(config)
    assert_bad_password_rejected(config)
    send_test_mail(config)
    print("[smtp-smoke] SMTP OK")


def read_config():
    return json.loads(os.environ["SMTP_CFG_JSON"])


def assert_required(config):
    missing = [key for key in REQUIRED_KEYS if not str(config.get(key, ""))]
    if missing:
        raise RuntimeError("SMTP-Konfig fehlt: " + ", ".join(missing))


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


def build_message(config):
    message = EmailMessage()
    from_name = config["SMTP_FROM_NAME"]
    message["From"] = f"{from_name} <{config['SMTP_FROM_EMAIL']}>"
    message["To"] = config["MAIL_TO_EMAIL"]
    message["Subject"] = "[SMTP-Smoke] Testmail"
    message.set_content("SMTP-Smoke-Test erfolgreich.")
    return message


if __name__ == "__main__":
    main()
