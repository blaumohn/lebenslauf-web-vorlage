#!/usr/bin/env .venv/bin/python3
import os
import shutil
import signal
import subprocess
import sys
import tempfile
import time
import unittest
from urllib.request import Request, urlopen


ROOT = os.path.abspath(os.path.dirname(os.path.dirname(__file__)))


def run(cmd, cwd=None, env=None):
    result = subprocess.run(cmd, cwd=cwd, env=env)
    if result.returncode != 0:
        raise RuntimeError(f"Command failed: {' '.join(cmd)}")


def output(cmd, cwd=None):
    result = subprocess.run(cmd, cwd=cwd, capture_output=True, text=True)
    if result.returncode != 0:
        return ""
    return result.stdout.strip()


def resolve_source(value):
    if value in ("", "local", "repo"):
        return ROOT
    if os.path.exists(value):
        return os.path.abspath(value)
    return value


def ensure_cache_dir(path):
    os.makedirs(path, exist_ok=True)
    return path


def build_env(base_env):
    cache_root = os.environ.get("SMOKE_CACHE_ROOT", "")
    if cache_root == "":
        return None
    cache_root = ensure_cache_dir(cache_root)
    env = dict(base_env)
    env["COMPOSER_CACHE_DIR"] = ensure_cache_dir(os.path.join(cache_root, "composer"))
    env["NPM_CONFIG_CACHE"] = ensure_cache_dir(os.path.join(cache_root, "npm"))
    env["PIP_CACHE_DIR"] = ensure_cache_dir(os.path.join(cache_root, "pip"))
    return env


def clone_repo(source, target):
    if os.path.exists(source):
        run(["git", "clone", "--local", source, target])
        return
    run(["git", "clone", source, target])


def wait_for_server(url, process, retries=20, delay=0.5):
    for _ in range(retries):
        exit_code = process.poll()
        if exit_code is not None:
            stdout, stderr = process.communicate(timeout=2)
            raise RuntimeError(
                "Dev-Server ist beendet.\n"
                f"Exit-Code: {exit_code}\n"
                f"STDOUT:\n{stdout}\n"
                f"STDERR:\n{stderr}"
            )
        try:
            fetch(url)
            return
        except Exception:
            time.sleep(delay)
    raise RuntimeError(f"Server nicht erreichbar: {url}")


def fetch(url):
    req = Request(url, headers={"User-Agent": "lebenslauf-smoke"})
    with urlopen(req, timeout=5) as response:
        body = response.read()
    return body.decode("utf-8", errors="replace")


def start_dev_server(clone_path, env):
    popen_kwargs = {"cwd": clone_path, "env": env}
    if os.name == "nt":
        popen_kwargs["creationflags"] = subprocess.CREATE_NEW_PROCESS_GROUP
    else:
        popen_kwargs["preexec_fn"] = os.setsid
    return subprocess.Popen(
        ["php", "bin/cli", "run", "dev"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        **popen_kwargs,
    )


def stop_dev_server(proc):
    if os.name == "nt":
        proc.terminate()
        try:
            proc.wait(timeout=10)
        except subprocess.TimeoutExpired:
            proc.kill()
        return
    try:
        os.killpg(os.getpgid(proc.pid), signal.SIGTERM)
    except ProcessLookupError:
        return
    try:
        proc.wait(timeout=10)
    except subprocess.TimeoutExpired:
        os.killpg(os.getpgid(proc.pid), signal.SIGKILL)


class SmokeTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        source = resolve_source(os.environ.get("CLONE_SOURCE", "local"))
        cls.temp_dir = tempfile.mkdtemp(prefix="lebenslauf-smoke-")
        cls.clone_path = os.path.join(cls.temp_dir, "repo")
        clone_repo(source, cls.clone_path)
        cls.env = build_env(os.environ)
        run(["composer", "install", "--no-interaction", "--prefer-dist"], cwd=cls.clone_path, env=cls.env)
        run(["php", "bin/cli", "setup", "dev", "--create-data-templates"], cwd=cls.clone_path, env=cls.env)

    @classmethod
    def tearDownClass(cls):
        if os.environ.get("KEEP_SMOKE_CLONE") == "1":
            print(f"Smoke clone kept at: {cls.clone_path}")
            return
        shutil.rmtree(cls.temp_dir, ignore_errors=True)

    def test_smoke_flow(self):
        """clone -> setup -> tests -> dev-server -> /cv check."""
        run(["php", "bin/cli", "build", "dev"], cwd=self.clone_path, env=self.env)

        run(["composer", "run", "test"], cwd=self.clone_path, env=self.env)

        proc = start_dev_server(self.clone_path, self.env)
        try:
            wait_for_server("http://127.0.0.1:8080/", proc)
            html = fetch("http://127.0.0.1:8080/cv")
            self.assertIn("Lebenslauf", html)
        finally:
            stop_dev_server(proc)


class SmokeTestResult(unittest.TextTestResult):
    def getDescription(self, test):
        desc = test.shortDescription()
        name = f"{test.__class__.__name__}.{test._testMethodName}"
        if desc:
            return f"{name} - {desc}"
        return name


if __name__ == "__main__":
    runner = unittest.TextTestRunner(verbosity=2, resultclass=SmokeTestResult)
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(SmokeTests)
    result = runner.run(suite)
    sys.exit(0 if result.wasSuccessful() else 1)
