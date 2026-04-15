import subprocess


def run(cmd, cwd=None, env=None):
    result = subprocess.run(cmd, cwd=cwd, env=env)
    if result.returncode != 0:
        raise RuntimeError(f"Command failed: {' '.join(cmd)}")
