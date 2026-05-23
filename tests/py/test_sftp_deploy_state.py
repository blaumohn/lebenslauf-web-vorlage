# ruff: noqa: E402, I001
import json
import sys
import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.sftp_deploy_state import (
    DeployState,
    DeploymentPlan,
    HtaccessSlotFile,
    SlotState,
    _VENDOR_SLOT_RE,
)
from cli.py.deploy.sftp_deploy_templates import resource_path

BOOTSTRAP_SRC = REPO_ROOT / "src" / "Http" / "bootstrap.php"
VENDOR_INJECT_LINE = "require $vendorDir . '/autoload.php';"
VENDOR_INJECTED = (
    "require dirname(__DIR__, 3) . '/vendor-a/autoload.php';"
)


class HtaccessSlotFileTest(unittest.TestCase):
    def test_roundtrip_app_a(self):
        content = HtaccessSlotFile.generate("a")
        self.assertEqual(HtaccessSlotFile.read_slot(content), "a")

    def test_roundtrip_app_b(self):
        content = HtaccessSlotFile.generate("b")
        self.assertEqual(HtaccessSlotFile.read_slot(content), "b")

    def test_read_slot_raises_no_rule(self):
        """read_slot() wirft ValueError, wenn keine RewriteRule passt.
        """
        with self.assertRaises(ValueError):
            HtaccessSlotFile.read_slot("RewriteEngine On\n")

    def test_generate_app_slot_content(self):
        """generate() enthält App-Slot-Pfade und RewriteEngine-Direktive.
        """
        content = HtaccessSlotFile.generate("a")
        self.assertIn("/app-a/public/index.php", content)
        self.assertIn("/app-a/public/", content)
        self.assertIn("RewriteEngine On", content)


class BootstrapInjectTest(unittest.TestCase):
    def test_inject_line_once(self):
        """bootstrap.php enthält die Inject-Zielzeile genau einmal."""
        content = BOOTSTRAP_SRC.read_text(encoding="utf-8")
        self.assertEqual(
            content.count(VENDOR_INJECT_LINE),
            1,
            f"Zeile '{VENDOR_INJECT_LINE}' muss genau einmal"
            " vorkommen — Änderung → _inject_vendor_require()"
            " in sftp-deploy.py anpassen",
        )

    def test_vendor_regex_matches_injected(self):
        """_VENDOR_SLOT_RE trifft auf injizierte bootstrap.php-Zeile."""
        match = _VENDOR_SLOT_RE.search(VENDOR_INJECTED)
        self.assertIsNotNone(
            match,
            f"_VENDOR_SLOT_RE trifft nicht auf '{VENDOR_INJECTED}' — "
            "Änderung des Inject-Formats → _VENDOR_SLOT_RE anpassen",
        )
        self.assertEqual(match.group(1), "a")

    def test_vendor_regex_no_source_match(self):
        """_VENDOR_SLOT_RE darf die Quellzeile nicht treffen."""
        self.assertIsNone(
            _VENDOR_SLOT_RE.search(VENDOR_INJECT_LINE),
            f"_VENDOR_SLOT_RE trifft auf Quellzeile"
            f" '{VENDOR_INJECT_LINE}'"
            " — DeployState.read() würde falschen Slot lesen",
        )


class DeploymentPlanTest(unittest.TestCase):
    def test_swap_both_slots_include_vendor(self):
        """swap() wechselt App- und Vendor-Slot,
        wenn Vendor eingeschlossen."""
        active = SlotState("a", "b")
        plan = DeploymentPlan.swap(active, include_vendor=True)
        self.assertEqual(plan.target, SlotState("b", "a"))

    def test_swap_keeps_vendor_without_flag(self):
        """swap() behält Vendor-Slot, wenn include_vendor=False."""
        active = SlotState("a", "b")
        plan = DeploymentPlan.swap(active, include_vendor=False)
        self.assertEqual(plan.target, SlotState("b", "b"))


class DeployResourceTest(unittest.TestCase):
    def test_public_entry_bootstrap(self):
        """public/index.php bindet bootstrap.php ein."""
        content = (REPO_ROOT / "public" / "index.php").read_text(encoding="utf-8")
        self.assertIn(
            "require dirname(__DIR__) . '/src/Http/bootstrap.php'",
            content,
        )

    def test_public_htaccess_routing(self):
        """public/.htaccess leitet Anfragen an index.php weiter."""
        content = (REPO_ROOT / "public" / ".htaccess").read_text(encoding="utf-8")
        self.assertIn("RewriteEngine On", content)
        self.assertIn("index.php", content)

    def test_app_slot_protects_src_and_var(self):
        """src/ und var/ innerhalb app-slot sind per .htaccess gesperrt.
        """
        src = read_resource("app-slot/src/.htaccess")
        var = read_resource("app-slot/var/.htaccess")
        self.assertEqual(src.strip(), "Require all denied")
        self.assertEqual(var.strip(), "Require all denied")

    def test_composer_no_deploy_autoload(self):
        """composer.json enthält kein files-Autoload
        für deploy-state.php."""
        composer = json.loads((REPO_ROOT / "composer.json").read_text())
        autoload = composer["autoload"]
        self.assertNotIn("files", autoload)
        self.assertNotIn(
            "src/resources/deploy-root/webroot/deploy-state.php",
            json.dumps(autoload),
        )

    def test_public_index_exists(self):
        """public/index.php ist als App-Einstieg vorhanden."""
        self.assertTrue((REPO_ROOT / "public" / "index.php").exists())


class DevRouterTest(unittest.TestCase):
    def test_dev_server_uses_public_docroot(self):
        """Dev-Server startet PHP mit public/ als Webroot ohne Router."""
        path = REPO_ROOT / "src" / "cli" / "py" / "dev" / "dev.py"
        content = path.read_text(encoding="utf-8")
        self.assertIn('"php"', content)
        self.assertIn('"-t"', content)
        self.assertIn('"public"', content)
        self.assertNotIn("dev-index.php", content)


_CHECKSUM = "abc123def456abcd"
_RUN_ID = "run-42"
_HTACCESS_A = HtaccessSlotFile.generate("a")
_BOOTSTRAP_B = (
    "require dirname(__DIR__, 3) . '/vendor-b/autoload.php';\n"
)
_VENDOR_META = f"[vendor]\nchecksum = {_CHECKSUM}\n\n"


class _FakeClient:
    def __init__(self, files):
        self._files = files

    def read_file(self, path):
        return self._files.get(path, "")


def _full_client(**overrides):
    files = {
        ".htaccess": _HTACCESS_A,
        "app-a/src/Http/bootstrap.php": _BOOTSTRAP_B,
        "app-a/.deploy-run": _RUN_ID,
        "vendor-b/.meta": _VENDOR_META,
    }
    files.update(overrides)
    return _FakeClient(files)


class DeployStateReadTest(unittest.TestCase):
    def test_liest_app_slot_aus_htaccess(self):
        state = DeployState.read(_full_client())
        self.assertEqual(state.app, "a")

    def test_liest_vendor_slot_aus_bootstrap(self):
        state = DeployState.read(_full_client())
        self.assertEqual(state.vendor, "b")

    def test_liest_run_id_aus_deploy_run(self):
        state = DeployState.read(_full_client())
        self.assertEqual(state.run_id, _RUN_ID)

    def test_liest_vendor_checksum_aus_meta(self):
        state = DeployState.read(_full_client())
        self.assertEqual(state.vendor_checksum, _CHECKSUM)

    def test_gibt_none_ohne_htaccess(self):
        self.assertIsNone(DeployState.read(_FakeClient({})))

    def test_leere_run_id_wenn_deploy_run_fehlt(self):
        state = DeployState.read(_full_client(**{"app-a/.deploy-run": ""}))
        self.assertEqual(state.run_id, "")

    def test_leere_checksum_wenn_meta_fehlt(self):
        state = DeployState.read(_full_client(**{"vendor-b/.meta": ""}))
        self.assertEqual(state.vendor_checksum, "")


def read_resource(path):
    return resource_path(path).read_text(encoding="utf-8")


if __name__ == "__main__":
    unittest.main()
