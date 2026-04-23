#!/usr/bin/env bash
set -euo pipefail

main() {
  local exit_code=0

  docker compose -f docker-compose.ci.yml up --remove-orphans --build \
    --abort-on-container-exit --exit-code-from ci-preview ci-preview \
    || exit_code=$?
  docker compose -f docker-compose.ci.yml down --remove-orphans
  return "$exit_code"
}

main "$@"
