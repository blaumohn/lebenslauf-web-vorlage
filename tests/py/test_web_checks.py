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
    def test_smoke_checks_setzt_playwright_base_url(self):
        result = run_web_check(
            r"""
            . scripts/web_checks.sh
            PIPELINE=dev
            CONTENT_LANGS=de,es
            npm() { printf 'PLAYWRIGHT_BASE_URL=%s\n' "$PLAYWRIGHT_BASE_URL"; }
            cli() { :; }
            run_smoke_checks http://example.test
            """
        )

        self.assertEqual(0, result.returncode)
        self.assertIn("PLAYWRIGHT_BASE_URL=http://example.test", result.stdout)

    def test_smoke_checks_liest_content_langs_aus_config_wenn_nicht_gesetzt(self):
        result = run_web_check(
            r"""
            . scripts/web_checks.sh
            PIPELINE=dev
            npm() { printf 'CONTENT_LANGS=%s\n' "$CONTENT_LANGS"; }
            cli() { printf 'de,es'; }
            run_smoke_checks http://example.test
            """
        )

        self.assertEqual(0, result.returncode)
        self.assertIn("CONTENT_LANGS=de,es", result.stdout)

    def test_smoke_checks_gibt_npm_fehlercode_zurueck(self):
        result = run_web_check(
            r"""
            . scripts/web_checks.sh
            PIPELINE=dev
            CONTENT_LANGS=de
            npm() { return 1; }
            cli() { :; }
            run_smoke_checks http://example.test
            """
        )

        self.assertNotEqual(0, result.returncode)

    def test_a11y_artefakt_test_nutzt_bestehende_runtime_config(self):
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
                "server /tmp/deploy/public run_accessibility_checks",
            ],
            result.stdout.strip().splitlines(),
        )

    def test_a11y_artefakt_test_gibt_server_fehler_zurueck(self):
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
        self.assertEqual(
            [
                "html /tmp/deploy/var/cache/html",
                "server /tmp/deploy/public run_accessibility_checks",
            ],
            result.stdout.strip().splitlines(),
        )


if __name__ == "__main__":
    unittest.main()
