import importlib.util
import sys
import types
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
TOKEN_SMOKE_PATH = REPO_ROOT / "tests" / "ci" / "token_smoke.py"
sys.path.insert(0, str(REPO_ROOT / "src"))
sys.path.insert(0, str(REPO_ROOT / "tests" / "ci"))
sys.modules.setdefault("paramiko", types.SimpleNamespace(RejectPolicy=object, SSHClient=object))


def load_token_smoke_module():
    spec = importlib.util.spec_from_file_location(
        "token_smoke",
        TOKEN_SMOKE_PATH,
    )
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


token_smoke = load_token_smoke_module()


class TokenSmokeTest(unittest.TestCase):
    def test_extracts_single_token(self):
        token = "a" * 32

        self.assertEqual(token, token_smoke.extract_token(f"\n{token}\n"))

    def test_rejects_missing_token(self):
        with self.assertRaisesRegex(RuntimeError, "genau einen Token"):
            token_smoke.extract_token("kein token")

    def test_rejects_multiple_tokens(self):
        body = "\n".join(["a" * 32, "b" * 32])

        with self.assertRaisesRegex(RuntimeError, "genau einen Token"):
            token_smoke.extract_token(body)

    def test_accepts_token_task_subject(self):
        token_smoke.assert_token_subject(
            "[App/Task] Task abgeschlossen: 20260626T000000Z-cv_token_add"
        )

    def test_rejects_unexpected_subject(self):
        with self.assertRaisesRegex(RuntimeError, "Unerwartetes Mail-Subjekt"):
            token_smoke.assert_token_subject("[App/Contact] Kontakt")

    def test_extracts_private_profiles_from_cache_entries(self):
        profiles = token_smoke.profiles_from_cache_entries(
            [
                "cv-private-default.de.html",
                "cv-private-default.en.html",
                "cv-private-demo.de.html",
                "cv-public-default.de.html",
                "home.de.html",
            ]
        )

        self.assertEqual(["default", "demo"], profiles)


if __name__ == "__main__":
    unittest.main()
