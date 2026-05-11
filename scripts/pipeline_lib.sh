run_pipeline() {
  local is_dev docroot

  require_env_nonempty PIPELINE

  write_pipeline_config_from_stdin

  [[ $PIPELINE == dev ]] && is_dev=1 || is_dev=

  if [[ ! $is_dev ]]; then
    require_env_set DEPLOY_DIR LAST_DEPLOY_COMMIT
  fi

  cli setup "$PIPELINE" ${is_dev:+--with-sample-content}
  cli build "$PIPELINE" ${is_dev:+cv}
  [[ -x vendor/bin/phpunit ]] && php vendor/bin/phpunit

  if [[ $is_dev ]]; then
    docroot="public"
  else
    deploy
    docroot="$DEPLOY_DIR/public"
  fi

  with_http_server 8080 "$docroot" http_smoke_checks "127.0.0.1" "8080"
}

write_pipeline_config_from_stdin() {
  if [[ -t 0 ]]; then
    return
  fi
  mkdir -p .local
  cat > ".local/${PIPELINE}.yaml"
}

deploy() {
  local include_vendor=true

  prepare_deploy_dir
  verify_artifact

  include_vendor="$(should_include_vendor)"

  sftp_upload "$include_vendor"
}

prepare_deploy_dir() {
  rm -rf "$DEPLOY_DIR"
  mkdir -p "$DEPLOY_DIR/var/cache"
  mkdir -p "$DEPLOY_DIR/src"
  cp -a public vendor "$DEPLOY_DIR/"
  cp -a src/Http src/resources "$DEPLOY_DIR/src/"
  cp -a var/cache/html "$DEPLOY_DIR/var/cache/"
  cp -a var/config "$DEPLOY_DIR/var/"
  copy_deploy_htaccess app-slot "$DEPLOY_DIR/.htaccess"
  copy_deploy_htaccess src "$DEPLOY_DIR/src/.htaccess"
  copy_deploy_htaccess var "$DEPLOY_DIR/var/.htaccess"
}

copy_deploy_htaccess() {
  local scope="$1"
  local target="$2"

  cp "src/resources/http/$scope/.htaccess" "$target"
}

verify_artifact() {
  test -f "$DEPLOY_DIR/public/index.php"
  test -f "$DEPLOY_DIR/var/cache/html/cv-public.html"
  test -f "$DEPLOY_DIR/.htaccess"
  test -f "$DEPLOY_DIR/src/.htaccess"
  test -f "$DEPLOY_DIR/var/.htaccess"
}

no_changes_since_deploy() {
  [[ -n "${LAST_DEPLOY_COMMIT:-}" ]] && git diff --quiet "$LAST_DEPLOY_COMMIT" HEAD
}

should_include_vendor() {
  local diff_files

  if [[ -z "${LAST_DEPLOY_COMMIT:-}" ]]; then
    echo true
    return
  fi

  diff_files="$(git diff --name-only "$LAST_DEPLOY_COMMIT" HEAD)"
  echo "$diff_files" | grep -qx "composer\.lock" && echo true && return
  echo false
}

sftp_upload() {
  local include_vendor="$1"
  SFTP_INCLUDE_VENDOR="$include_vendor" cli python "$PIPELINE" --phases deploy scripts/sftp-deploy.py
}


with_http_server() {
  local port="$1"
  local docroot="$2"
  local pid

  shift 2
  pid="$(start_php_server "$port" "$docroot" "/tmp/ci-http-${port}.log")"
  trap 'kill '"$pid"' 2>/dev/null || true' EXIT
  wait_for_http_server "$port"
  "$@"
  kill "$pid"
  trap - EXIT
}

http_smoke_checks() {
  local host="$1" port="$2"

  echo "[smoke] Prüfe http://${host}:${port}/"
  smoke_http_page_contains "$host" "$port" "/" "Zum Lebenslauf"
  echo "[smoke] OK /"

  echo "[smoke] Prüfe http://${host}:${port}/cv"
  smoke_http_page_contains "$host" "$port" "/cv" "Alex B."
  echo "[smoke] OK /cv"

  echo "[smoke] Prüfe http://${host}:${port}/contact"
  smoke_http_page_contains "$host" "$port" "/contact" "<form"
  echo "[smoke] OK /contact"
}

smoke_http_page_contains() {
  local host="$1" port="$2" path="$3" needle="$4" body

  body="$(curl --fail --silent --show-error "http://${host}:${port}${path}")"

  if ! printf '%s' "$body" | grep -q "$needle"; then
    echo "[smoke] Inhalt fehlt: ${needle} in ${path}" >&2
    echo "$body"
    exit 1
  fi
}

start_php_server() {
  local port="$1"
  local docroot="$2"
  local log_file="$3"

  php -S "0.0.0.0:${port}" -t "$docroot" > "$log_file" 2>&1 &
  echo "$!"
}

wait_for_http_server() {
  local port="$1"
  local attempt

  for attempt in $(seq 1 10); do
    if curl --silent --show-error "http://127.0.0.1:${port}/" > /dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  echo "HTTP-Server auf Port ${port} antwortet nicht rechtzeitig" >&2
  exit 1
}
