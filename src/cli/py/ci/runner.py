import logging
import sys

from cli.py.util.compose_runner import build_run_id, compose, run, runner_env

CI_SERVICE_PREVIEW = "ci-preview"
USAGE = "Usage: runner.py <pipeline>"
logger = logging.getLogger(__name__)

PREVIEW_TEST_CASES = (
    "test-admin-deploy",
    "test-push-deploy",
    "test-composer-lock-changed",
)


def main() -> int:
    logging.basicConfig(level=logging.INFO, format="%(message)s")
    pipeline = resolve_pipeline(sys.argv[1:])
    if pipeline is None:
        logger.error(USAGE)
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
    return compose(
        "up",
        "--remove-orphans",
        "--build",
        "--exit-code-from",
        "ci-dev",
        "ci-dev",
        check=False,
    ).returncode


def run_preview() -> int:
    try:
        build_image()
        check_checksum_determinism()
        reset_preview_deploy()
        start_helpers()
        run_tests()
        return 0
    except RuntimeError as exc:
        logger.error(str(exc))
        dump_diagnostics()
        return 1
    finally:
        down_stack()


def check_checksum_determinism() -> None:
    compose(
        "run", "--rm", "--no-deps",
        CI_SERVICE_PREVIEW,
        "python3", "/repo/scripts/check-vendor-determinism.py",
        label="Checksum-Determinismus",
    )


def build_image() -> None:
    compose("build", label="Image-Build")


def reset_preview_deploy() -> None:
    compose("down", "--remove-orphans", label="Stack-Reset")
    run(["docker", "volume", "rm", "preview_deploy"], check=False)


def start_helpers() -> None:
    compose("up", "-d", "--wait", "sftp-server", "preview-web", "mailpit", label="Hilfsdienste")


def run_tests() -> None:
    for test_case in PREVIEW_TEST_CASES:
        env = preview_test_env(test_case)
        print(f"[runner] Testfall: {test_case}", flush=True)
        compose(
            "run", "--rm", "--no-deps", "-e", f"CI_TEST_CASE={test_case}", CI_SERVICE_PREVIEW,
            env=env,
            label=f"Testfall: {test_case}",
        )
        print(f"[runner] Testfall OK: {test_case}", flush=True)


def preview_test_env(test_case: str) -> dict[str, str]:
    return runner_env(f"ci-{test_case}")


def build_ci_run_id(test_case: str) -> str:
    return build_run_id(f"ci-{test_case}")


def dump_diagnostics() -> None:
    for service in ("preview-web", "sftp-server", "mailpit"):
        print(f"\n[runner] === Logs: {service} ===", flush=True)
        compose("logs", "--no-color", "--tail=50", service, check=False)
    print("\n[runner] === Stack-Status ===", flush=True)
    compose("ps", check=False)


def down_stack() -> None:
    compose("down", "--remove-orphans", check=False)


if __name__ == "__main__":
    sys.exit(main())
