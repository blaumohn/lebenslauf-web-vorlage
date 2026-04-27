#!/usr/bin/env bash
set -euo pipefail

main() {
  local exit_code=0

  docker compose -f docker-compose.ci.yml build
  docker compose -f docker-compose.ci.yml run ci-preview \
    || exit_code=$?

  if [[ $exit_code -eq 0 ]]; then
    docker compose -f docker-compose.ci.yml run --no-deps ci-diff-preview \
      || exit_code=$?
  fi

  if [[ $exit_code -eq 0 ]]; then
    docker compose -f docker-compose.ci.yml run --no-deps ci-diff-vendor-preview \
      || exit_code=$?
  fi

  docker compose -f docker-compose.ci.yml down --remove-orphans
  return "$exit_code"
}

main "$@"
