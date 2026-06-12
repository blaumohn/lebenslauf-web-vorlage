run_http_smoke_checks() {
  local base="${1%/}"
  local cv_name

  cv_name="$(read_cv_name_kurz)"
  smoke_http_page_contains "${base}/" "Zum Lebenslauf"
  smoke_http_page_contains "${base}/cv" "$cv_name"
  smoke_http_page_contains "${base}/contact" "<form"
}

run_artifact_html_accessibility_checks() {
  local deploy_dir="${1:?deploy_dir fehlt}"

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

post_deploy_http_smoke_checks() {
  local root_url

  root_url="$(cli config "$PIPELINE" get APP_ROOT_URL --phase deploy)"
  run_http_smoke_checks "$root_url"
}

post_deploy_header_smoke_checks() {
  local root_url

  root_url="$(cli config "$PIPELINE" get APP_ROOT_URL --phase deploy)"
  run_http_header_checks "$root_url"
}

run_http_header_checks() {
  local base="${1%/}"

  smoke_http_header_contains "${base}/" "content-type" "text/html"
  smoke_http_header_contains "${base}/cv" "content-type" "text/html"
  smoke_http_header_contains "${base}/contact" "content-type" "text/html"
  smoke_http_header_contains "${base}/" "x-content-type-options" "nosniff"
  smoke_http_header_contains "${base}/cv" "x-content-type-options" "nosniff"
  smoke_http_header_contains "${base}/contact" "x-content-type-options" "nosniff"
}

smoke_http_header_contains() {
  local url="$1" header="$2" needle="$3" headers

  echo "[smoke] HTTP-Header: ${url} ${header}" >&2
  headers="$(fetch_http_headers "$url")" || return 1
  if header_list_contains "$headers" "$header" "$needle"; then
    return 0
  fi
  report_missing_http_header "$url" "$header" "$needle" "$headers"
}

fetch_http_headers() {
  local url="$1" headers response

  if headers="$(curl --fail --silent --show-error \
    --dump-header - \
    --output /dev/null \
    "$url")"
  then
    printf '%s' "$headers"
    return 0
  fi

  echo "[smoke] Header-Abruf fehlgeschlagen: ${url}" >&2
  response="$(curl --include --fail-with-body --silent --show-error "$url" 2>&1)" || true
  if [[ -n "$response" ]]; then
    echo "[smoke] Antwort:" >&2
    printf '%s\n' "$response" >&2
  fi
  return 1
}

header_list_contains() {
  local headers="$1" header="$2" needle="$3"

  printf '%s' "$headers" \
    | tr -d '\r' \
    | awk -v header="$header" -v needle="$needle" '
        BEGIN {
          header = tolower(header)
          needle = tolower(needle)
          found = 0
        }
        {
          line = tolower($0)
          if (index(line, header ":") == 1 && index(line, needle) > 0) {
            found = 1
          }
        }
        END {
          if (found) {
            exit 0
          }
          exit 1
        }
      '
}

report_missing_http_header() {
  local url="$1" header="$2" needle="$3" headers="$4"

  echo "[smoke] Header fehlt: ${header} enthält ${needle} in ${url}" >&2
  printf '%s\n' "$headers" >&2
  return 1
}

read_cv_name_kurz() {
  local daten_pfad profile yaml_file

  daten_pfad="$(cli config "$PIPELINE" get LEBENSLAUF_DATEN_PFAD --phase build)"
  profile="$(cli config "$PIPELINE" get LEBENSLAUF_PUBLIC_PROFILE --phase build)"
  yaml_file="${daten_pfad}/daten-${profile}.yaml"
  grep 'kurz:' "$yaml_file" | sed 's/.*kurz: *//'
}

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
