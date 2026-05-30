import logging
import sys

from cli.py.ci import log_filter
from cli.py.util.compose_runner import build_run_id, compose, compose_cmd, run, runner_env
from cli.py.util.log import Logger

CI_SERVICE_PREVIEW = "ci-preview"
USAGE = "Usage: runner.py <pipeline>"
logger = logging.getLogger(__name__)

PREVIEW_TEST_CASES = (
    ("test-admin-deploy",          None),
    ("test-push-deploy",           None),
    ("test-composer-lock-changed", None),
    ("test-rollback",              '{"APP_ROOT_URL":"http://smoke-unreachable"}'),
)

SUPPRESS_ERROR_TYPES: dict[str, set[str]] = {
    "test-rollback": {"ConnectionError"},
}


def main() -> int:
    Logger("ci")
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


CI_SERVICE_DEV = "ci-dev"
CI_README_DEV_UX_CMD = ("bash", "/repo/tests/ci/readme-dev-user-flow.sh")


def run_dev() -> int:
    rc = compose(
        "up", "--remove-orphans", "--build",
        "--exit-code-from", CI_SERVICE_DEV, CI_SERVICE_DEV,
        check=False,
    ).returncode
    if rc != 0:
        return rc
    return compose(
        "run", "--rm", "--no-deps", CI_SERVICE_DEV, *CI_README_DEV_UX_CMD,
        check=False,
    ).returncode


def run_preview() -> int:
    try:
        build_image()
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


def build_image() -> None:
    compose("build", label="Image-Build")


def reset_preview_deploy() -> None:
    compose("down", "--remove-orphans", label="Stack-Reset")
    run(["docker", "volume", "rm", "preview_deploy"], check=False)


def start_helpers() -> None:
    compose("up", "-d", "--wait", "sftp-server", "preview-web", "mailpit", label="Hilfsdienste")


def run_tests() -> None:
    for test_case, overrides in PREVIEW_TEST_CASES:
        run_test_case(test_case, overrides)


def run_test_case(test_case: str, overrides: str | None) -> None:
    suppress_types = SUPPRESS_ERROR_TYPES.get(test_case, set())
    print(f"[runner] Testfall: {test_case}", flush=True)
    if suppress_types:
        env = preview_test_env(test_case, overrides)
        cmd = compose_cmd("run", "--rm", "--no-deps", "-e", f"CI_TEST_CASE={test_case}", CI_SERVICE_PREVIEW)
        returncode = log_filter.run_filtered(cmd, env, suppress_types)
    else:
        returncode = _compose_test(test_case, overrides).returncode
    if returncode != 0:
        raise RuntimeError(
            f"[runner] Fehlgeschlagen: Testfall: {test_case} "
            f"(Exit-Code: {returncode})"
        )
    print(f"[runner] Testfall OK: {test_case}", flush=True)


def _compose_test(test_case: str, overrides: str | None):
    env = preview_test_env(test_case, overrides)
    return compose(
        "run", "--rm", "--no-deps", "-e", f"CI_TEST_CASE={test_case}",
        CI_SERVICE_PREVIEW,
        env=env,
        label=f"Testfall: {test_case}",
        check=False,
    )


def preview_test_env(test_case: str, overrides: str | None = None) -> dict[str, str]:
    env = runner_env(f"ci-{test_case}")
    if overrides is not None:
        env["SFTP_DEPLOY_OVERRIDES"] = overrides
    if test_case in SUPPRESS_ERROR_TYPES:
        env["LOG_FORMAT"] = "json"
    return env


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
