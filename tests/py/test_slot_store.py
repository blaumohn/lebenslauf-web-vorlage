# ruff: noqa: E402, I001
import json
import sys
import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.exceptions import DeployConflictError
from cli.py.deploy.slot_store import (
    HtaccessSlotFile,
    SlotStore,
    _VENDOR_SLOT_RE,
)
from cli.py.deploy.slots import DeploymentPlan, SlotMap
from cli.py.deploy.sftp_deploy_templates import resource_path

INDEX_PHP_SRC = REPO_ROOT / "public" / "index.php"
VENDOR_INJECT_LINE = "$vendorDir  = $appSlot . '/vendor';"
VENDOR_INJECTED = (
    "$vendorDir  = dirname(__DIR__, 2) . '/vendor-a';"
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
        """generate() enthält App-Slot-Pfade und RewriteEngine.
        """
        content = HtaccessSlotFile.generate("a")
        self.assertIn("/app-a/public/index.php", content)
        self.assertIn("/app-a/public/", content)
        self.assertIn("RewriteEngine On", content)


class BootstrapInjectTest(unittest.TestCase):
    def test_inject_line_once(self):
        """public/index.php enthält die Inject-Zielzeile genau einmal."""
        content = INDEX_PHP_SRC.read_text(encoding="utf-8")
        self.assertEqual(
            content.count(VENDOR_INJECT_LINE),
            1,
            f"Zeile '{VENDOR_INJECT_LINE}' muss genau einmal"
            " vorkommen — Änderung → _inject_vendor_dir()"
            " in tree_uploader.py anpassen",
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
            " — SlotStore.current_slot_map() würde falschen Slot lesen",
        )


class DeploymentPlanTest(unittest.TestCase):
    def test_swap_both_slots_include_vendor(self):
        """swap() wechselt App- und Vendor-Slot,
        wenn Vendor eingeschlossen."""
        active = SlotMap.from_labels(app="a", vendor="b")
        plan = DeploymentPlan.swap(active, include_vendor=True)
        self.assertEqual(
            plan.target_slot_map,
            SlotMap.from_labels(app="b", vendor="a"),
        )

    def test_swap_keeps_vendor_without_flag(self):
        """swap() behält Vendor-Slot, wenn include_vendor=False."""
        active = SlotMap.from_labels(app="a", vendor="b")
        plan = DeploymentPlan.swap(active, include_vendor=False)
        self.assertEqual(
            plan.target_slot_map,
            SlotMap.from_labels(app="b", vendor="b"),
        )


class DeployResourceTest(unittest.TestCase):
    def test_public_entry_bootstrap(self):
        """public/index.php bindet bootstrap.php ein."""
        content = (
            REPO_ROOT / "public" / "index.php"
        ).read_text(encoding="utf-8")
        self.assertIn(
            "require $appSlot . '/src/Http/bootstrap.php'",
            content,
        )

    def test_public_htaccess_routing(self):
        """public/.htaccess leitet Anfragen an index.php weiter."""
        content = (
            REPO_ROOT / "public" / ".htaccess"
        ).read_text(encoding="utf-8")
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
        composer = json.loads(
            (REPO_ROOT / "composer.json").read_text()
        )
        autoload = composer["autoload"]
        self.assertNotIn("files", autoload)
        self.assertNotIn(
            "src/resources/deploy-root/webroot/deploy-state.php",
            json.dumps(autoload),
        )

    def test_public_index_exists(self):
        """public/index.php ist als App-Einstieg vorhanden."""
        self.assertTrue((REPO_ROOT / "public" / "index.php").exists())


_CHECKSUM = "abc123def456abcd"
_RUN_ID = "run-42"
_HTACCESS_A = HtaccessSlotFile.generate("a")
_BOOTSTRAP_B = (
    "$vendorDir  = dirname(__DIR__, 2) . '/vendor-b';\n"
)
_VENDOR_META = f"[vendor]\nchecksum = {_CHECKSUM}\n\n"


class _FakeClient:
    def __init__(self, files, dirs=None):
        self._files = files
        self._dirs = set(dirs or [])

    def read_file(self, path):
        return self._files.get(path, "")

    def put_text(self, path, content):
        self._files[path] = content

    def dir_exists(self, path):
        return path in self._dirs


def _full_client(**overrides):
    files = {
        ".htaccess": _HTACCESS_A,
        "app-a/public/index.php": _BOOTSTRAP_B,
        "app-a/.deploy-run": _RUN_ID,
        "vendor-b/.meta": _VENDOR_META,
    }
    files.update(overrides)
    return _FakeClient(files)


class SlotStoreReadTest(unittest.TestCase):
    def test_liest_app_slot_aus_htaccess(self):
        slot_map = SlotStore(_full_client()).current_slot_map()
        self.assertEqual(slot_map.app.label, "a")

    def test_liest_vendor_slot_aus_bootstrap(self):
        slot_map = SlotStore(_full_client()).current_slot_map()
        self.assertEqual(slot_map.vendor.label, "b")

    def test_liest_run_id_aus_deploy_run(self):
        store = SlotStore(_full_client())
        self.assertEqual(store.run_id_for_app_slot("a"), _RUN_ID)

    def test_liest_vendor_checksum_aus_meta(self):
        store = SlotStore(_full_client())
        self.assertEqual(
            store.vendor_checksum_for_slot("b"), _CHECKSUM
        )

    def test_gibt_none_ohne_htaccess(self):
        self.assertIsNone(
            SlotStore(_FakeClient({})).current_slot_map()
        )

    def test_require_current_slot_map_wirft_ohne_htaccess(self):
        with self.assertRaisesRegex(RuntimeError, "Kein aktiver Slot"):
            SlotStore(_FakeClient({})).require_current_slot_map()

    def test_konflikt_bei_ungueltigem_htaccess(self):
        client = _FakeClient({".htaccess": "RewriteEngine On\n"})
        with self.assertRaises(DeployConflictError):
            SlotStore(client).current_slot_map()

    def test_konflikt_bei_fehlendem_bootstrap(self):
        client = _full_client(
            **{"app-a/public/index.php": ""}
        )
        with self.assertRaises(DeployConflictError):
            SlotStore(client).current_slot_map()

    def test_leere_run_id_wenn_deploy_run_fehlt(self):
        store = SlotStore(_full_client(**{"app-a/.deploy-run": ""}))
        self.assertEqual(store.run_id_for_app_slot("a"), "")

    def test_none_checksum_wenn_meta_fehlt(self):
        store = SlotStore(_full_client(**{"vendor-b/.meta": ""}))
        self.assertIsNone(store.vendor_checksum_for_slot("b"))


def read_resource(path):
    return resource_path(path).read_text(encoding="utf-8")


if __name__ == "__main__":
    unittest.main()
