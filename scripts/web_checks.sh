smoke_http_page_contains() {
  local url="$1" needle="$2" body

  echo "[smoke] HTTP-Abruf: ${url}" >&2
  if ! body="$(curl --fail-with-body --silent --show-error "$url")"; then
    echo "[smoke] HTTP-Abruf fehlgeschlagen: ${url}" >&2
    if [[ -n "$body" ]]; then
      echo "[smoke] Antwort-Body:" >&2
      printf '%s\n' "$body" >&2
    fi
    return 1
  fi
  if ! printf '%s' "$body" | grep -q "$needle"; then
    echo "[smoke] Inhalt fehlt: ${needle} in ${url}" >&2
    return 1
  fi
}

run_smoke_checks() {
  local base="${1%/}"
  local content_langs="${CONTENT_LANGS:-$(cli config "$PIPELINE" get CONTENT_LANGS --phase build)}"

  PLAYWRIGHT_BASE_URL="$base" CONTENT_LANGS="$content_langs" npm run qa:smoke
}

run_artifact_html_accessibility_checks() {
  local deploy_dir="${1:?deploy_dir fehlt}"
  local content_langs
  content_langs="$(cli config "$PIPELINE" get CONTENT_LANGS --phase build)"

  run_html_quality_checks "$deploy_dir/var/cache/html"
  CONTENT_LANGS="$content_langs" with_dev_server "$deploy_dir/public" run_accessibility_checks
}

run_html_quality_checks() {
  local html_dir="${1:?html_dir fehlt}"

  npx html-validate --config htmlvalidate.config.cjs "$html_dir"
}

run_accessibility_checks() {
  local base="${1%/}"

  PLAYWRIGHT_BASE_URL="$base" CONTENT_LANGS="$CONTENT_LANGS" npm run qa:a11y
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
