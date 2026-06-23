import logging
import sys

from cli.py.ci import log_filter
from cli.py.util.compose_runner import build_run_id, compose, compose_cmd, run, runner_env
from cli.py.util.log import Logger

CI_SERVICE_DRIVER = "ci-driver"
USAGE = "Usage: runner.py <pipeline>"
logger = logging.getLogger(__name__)

CI_TEST_CASES = (
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
    return run_pipeline(pipeline)


def resolve_pipeline(args) -> str | None:
    if len(args) != 1:
        return None
    pipeline = args[0].strip()
    if not pipeline:
        return None
    return pipeline


CI_SERVICE_DEV = "dev-smoke"
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


def run_pipeline(pipeline: str) -> int:
    try:
        build_image()
        reset_deploy()
        start_helpers()
        run_content_sftp_relative_test(pipeline)
        run_content_sftp_absolute_test(pipeline)
        run_tests(pipeline)
        return 0
    except RuntimeError as exc:
        logger.error(str(exc))
        dump_diagnostics()
        return 1
    finally:
        down_stack()


def build_image() -> None:
    compose("build", label="Image-Build")


def reset_deploy() -> None:
    compose("down", "--remove-orphans", label="Stack-Reset")
    run(["docker", "volume", "rm", "ci_deploy"], check=False)


def start_helpers() -> None:
    compose("up", "-d", "--wait", "sftp-server", "ci-web", "mailpit", label="Hilfsdienste")


def run_tests(pipeline: str) -> None:
    for test_case, overrides in CI_TEST_CASES:
        run_test_prepare(pipeline)
        run_test_case(pipeline, test_case, overrides)


def run_test_prepare(pipeline: str, content_path: str | None = None) -> None:
    env = pipeline_test_env(pipeline, "test-prepare")
    env["CI_CONTENT_PREPARE_SCRIPT"] = "bin/content-vorbereiten"
    if content_path is not None:
        env["CI_CONTENT_PATH"] = content_path
    result = compose(
        "run", "--rm", "--no-deps",
        CI_SERVICE_DRIVER,
        env=env,
        label="Test-Vorbereitung",
        check=False,
    )
    if result.returncode != 0:
        raise RuntimeError(
            f"[runner] Test-Vorbereitung fehlgeschlagen "
            f"(Exit-Code: {result.returncode})"
        )


def run_test_case(pipeline: str, test_case: str, overrides: str | None) -> None:
    suppress_types = SUPPRESS_ERROR_TYPES.get(test_case, set())
    print(f"[runner] Testfall: {test_case}", flush=True)
    if suppress_types:
        returncode = _run_filtered_test(pipeline, test_case, overrides, suppress_types)
    else:
        returncode = _compose_test(pipeline, test_case, overrides).returncode
    if returncode != 0:
        raise RuntimeError(
            f"[runner] Fehlgeschlagen: Testfall: {test_case} "
            f"(Exit-Code: {returncode})"
        )
    print(f"[runner] Testfall OK: {test_case}", flush=True)


def _run_filtered_test(pipeline: str, test_case: str, overrides: str | None, suppress_types: set[str]) -> int:
    env = pipeline_test_env(pipeline, test_case, overrides)
    cmd = compose_cmd("run", "--rm", "--no-deps", "-e", f"CI_TEST_CASE={test_case}", CI_SERVICE_DRIVER)
    return log_filter.run_filtered(cmd, env, suppress_types)


def _compose_test(pipeline: str, test_case: str, overrides: str | None):
    env = pipeline_test_env(pipeline, test_case, overrides)
    return compose(
        "run", "--rm", "--no-deps", "-e", f"CI_TEST_CASE={test_case}",
        CI_SERVICE_DRIVER,
        env=env,
        label=f"Testfall: {test_case}",
        check=False,
    )


def run_content_sftp_relative_test(pipeline: str) -> None:
    content_path = ".local/content"
    run_test_prepare(pipeline, content_path)
    env = pipeline_test_env(pipeline, "content-relativ")
    env["CI_CONTENT_PATH"] = content_path
    _run_content_test("content-relativ", env, [])


def run_content_sftp_absolute_test(pipeline: str) -> None:
    content_path = f"/tmp/{build_ci_run_id('content')}"
    run_test_prepare(pipeline, content_path)
    env = pipeline_test_env(pipeline, "content-absolut")
    env["CI_CONTENT_PATH"] = content_path
    _run_content_test("content-absolut", env, [])


def _run_content_test(test_case: str, env: dict, vol_args: list[str]) -> None:
    print(f"[runner] Testfall: {test_case}", flush=True)
    result = compose(
        "run", "--rm", "--no-deps", "-e", "CI_TEST_CASE=",
        *vol_args, CI_SERVICE_DRIVER,
        env=env,
        label=f"Testfall: {test_case}",
        check=False,
    )
    if result.returncode != 0:
        raise RuntimeError(
            f"[runner] Fehlgeschlagen: Testfall: {test_case} "
            f"(Exit-Code: {result.returncode})"
        )
    print(f"[runner] Testfall OK: {test_case}", flush=True)


def pipeline_test_env(pipeline: str, test_case: str, overrides: str | None = None) -> dict[str, str]:
    env = runner_env(f"ci-{test_case}")
    env["PIPELINE"] = pipeline
    env["CI_CONTENT_PATH"] = ""
    if overrides is not None:
        env["SFTP_DEPLOY_OVERRIDES"] = overrides
    if test_case in SUPPRESS_ERROR_TYPES:
        env["LOG_FORMAT"] = "json"
    return env


def build_ci_run_id(test_case: str) -> str:
    return build_run_id(f"ci-{test_case}")


def dump_diagnostics() -> None:
    for service in ("ci-web", "sftp-server", "mailpit"):
        print(f"\n[runner] === Logs: {service} ===", flush=True)
        compose("logs", "--no-color", "--tail=50", service, check=False)
    print("\n[runner] === Stack-Status ===", flush=True)
    compose("ps", check=False)


def down_stack() -> None:
    compose("down", "--remove-orphans", check=False)


if __name__ == "__main__":
    sys.exit(main())
