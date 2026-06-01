import argparse

from cli.py.mail.smtp_lib import SmtpClient, build_message
from cli.py.pipeline_cfg import PipelineCfg


def main() -> None:
    args = parse_args()
    cfg = PipelineCfg("runtime")
    message = build_message(
        cfg,
        to=args.to,
        subject="[SMTP-Smoke] Testmail lokal",
        body="Lokaler SMTP-Smoke-Test erfolgreich.",
    )
    with SmtpClient(cfg) as client:
        client.send_message(message)
    print(f"[smtp-smoke] gesendet an {message['To']}", flush=True)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Sendet eine Test-E-Mail über den konfigurierten SMTP-Server"
    )
    parser.add_argument("--to", help="Empfänger (Standard: MAIL_TO_EMAIL aus Config)")
    return parser.parse_args()


if __name__ == "__main__":
    main()
