run_pipeline() {
  local pipeline="$1" is_dev docroot build_overrides
  [[ $pipeline == dev ]] && is_dev=1

  cli setup "$pipeline" ${is_dev:+--copy-sample-content}
  overrides="$(php scripts/build-overrides-json.php)"
  cli build "$pipeline" ${is_dev:+cv} --overrides "$overrides"
  [[ -x "$ROOT_DIR/vendor/bin/phpunit" ]] && php "$ROOT_DIR/vendor/bin/phpunit"

  if [[ -n "${is_dev:-}" ]]; then
    docroot="$ROOT_DIR/public"
  else
    prepare_deploy "$pipeline" "$overrides"
    docroot="$DEPLOY_DIR/public"
  fi

  with_http_server 8080 "$docroot" http_smoke_checks 8080
}

prepare_deploy() {
  local pipeline="$1" overrides="$2"

  prepare_deploy_dir
  verify_artifact
  write_resolved_output "$pipeline" "$overrides"
  for key in ftp_host ftp_user ftp_pass ftp_port ftp_server_dir; do
    assert_output_key "$GITHUB_OUTPUT" "$key"
  done
}

prepare_deploy_dir() {
  rm -rf "$DEPLOY_DIR"
  mkdir -p "$DEPLOY_DIR/var/cache"
  mkdir -p "$DEPLOY_DIR/src"
  cp -a public vendor "$DEPLOY_DIR/"
  cp -a src/Http src/resources "$DEPLOY_DIR/src/"
  cp -a var/cache/html "$DEPLOY_DIR/var/cache/"
  cp -a var/config "$DEPLOY_DIR/var/"
  copy_deploy_htaccess root "$DEPLOY_DIR/.htaccess"
  copy_deploy_htaccess src "$DEPLOY_DIR/src/.htaccess"
  copy_deploy_htaccess var "$DEPLOY_DIR/var/.htaccess"
}

verify_artifact() {
  test -f "$DEPLOY_DIR/public/index.php"
  test -f "$DEPLOY_DIR/var/cache/html/cv-public.html"
  test -f "$DEPLOY_DIR/.htaccess"
  test -f "$DEPLOY_DIR/src/.htaccess"
  test -f "$DEPLOY_DIR/var/.htaccess"
}

copy_deploy_htaccess() {
  local scope="$1"
  local target="$2"

  cp "$ROOT_DIR/src/resources/http/$scope/.htaccess" "$target"
}

write_resolved_output() {
  local pipeline="$1" overrides="$2"

  [[ "$GITHUB_OUTPUT" != "" ]] || return 0
  cli config get "$pipeline" --phase deploy --format=github-output \
    --overrides "$overrides" >> "$GITHUB_OUTPUT"
}

assert_output_key() {
  local file="$1"
  local key="$2"

  grep -q "^${key}=" "$file"
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
  local port="$1"

  smoke_http_page "$port" "/"        /tmp/ci-home.html
  smoke_http_page "$port" "/cv"      /tmp/ci-cv.html
  smoke_http_page "$port" "/contact" /tmp/ci-contact.html
  curl --fail --silent --show-error "http://127.0.0.1:${port}/cv" > /tmp/ci-cv-check.html
  grep -q "Lebenslauf" /tmp/ci-cv-check.html
}

smoke_http_page() {
  local port="$1"
  local path="$2"
  local target="$3"

  curl --fail --silent --show-error "http://127.0.0.1:${port}${path}" > "$target"
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

  for attempt in 1 2 3 4 5 6 7 8 9 10; do
    if curl --silent --show-error "http://127.0.0.1:${port}/" > /dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  echo "HTTP-Server auf Port ${port} antwortet nicht rechtzeitig" >&2
  exit 1
}
