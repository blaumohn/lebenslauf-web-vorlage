import subprocess
import sys

COMPOSE_FILE = "docker-compose.ci.yml"
CI_SERVICE_PREVIEW = "ci-preview"
USAGE = "Usage: runner.py <pipeline>"
PREVIEW_TEST_CASES = (
    "test-admin-deploy",
    "test-push-deploy",
    "test-composer-lock-changed",
)


def main() -> int:
    pipeline = resolve_pipeline(sys.argv[1:])
    if pipeline is None:
        print(USAGE, file=sys.stderr)
        return 1
    if pipeline == "dev":
        return run_dev()
    return run_preview()


def resolve_pipeline(args) -> str | None:
    if len(args) != 1:
        return None
    pipeline = args[0].strip()
    if not pipeline:
        return None
    return pipeline


def run_dev() -> int:
    cmd = [
        "docker", "compose", "-f", COMPOSE_FILE,
        "up", "--remove-orphans", "--build", "--exit-code-from", "ci-dev", "ci-dev",
    ]
    return subprocess.run(cmd).returncode


def run_preview() -> int:
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


def build_image() -> None:
    compose("build", label="Image-Build")


def reset_preview_deploy() -> None:
    compose("down", "--remove-orphans", label="Stack-Reset")
    run(["docker", "volume", "rm", "preview_deploy"], check=False)


def start_helpers() -> None:
    compose("up", "-d", "--wait", "sftp-server", "preview-web", "mailpit", label="Hilfsdienste")


def run_tests() -> None:
    for test_case in PREVIEW_TEST_CASES:
        compose(
            "run", "--rm", "--no-deps", "-e", f"CI_TEST_CASE={test_case}", CI_SERVICE_PREVIEW,
            label=f"Testfall: {test_case}",
        )


def down_stack() -> None:
    compose("down", "--remove-orphans", check=False)


def compose(*args, check=True, label: str = "") -> subprocess.CompletedProcess:
    return run(["docker", "compose", "-f", COMPOSE_FILE, *args], check=check, label=label)


def run(cmd, check=True, label: str = "") -> subprocess.CompletedProcess:
    result = subprocess.run(cmd)
    if check and result.returncode != 0:
        context = label if label else " ".join(cmd)
        raise RuntimeError(f"[ci] Fehlgeschlagen: {context}")
    return result


if __name__ == "__main__":
    sys.exit(main())
