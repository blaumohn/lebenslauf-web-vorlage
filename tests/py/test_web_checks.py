import subprocess
import textwrap
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]


def run_web_check(script: str) -> subprocess.CompletedProcess:
    return subprocess.run(
        ["bash", "-c", textwrap.dedent(script)],
        cwd=REPO_ROOT,
        capture_output=True,
        text=True,
    )


class WebChecksTest(unittest.TestCase):
    def test_header_smoke_success_bleibt_knapp(self):
        result = run_web_check(
            r"""
            . scripts/web_checks.sh
            curl() {
              printf 'HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n<html>ok</html>'
            }
            smoke_http_header_contains http://example.test/ content-type text/html
            """
        )

        self.assertEqual(0, result.returncode)
        self.assertIn("[smoke] HTTP-Header: http://example.test/ content-type", result.stderr)
        self.assertNotIn("<html>ok</html>", result.stderr)

    def test_header_smoke_gibt_antwort_bei_http_fehler_aus(self):
        result = run_web_check(
            r"""
            . scripts/web_checks.sh
            curl() {
              printf 'HTTP/1.1 429 Too Many Requests\r\nContent-Type: text/html\r\n\r\n<html>Zu viele Anfragen</html>'
              return 22
            }
            smoke_http_header_contains http://example.test/contact content-type text/html
            """
        )

        self.assertEqual(1, result.returncode)
        self.assertIn("[smoke] Header-Abruf fehlgeschlagen: http://example.test/contact", result.stderr)
        self.assertIn("<html>Zu viele Anfragen</html>", result.stderr)

    def test_header_smoke_akzeptiert_body_nicht_als_header(self):
        result = run_web_check(
            r"""
            . scripts/web_checks.sh
            header_list_contains $'HTTP/1.1 200 OK\r\nServer: test\r\n' content-type text/html
            """
        )

        self.assertEqual(1, result.returncode)

    def test_page_smoke_gibt_body_bei_http_fehler_aus(self):
        result = run_web_check(
            r"""
            . scripts/web_checks.sh
            curl() {
              printf '<html>Zu viele Anfragen</html>'
              return 22
            }
            smoke_http_page_contains http://example.test/contact '<form'
            """
        )

        self.assertEqual(1, result.returncode)
        self.assertIn("[smoke] HTTP-Abruf fehlgeschlagen: http://example.test/contact", result.stderr)
        self.assertIn("<html>Zu viele Anfragen</html>", result.stderr)

    def test_a11y_artefakt_test_schreibt_override_und_stellt_config_wieder_her(self):
        result = run_web_check(
            r"""
            . scripts/web_checks.sh
            PIPELINE=preview
            calls=()
            cli() { calls+=("$*"); }
            cp() { calls+=("cp $*"); }
            with_dev_server() { calls+=("server $*"); }
            run_html_quality_checks() { calls+=("html $*"); }
            run_artifact_html_accessibility_checks /tmp/deploy
            printf '%s\n' "${calls[@]}"
            """
        )

        self.assertEqual(0, result.returncode)
        self.assertEqual(
            [
                "html /tmp/deploy/var/cache/html",
                'build preview config --overrides {"CAPTCHA_MAX_GET":"10"}',
                "cp -a var/config /tmp/deploy/var/",
                "server /tmp/deploy/public run_accessibility_checks",
                "build preview config",
                "cp -a var/config /tmp/deploy/var/",
            ],
            result.stdout.strip().splitlines(),
        )

    def test_a11y_artefakt_test_stellt_config_nach_fehler_wieder_her(self):
        result = run_web_check(
            r"""
            . scripts/web_checks.sh
            PIPELINE=preview
            calls=()
            cli() { calls+=("$*"); }
            cp() { calls+=("cp $*"); }
            with_dev_server() { calls+=("server $*"); return 7; }
            run_html_quality_checks() { calls+=("html $*"); }
            run_artifact_html_accessibility_checks /tmp/deploy
            status=$?
            printf '%s\n' "${calls[@]}"
            exit "$status"
            """
        )

        self.assertEqual(7, result.returncode)
        self.assertIn("build preview config\ncp -a var/config /tmp/deploy/var/", result.stdout)


if __name__ == "__main__":
    unittest.main()
