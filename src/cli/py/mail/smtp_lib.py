import smtplib
import ssl
from email.message import EmailMessage
from email.utils import formataddr


def build_message(
    cfg,
    *,
    to: str | None = None,
    subject: str,
    body: str,
) -> EmailMessage:
    message = EmailMessage()
    fromAddr = formataddr((cfg["SMTP_FROM_NAME"], cfg["SMTP_FROM_EMAIL"]))
    message["From"] = fromAddr
    message["To"] = to or cfg["MAIL_TO_EMAIL"]
    message["Subject"] = subject
    message.set_content(body)
    return message


class SmtpClient:
    def __init__(self, cfg, cafile: str | None = None):
        self._cfg = cfg
        self._smtp = self._open_connection(cafile)
        self._smtp.login(cfg["SMTP_USER"], cfg["SMTP_PASS"])

    def send_message(self, message: EmailMessage) -> None:
        self._smtp.send_message(message)

    def __enter__(self):
        return self

    def __exit__(self, *_):
        self.close()

    def close(self) -> None:
        self._smtp.quit()

    def _open_connection(self, cafile: str | None) -> smtplib.SMTP:
        cfg = self._cfg
        smtp = smtplib.SMTP(cfg["SMTP_HOST"], int(cfg["SMTP_PORT"]), timeout=10)
        smtp.ehlo()
        encryption = cfg["SMTP_ENCRYPTION"]
        if encryption == "tls":
            smtp.starttls(context=ssl.create_default_context(cafile=cafile))
            smtp.ehlo()
            return smtp
        if encryption == "none":
            return smtp
        smtp.quit()
        raise RuntimeError(f"SMTP_ENCRYPTION unbekannt: {encryption!r} (erwartet: tls oder none)")
