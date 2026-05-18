# ruff: noqa: E402, I001
import json
import os
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.deploy.sftp_deploy_state import (
    DeployState,
    DeploymentPlan,
    SlotState,
)
from cli.py.deploy.sftp_deploy_templates import resource_path


class SftpDeployStateTest(unittest.TestCase):
    def test_deploy_state_ini_roundtrip(self):
        state = SlotState("b", "a")
        content = DeployState.format(state)
        lines = content.splitlines()

        self.assertEqual(DeployState.parse(content), state)
        expected = ["[state]", "app = b", "vendor = a", ""]
        self.assertEqual(lines, expected)

    def test_invalid_deploy_state_returns_none(self):
        content = "[state]\napp=x\nvendor=a\n"

        self.assertIsNone(DeployState.parse(content))
        self.assertIsNone(DeployState.parse("[]"))
        self.assertIsNone(DeployState.parse(""))

    def test_deploy_state_format_rejects_missing_state(self):
        with self.assertRaises(ValueError):
            DeployState.format(None)

    def test_deployment_plan_swaps_tree_and_optional_vendor(self):
        active = SlotState("a", "b")

        with_vendor = DeploymentPlan.swap(active, True, True)
        without_vendor = DeploymentPlan.swap(active, False, True)

        self.assertEqual(with_vendor.target, SlotState("b", "a"))
        self.assertEqual(without_vendor.target, SlotState("b", "b"))

    def test_deployment_plan_uploads_vendor_when_slot_invalid(self):
        active = SlotState("a", "b")

        plan = DeploymentPlan.swap(active, False, False)

        self.assertEqual(plan.target, SlotState("b", "a"))

    def test_static_entry_resources_exist(self):
        router = read_resource("webroot/index.php")
        htaccess = read_resource("webroot/.htaccess")
        deploy_state = read_resource("webroot/deploy-state.php")

        self.assertIn("require __DIR__ . '/deploy-state.php';", router)
        self.assertIn(".deploy-state.ini", router)
        self.assertNotIn("// deploy-state:", router)
        self.assertIn("final class DeployRuntimeState", deploy_state)
        self.assertIn("RewriteRule ^ index.php [L]", htaccess)

    def test_composer_does_not_autoload_deploy_state(self):
        composer = json.loads((REPO_ROOT / "composer.json").read_text())
        autoload = composer["autoload"]

        self.assertNotIn("files", autoload)
        self.assertNotIn(
            "src/resources/deploy-root/webroot/deploy-state.php",
            json.dumps(autoload),
        )

    def test_webroot_router_serves_only_active_public_files(self):
        router = read_resource("webroot/index.php")
        app_root = (
            "define('APP_ROOT_DIR', __DIR__ . '/../' "
            ". $state->appDir());"
        )

        deploy_state_require = "require __DIR__ . '/deploy-state.php';"
        bootstrap_require = "require $bootstrap;"

        self.assertIn(app_root, router)
        self.assertIn("DeployRuntimeState::fromIniFile", router)
        self.assertIn("deploy_router_log", router)
        self.assertLess(
            router.index(deploy_state_require),
            router.index(bootstrap_require),
        )
        self.assertIn("$file = $appRoot . '/public/' . $path;", router)
        self.assertIn(bootstrap_require, router)
        bootstrap_path = "APP_ROOT_DIR . '/src/Http/bootstrap.php'"
        self.assertIn(bootstrap_path, router)
        self.assertIn("str_contains($path, '..')", router)
        self.assertNotIn("$file = $appRoot . '/' . $path;", router)
        self.assertNotIn("public/index.php", router)

    def test_app_slot_protects_non_public_runtime_paths(self):
        app_root = read_resource("app-slot/.htaccess")
        src = read_resource("app-slot/src/.htaccess")
        var = read_resource("app-slot/var/.htaccess")

        self.assertEqual(app_root.strip(), "Require all denied")
        self.assertEqual(src.strip(), "Require all denied")
        self.assertEqual(var.strip(), "Require all denied")

    def test_webroot_router_uses_active_public_file(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            webroot = prepare_router_layout(root, "b")
            public_file = root / "app-b" / "public" / "asset.txt"
            write_text(public_file, "active asset")

            output = run_router(webroot, "/asset.txt")

        self.assertEqual(output, "active asset")

    def test_webroot_router_hides_runtime_paths(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            webroot = prepare_router_layout(root, "a")
            write_text(root / "app-a" / "var" / "secret.txt", "var")
            write_text(root / "app-a" / "src" / "secret.txt", "src")

            var_output = run_router(webroot, "/var/secret.txt")
            src_output = run_router(webroot, "/src/secret.txt")

        self.assertEqual(var_output, "front")
        self.assertEqual(src_output, "front")

    def test_public_index_is_not_required_as_app_entry(self):
        self.assertFalse((REPO_ROOT / "public" / "index.php").exists())

    def test_dev_router_is_outside_public_and_uses_bootstrap(self):
        router = (REPO_ROOT / "scripts" / "local" / "dev-index.php")
        content = router.read_text(encoding="utf-8")

        self.assertIn("define('APP_ROOT_DIR'", content)
        self.assertIn("define('APP_VENDOR_DIR'", content)
        self.assertIn("getenv('APP_ROOT_DIR')", content)
        self.assertIn("getenv('APP_VENDOR_DIR')", content)
        self.assertIn("return false;", content)
        self.assertIn("src/Http/bootstrap.php", content)

    def test_dev_server_uses_local_router_with_public_root(self):
        dev_script = REPO_ROOT / "src" / "cli" / "py" / "dev" / "dev.py"
        content = dev_script.read_text(encoding="utf-8")

        self.assertIn('"php"', content)
        self.assertIn('"-t"', content)
        self.assertIn('"public"', content)
        self.assertIn('"scripts/local/dev-index.php"', content)


def read_resource(path):
    return resource_path(path).read_text(encoding="utf-8")


def prepare_router_layout(root, app_slot):
    webroot = root / "public"
    webroot.mkdir()
    router = resource_path("webroot/index.php")
    shutil.copy(router, webroot / "index.php")
    deploy_state = resource_path("webroot/deploy-state.php")
    shutil.copy(deploy_state, webroot / "deploy-state.php")
    write_text(root / ".deploy-state.ini", state_ini(app_slot))
    bootstrap = (
        root / f"app-{app_slot}" / "src" / "Http" / "bootstrap.php"
    )
    write_text(bootstrap, "<?php echo 'front';")
    return webroot


def state_ini(app_slot):
    return f"[state]\napp = {app_slot}\nvendor = a\n\n"


def write_text(path, content):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def run_router(webroot, uri):
    env = os.environ.copy()
    env["REQUEST_URI"] = uri
    code = router_php_code(webroot)
    result = subprocess.run(
        ["php", "-r", code],
        check=True,
        capture_output=True,
        env=env,
        text=True,
    )
    return result.stdout


def router_php_code(webroot):
    router = str(webroot / "index.php")
    return (
        '$_SERVER["REQUEST_URI"] = getenv("REQUEST_URI"); require "'
        + router
        + '";'
    )


if __name__ == "__main__":
    unittest.main()
