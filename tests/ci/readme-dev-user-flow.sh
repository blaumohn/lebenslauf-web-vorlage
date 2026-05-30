#!/usr/bin/env bash
set -euo pipefail
set -E
trap 'echo "[readme-dev-ux] Fehler in Zeile $LINENO: $BASH_COMMAND" >&2' ERR

SOURCE_REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
. "$SOURCE_REPO_DIR/scripts/shell_env.sh"
. "$SOURCE_REPO_DIR/scripts/ci_clone_lib.sh"
. "$SOURCE_REPO_DIR/scripts/pipeline_output.sh"

require_env_nonempty CI_WORK_BASE

main() {
  local staging_dir user_work_dir git_daemon_pid dev_server_pid LEBENSLAUF_WEB_VORLAGE_REPO

  run_step "README-Dev-UX: Repository bereitstellen" prepare_readme_dev_repo
  run_step "README-Dev-UX: Schnellstart" schnellstart
  run_step "README-Dev-UX: Private Ansicht" private_ansicht_einrichten

  kill "${git_daemon_pid:-}" "${dev_server_pid:-}" 2>/dev/null || true
  trap - EXIT
}

prepare_readme_dev_repo() {
  staging_dir="$(prepare_repo_dir readme-dev-ux-staging)"
  user_work_dir="$(mktemp -d "$CI_WORK_BASE/readme-dev-ux-XXXXXX")"
  start_git_server "$staging_dir"
  trap 'kill "${git_daemon_pid:-}" "${dev_server_pid:-}" 2>/dev/null || true' EXIT
  cd "$user_work_dir"
}

schnellstart() {
  git clone "$LEBENSLAUF_WEB_VORLAGE_REPO" lebenslauf-web-vorlage
  cd lebenslauf-web-vorlage
  export PATH="$PWD/bin:$PATH"  # statt export: php bin/cli …
  composer install
  cli setup dev --with-sample-content
  cli build dev
  cli start dev > /tmp/readme-dev-ux-server.log 2>&1 &
  dev_server_pid="$!"
  wait_for_dev_server
}

private_ansicht_einrichten() {
  local token
  token="$(cli token dev rotate default)"
  curl --fail --silent --show-error "http://127.0.0.1:8080/cv?token=${token}" \
    | grep -q '</html>'
}

start_git_server() {
  local repo_dir parent name port
  repo_dir="$1"
  parent="$(dirname "$repo_dir")"
  name="$(basename "$repo_dir")"
  port="${GIT_DAEMON_PORT:-9418}"
  git daemon --reuseaddr --port="$port" \
    --base-path="$parent" --export-all \
    "$repo_dir" &
  git_daemon_pid="$!"
  LEBENSLAUF_WEB_VORLAGE_REPO="git://127.0.0.1:${port}/${name}"
  wait_for_git_server
}

wait_for_git_server() {
  for _ in $(seq 1 10); do
    if git ls-remote "$LEBENSLAUF_WEB_VORLAGE_REPO" > /dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  echo "[readme-dev-ux] git daemon antwortet nicht rechtzeitig" >&2
  return 1
}

wait_for_dev_server() {
  local url="http://127.0.0.1:8080/"
  for _ in $(seq 1 30); do
    if curl --silent --show-error "$url" > /dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  echo "[readme-dev-ux] Dev-Server antwortet nicht rechtzeitig" >&2
  cat /tmp/readme-dev-ux-server.log >&2 || true
  return 1
}

main "$@"
