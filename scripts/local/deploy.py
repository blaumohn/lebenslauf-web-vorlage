#!/usr/bin/env python3

import logging
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "src"))

from cli.py.util.compose_runner import compose, runner_env  # noqa: E402

DEPLOY_SERVICE = "deploy-local"
logger = logging.getLogger(__name__)


def main() -> int:
    logging.basicConfig(level=logging.INFO, format="%(message)s")
    try:
        build_image()
        run_deploy()
        return 0
    except RuntimeError as exc:
        logger.error(str(exc))
        return 1


def build_image() -> None:
    compose("build", DEPLOY_SERVICE, label="Image-Build")


def run_deploy() -> None:
    compose(
        "run",
        "--rm",
        "--no-deps",
        DEPLOY_SERVICE,
        env=runner_env("local-deploy"),
        label="Lokaler Deploy",
    )


if __name__ == "__main__":
    sys.exit(main())
