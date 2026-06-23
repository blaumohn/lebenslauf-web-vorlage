run_smoke_checks() {
  local base="${1%/}"
  local content_langs="${CONTENT_LANGS:-$(cli config "$PIPELINE" get CONTENT_LANGS --phase build)}"

  PLAYWRIGHT_BASE_URL="$base" CONTENT_LANGS="$content_langs" npm run qa:smoke
}

run_artifact_html_accessibility_checks() {
  local deploy_dir="${1:?deploy_dir fehlt}"
  export CONTENT_LANGS="$(cli config "$PIPELINE" get CONTENT_LANGS --phase build)"

  run_html_quality_checks "$deploy_dir/var/cache/html"
  with_dev_server "$deploy_dir/public" run_accessibility_checks
}

run_html_quality_checks() {
  local html_dir="${1:?html_dir fehlt}"

  npx html-validate --config htmlvalidate.config.cjs "$html_dir"/*.html
}

run_accessibility_checks() {
  local base="${1%/}"

  PLAYWRIGHT_BASE_URL="$base" npm run qa:a11y
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
    if curl --silent --show-error "http://127.0.0.1:${port}/" \
      > /dev/null 2>&1
    then
      return 0
    fi
    sleep 1
  done
  echo "HTTP-Server auf Port ${port} antwortet nicht rechtzeitig" >&2
  exit 1
}
