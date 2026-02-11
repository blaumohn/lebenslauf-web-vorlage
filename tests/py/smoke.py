import os
import shutil
import sys
import tempfile
import unittest

from tests.py.tools.smoke import server as smoke_server
from cli.py.util.run_helpers import run
from tests.py.tools.smoke.worktree import WorktreeSession

ROOT = os.getcwd()


def main():
    runner = unittest.TextTestRunner(verbosity=2, resultclass=SmokeTestResult)
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(SmokeTests)
    result = runner.run(suite)
    sys.exit(0 if result.wasSuccessful() else 1)


class SmokeTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp_dir = tempfile.mkdtemp(prefix="lebenslauf-smoke-")
        try:
            cls.worktree = cls.prepare_worktree()
            cls.clone_path = cls.prepare_clone()
            cls.install_composer_dependencies()
        except Exception:
            cls.cleanup_worktree()
            raise

    @classmethod
    def tearDownClass(cls):
        cls.cleanup_worktree()
        shutil.rmtree(cls.temp_dir, ignore_errors=True)

    def test_smoke_create_templates(self):
        """setup --reset-sample-content -> tests -> dev-server -> /cv check."""
        self.run_setup(create_templates=True)

        run(["php", "bin/cli", "build", "dev", "cv"], cwd=self.clone_path)

        run(["composer", "run", "test"], cwd=self.clone_path)

        proc = smoke_server.start(self.clone_path)
        try:
            smoke_server.wait("http://127.0.0.1:8080/", proc)
            html = smoke_server.fetch("http://127.0.0.1:8080/cv")
            self.assertIn("Lebenslauf", html)
        finally:
            smoke_server.stop(proc)

    @classmethod
    def prepare_worktree(cls):
        return WorktreeSession(ROOT, cls.temp_dir).prepare()

    @classmethod
    def prepare_clone(cls):
        clone_path = os.path.join(cls.temp_dir, "repo")
        clone_repo(cls.worktree.worktree_path, clone_path)
        return clone_path

    @classmethod
    def install_composer_dependencies(cls):
        run(
            ["composer", "install", "--no-interaction", "--prefer-dist"],
            cwd=cls.clone_path,
        )

    @classmethod
    def setup_cache_args(cls):
        python_cache = build_cache_dir("pip")
        npm_cache = build_cache_dir("npm")
        return ["--python-cache-dir", python_cache, "--npm-cache-dir", npm_cache]

    @classmethod
    def cleanup_worktree(cls):
        if cls.worktree is None:
            return
        cls.worktree.cleanup()

    def run_setup(self, create_templates=False):
        cmd = ["php", "bin/cli", "setup", "dev"]
        if create_templates:
            cmd.append("--reset-sample-content")
        cmd.extend(self.setup_cache_args())
        run(cmd, cwd=self.clone_path)


class SmokeTestResult(unittest.TextTestResult):
    def getDescription(self, test):
        desc = test.shortDescription()
        name = f"{test.__class__.__name__}.{test._testMethodName}"
        if desc:
            return f"{name} - {desc}"
        return name


def cache_root_path():
    return ensure_cache_dir(os.path.join(ROOT, "var", "cache", "smoke"))


def build_cache_dir(name):
    return ensure_cache_dir(os.path.join(cache_root_path(), name))


def ensure_cache_dir(path):
    os.makedirs(path, exist_ok=True)
    return path


def clone_repo(source, target):
    if os.path.exists(source):
        run(["git", "clone", "--local", source, target])
        return
    run(["git", "clone", source, target])


if __name__ == "__main__":
    main()
