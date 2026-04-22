run_pipeline() {
  local pipeline="$1" is_dev docroot build_overrides
  [[ $pipeline == dev ]] && is_dev=1

  cli setup "$pipeline" ${is_dev:+--copy-sample-content} --overrides '{}'
  build_overrides="$(build_group_overrides_json "$pipeline" runtime smtp SMTP_PASS)"
  cli build "$pipeline" ${is_dev:+cv} --overrides "$build_overrides"
  [[ -x "$ROOT_DIR/vendor/bin/phpunit" ]] && php "$ROOT_DIR/vendor/bin/phpunit"

  if [[ -n "${is_dev:-}" ]]; then
    docroot="$ROOT_DIR/public"
  else
    prepare_deploy "$pipeline"
    docroot="$DEPLOY_DIR/public"
  fi

  with_http_server 8080 "$docroot" http_smoke_checks 8080
}

json_escape() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  value="${value//$'\n'/\\n}"
  value="${value//$'\r'/\\r}"
  value="${value//$'\t'/\\t}"
  printf '%s' "$value"
}

build_group_overrides_json() {
  local pipeline="$1"
  local phase="$2"
  local group="$3"
  local json="" sep="" name value

  shift 3
  for name in "$@"; do
    value="${!name-}"
    [[ -n "${value:-}" ]] || continue
    value="$(json_escape "$value")"
    json+="${sep}\"${name}\":\"${value}\""
    sep=","
  done

  if [[ -z "$json" ]]; then
    echo '{}'
    return 0
  fi

  printf '{"%s":{"%s":{"%s":{%s}}}}\n' \
    "$pipeline" \
    "$phase" \
    "$group" \
    "$json"
}

prepare_deploy() {
  local pipeline="$1"

  prepare_deploy_dir
  verify_artifact
  write_resolved_output "$pipeline"
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
  local pipeline="$1"

  [[ "$GITHUB_OUTPUT" != "" ]] || return 0
  run_resolve_deploy "$pipeline" >> "$GITHUB_OUTPUT"
}

assert_output_key() {
  local file="$1"
  local key="$2"

  grep -q "^${key}=" "$file"
}

run_resolve_deploy() {
  local pipeline="$1" pair name key

  for pair in \
    "ftp_host:FTP_HOST" \
    "ftp_user:FTP_USER" \
    "ftp_pass:FTP_PASS" \
    "ftp_port:FTP_PORT" \
    "ftp_server_dir:FTP_SERVER_DIR"; do
    name="${pair%%:*}"
    key="${pair#*:}"
    print_resolved_value "$pipeline" "$name" "$key"
  done
}

print_resolved_value() {
  local pipeline="$1"
  local name="$2"
  local key="$3"
  local value

  value="$(config_get "$pipeline" deploy "$key")"
  printf '%s=%s\n' "$name" "$value"
}

config_get() {
  local pipeline="$1"
  local phase="$2"
  local key="$3"
  local overrides

  overrides="$(
    build_group_overrides_json \
      "$pipeline" \
      "$phase" \
      ftp \
      FTP_HOST FTP_USER FTP_PASS FTP_PORT FTP_SERVER_DIR
  )"

  cli config get "$pipeline" "$key" --phase "$phase" --overrides "$overrides"
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
  curl --fail --silent --show-error "http://127.0.0.1:${port}/cv" | grep -q "Lebenslauf"
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
