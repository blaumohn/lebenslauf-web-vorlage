import argparse
import smtplib
import sys

from cli.py.mail.smtp_lib import SmtpClient
from cli.py.pipeline_cfg import PipelineCfg

TAG = "[smtp-credentials-check]"
CI_CA_FILE = "tests/ci/ca.crt"


def main():
    args = parse_args()
    cafile = CI_CA_FILE if args.ci_ca_cert else None
    try:
        cfg = PipelineCfg("runtime")
        verify_smtp_auth(cfg, cafile)
        print(f"{TAG} OK: Anmeldedaten gültig")
    except RuntimeError as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--ci-ca-cert", action="store_true", default=False)
    return parser.parse_args()


def verify_smtp_auth(cfg, cafile):
    try:
        with SmtpClient(cfg, cafile=cafile) as _:
            pass
    except smtplib.SMTPAuthenticationError as exc:
        raise RuntimeError(f"{TAG} Auth fehlgeschlagen: {exc}") from exc
    except (smtplib.SMTPException, OSError) as exc:
        raise RuntimeError(f"{TAG} Verbindung fehlgeschlagen: {exc}") from exc


if __name__ == "__main__":
    main()
