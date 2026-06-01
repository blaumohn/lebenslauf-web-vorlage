import argparse
import json
import smtplib
import ssl
import sys
import time
import urllib.request

from cli.py.mail.smtp_lib import SmtpClient, build_message
from cli.py.pipeline_cfg import PipelineCfg

MAILPIT_API_URL = "http://mailpit:8025"
CI_CA_FILE = "tests/ci/ca.crt"


def main():
    args = parse_args()
    cafile = CI_CA_FILE if args.ci_ca_cert else None
    try:
        cfg = PipelineCfg("runtime")
        await_mailpit()
        assert_bad_password_rejected(cfg, cafile)
        send_test_mail(cfg, cafile)
        check_mail_received()
    except RuntimeError as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--ci-ca-cert", action="store_true", default=False)
    return parser.parse_args()


def await_mailpit():
    url = f"{MAILPIT_API_URL}/api/v1/info"
    for _ in range(10):
        try:
            urllib.request.urlopen(url, timeout=2)
            return
        except Exception:
            time.sleep(1)
    raise RuntimeError(f"[smtp-smoke] Mailpit nicht erreichbar: {MAILPIT_API_URL}")


def assert_bad_password_rejected(cfg, cafile):
    smtp = smtplib.SMTP(cfg["SMTP_HOST"], int(cfg["SMTP_PORT"]), timeout=10)
    smtp.ehlo()
    smtp.starttls(context=ssl.create_default_context(cafile=cafile))
    smtp.ehlo()
    try:
        smtp.login(cfg["SMTP_USER"], "wrong-" + cfg["SMTP_PASS"])
    except smtplib.SMTPAuthenticationError:
        print("[smtp-smoke] Falsches SMTP-Passwort abgelehnt")
        return
    finally:
        smtp.quit()
    raise RuntimeError("Falsches SMTP-Passwort wurde akzeptiert.")


def send_test_mail(cfg, cafile):
    message = build_message(cfg, subject="[SMTP-Smoke] Testmail", body="SMTP-Smoke-Test erfolgreich.")
    with SmtpClient(cfg, cafile=cafile) as client:
        client.send_message(message)
    print("[smtp-smoke] Testmail gesendet.")


def check_mail_received():
    url = f"{MAILPIT_API_URL}/api/v1/messages"
    with urllib.request.urlopen(url) as resp:
        data = json.loads(resp.read())
    total = data.get("total", 0)
    if total < 1:
        raise RuntimeError(f"[smtp-smoke] Keine Mail empfangen (total={total})")
    print(f"[smtp-smoke] OK: {total} Mail(s)")


if __name__ == "__main__":
    main()
