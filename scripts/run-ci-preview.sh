#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="docker-compose.ci.yml"
CI_SERVICE="ci-preview"

main() {
  build_image
  start_helpers
  trap down_stack EXIT
  run_tests
}

build_image() {
  docker compose -f "$COMPOSE_FILE" build
}

start_helpers() {
  docker compose -f "$COMPOSE_FILE" up -d sftp-server preview-web mailpit
}

down_stack() {
  docker compose -f "$COMPOSE_FILE" down --remove-orphans
}

run_test() {
  docker compose -f "$COMPOSE_FILE" run --rm --no-deps "$CI_SERVICE" \
    bash /repo/bin/ci "$1"
}

run_tests() {
  run_test test-admin-deploy
  run_test test-push-deploy
  run_test test-composer-lock-changed
}

main "$@"
