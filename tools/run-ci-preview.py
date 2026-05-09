import os
import subprocess
import sys


COMPOSE_FILE = "docker-compose.ci.yml"
CI_SERVICE = "ci-preview"
TEST_CASES = (
    "test-admin-deploy",
    "test-push-deploy",
    "test-composer-lock-changed",
)


def main():
    try:
        build_image()
        reset_preview_deploy()
        start_helpers()
        run_tests()
        return 0
    except RuntimeError as exc:
        print(str(exc), file=sys.stderr)
        return 1
    finally:
        down_stack()


def build_image():
    run_compose("build")


def reset_preview_deploy():
    run_compose("down", "--remove-orphans")
    run(["docker", "volume", "rm", "preview_deploy"], check=False)


def start_helpers():
    run_compose("up", "-d", "--wait", "sftp-server", "preview-web", "mailpit")


def run_tests():
    for test_case in TEST_CASES:
        run_test(test_case)


def run_test(test_case):
    run_compose(
        "run",
        "--rm",
        "--no-deps",
        "-e",
        f"CI_TEST_CASE={test_case}",
        CI_SERVICE,
    )


def down_stack():
    run_compose("down", "--remove-orphans", check=False)


def run_compose(*args, env=None, check=True):
    cmd = ["docker", "compose", "-f", COMPOSE_FILE, *args]
    return run(cmd, env=env, check=check)


def run(cmd, env=None, check=True):
    result = subprocess.run(cmd, env=env)
    if check and result.returncode != 0:
        raise RuntimeError(f"Befehl fehlgeschlagen: {' '.join(cmd)}")
    return result


if __name__ == "__main__":
    sys.exit(main())
