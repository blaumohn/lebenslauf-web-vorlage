import os
import shutil
import subprocess
import time

from cli.py.util.run_helpers import run


class WorktreeSession:
    def __init__(self, source_repo, temp_dir):
        self.source_repo = source_repo
        self.temp_dir = temp_dir
        self.worktree_path = None
        self.branch = None

    def prepare(self):
        self.worktree_path = os.path.join(self.temp_dir, "worktree")
        self.branch = f"tmp/smoke-test-{int(time.time())}"
        run(["git", "-C", self.source_repo, "worktree", "add", self.worktree_path, "-b", self.branch, "HEAD"])
        self.apply_patch()
        self.copy_untracked()
        self.commit()
        return self

    def cleanup(self):
        if self.worktree_path is None or self.branch is None:
            return
        run(["git", "-C", self.source_repo, "worktree", "remove", "--force", self.worktree_path])
        run(["git", "-C", self.source_repo, "branch", "-D", self.branch])

    def apply_patch(self):
        diff = run_capture(
            ["git", "-C", self.source_repo, "diff", "--binary", "HEAD"],
            cwd=self.source_repo,
        )
        if diff.strip() == "":
            return
        result = subprocess.run(
            ["git", "-C", self.worktree_path, "apply", "--whitespace=nowarn"],
            input=diff,
            text=True,
        )
        if result.returncode != 0:
            raise RuntimeError("Failed to apply patch to worktree.")

    def copy_untracked(self):
        files = self.list_untracked()
        for rel in files:
            src = os.path.join(self.source_repo, rel)
            dest = os.path.join(self.worktree_path, rel)
            if os.path.isdir(src):
                shutil.copytree(src, dest, dirs_exist_ok=True)
                continue
            os.makedirs(os.path.dirname(dest), exist_ok=True)
            shutil.copy2(src, dest)

    def commit(self):
        if self.status_porcelain() == "":
            return
        run(["git", "-C", self.worktree_path, "add", "-A"])
        run(["git", "-C", self.worktree_path, "commit", "-m", "tmp: smoke test"])

    def list_untracked(self):
        output_text = run_capture(
            ["git", "-C", self.source_repo, "ls-files", "--others", "--exclude-standard"],
            cwd=self.source_repo,
        )
        return [line for line in output_text.splitlines() if line.strip()]

    def status_porcelain(self):
        return run_capture(["git", "-C", self.worktree_path, "status", "--porcelain"], cwd=self.worktree_path).strip()


def run_capture(cmd, cwd=None, text=True):
    result = subprocess.run(cmd, cwd=cwd, capture_output=True, text=text)
    if result.returncode != 0:
        raise RuntimeError(f"Command failed: {' '.join(cmd)}")
    return result.stdout
