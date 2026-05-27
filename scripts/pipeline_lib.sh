. scripts/pipeline_output.sh

run_pipeline() {
  local is_dev=

  require_env_nonempty PIPELINE

  write_pipeline_config_from_stdin

  [[ $PIPELINE == dev ]] && is_dev=1

  if [[ ! $is_dev ]]; then
    require_env_set DEPLOY_DIR LAST_DEPLOY_COMMIT
  fi

  pipeline_report_start
  run_step "Setup ($PIPELINE)" pipeline_setup "$is_dev"
  run_step "Build ($PIPELINE)" pipeline_build "$is_dev"
  # run_step "Tests" run_unit_and_feature_tests

  if [[ $is_dev ]]; then
    run_step "Tests" composer test
    run_step "HTTP-Smoke lokal" with_dev_server "public" run_http_smoke_checks
    return
  fi

  run_step "Deploy-Artefakt" prepare_deploy
  run_step "HTTP-Smoke Artefakt" with_dev_server "$DEPLOY_DIR/public" run_http_smoke_checks
  run_step "SFTP-Deploy" deploy
  run_step "HTTP-Smoke Zielsystem" post_deploy_smoke_checks
}

pipeline_setup() {
  local is_dev="$1"

  cli setup "$PIPELINE" ${is_dev:+--with-sample-content}
}

pipeline_build() {
  local is_dev="$1"

  cli build "$PIPELINE" ${is_dev:+cv}
}

write_pipeline_config_from_stdin() {
  if [[ -t 0 ]]; then
    return
  fi
  mkdir -p .local
  cat > ".local/${PIPELINE}.yaml"
}

run_unit_and_feature_tests() {
  if [ ! -x vendor/bin/phpunit ]; then
    echo "PHPUnit nicht installiert; PHP-Tests werden übersprungen."
    composer test:python
    return
  fi

  composer test
}

prepare_deploy() {
  prepare_deploy_dir
  verify_artifact
}

prepare_deploy_dir() {
  rm -rf "$DEPLOY_DIR"
  mkdir -p "$DEPLOY_DIR/var/cache"
  mkdir -p "$DEPLOY_DIR/src"
  cp -a public vendor "$DEPLOY_DIR/"
  cp -a src/Http src/resources "$DEPLOY_DIR/src/"
  cp -a var/cache/html "$DEPLOY_DIR/var/cache/"
  cp -a var/config "$DEPLOY_DIR/var/"
  copy_slot_htaccess "src" "$DEPLOY_DIR/src/.htaccess"
  copy_slot_htaccess "var" "$DEPLOY_DIR/var/.htaccess"
}

copy_slot_htaccess() {
  local sub="$1" target="$2"
  cp "src/resources/deploy-root/app-slot${sub:+/$sub}/.htaccess" "$target"
}

verify_artifact() {
  test -f "$DEPLOY_DIR/public/index.php"
  test -f "$DEPLOY_DIR/public/.htaccess"
  test -f "$DEPLOY_DIR/src/Http/bootstrap.php"
  test -f "$DEPLOY_DIR/var/cache/html/cv-public.html"
  test -f "$DEPLOY_DIR/src/.htaccess"
  test -f "$DEPLOY_DIR/var/.htaccess"
}

no_changes_since_deploy() {
  [[ -z "${LAST_DEPLOY_COMMIT:-}" ]] && return 1
  local diff
  diff="$(git diff --name-only "$LAST_DEPLOY_COMMIT" HEAD)"
  [[ -z "$diff" ]]
}

deploy() {
  sftp_upload
}

sftp_upload() {
  local overrides_arg=()
  [[ -n "${SFTP_DEPLOY_OVERRIDES:-}" ]] \
    && overrides_arg=(--overrides "$SFTP_DEPLOY_OVERRIDES")
  cli python "$PIPELINE" --phase deploy "${overrides_arg[@]}" scripts/sftp-deploy.py
}

post_deploy_smoke_checks() {
  local root_url
  root_url="$(cli config "$PIPELINE" get APP_ROOT_URL --phase deploy)"
  run_http_smoke_checks "$root_url"
}

run_http_smoke_checks() {
  local base="${1%/}"
  smoke_http_page_contains "${base}/"        "Zum Lebenslauf"
  smoke_http_page_contains "${base}/cv"      "Alex B."
  smoke_http_page_contains "${base}/contact" "<form"
}

smoke_http_page_contains() {
  local url="$1" needle="$2" body
  echo "[smoke] HTTP-Abruf: ${url}" >&2
  if ! body="$(curl --fail --silent --show-error "$url")"; then
    echo "[smoke] HTTP-Abruf fehlgeschlagen: ${url}" >&2
    return 1
  fi
  if ! printf '%s' "$body" | grep -q "$needle"; then
    echo "[smoke] Inhalt fehlt: ${needle} in ${url}" >&2
    echo "$body"
    exit 1
  fi
}


with_dev_server() {
  local docroot="$1" dev_server_port=8080 pid
  shift
  pid="$(start_php_server "$dev_server_port" "$docroot" "/tmp/ci-http-${dev_server_port}.log")"
  trap 'kill '"$pid"' 2>/dev/null || true' EXIT
  wait_for_http_server "$dev_server_port"
  "$@" "http://127.0.0.1:${dev_server_port}"
  kill "$pid"
  trap - EXIT
}


start_php_server() {
  local port="$1" docroot="$2" log_file="$3"

  php -S "0.0.0.0:${port}" \
    -t "$docroot" \
    > "$log_file" 2>&1 &
  echo "$!"
}

wait_for_http_server() {
  local port="$1"

  for _ in $(seq 1 10); do
    if curl --silent --show-error "http://127.0.0.1:${port}/" > /dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  echo "HTTP-Server auf Port ${port} antwortet nicht rechtzeitig" >&2
  exit 1
}
