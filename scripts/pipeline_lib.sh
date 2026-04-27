run_pipeline() {
  local pipeline="$1" is_dev docroot
  [[ $pipeline == dev ]] && is_dev=1

  cli setup "$pipeline" ${is_dev:+--copy-sample-content}
  overrides="$(php scripts/build-overrides-json.php)"
  cli build "$pipeline" ${is_dev:+cv} --overrides "$overrides"
  [[ -x "$ROOT_DIR/vendor/bin/phpunit" ]] && php "$ROOT_DIR/vendor/bin/phpunit"

  if [[ -n "${is_dev:-}" ]]; then
    docroot="$ROOT_DIR/public"
  else
    deploy "$pipeline" "$overrides"
    docroot="$DEPLOY_DIR/public"
  fi

  with_http_server 8080 "$docroot" http_smoke_checks "127.0.0.1" "8080"
}

deploy() {
  local pipeline="$1" overrides="$2"
  local cfg_json diff_files

  prepare_deploy_dir
  verify_artifact

  cfg_json="$(cli config get "$pipeline" --phase deploy --overrides "$overrides")"

  : "${DEPLOY_BEFORE?DEPLOY_BEFORE ist nicht gesetzt}"
  if [[ -z "$DEPLOY_BEFORE" ]]; then
    sftp_upload "$cfg_json"
  else
    diff_files="$(git diff --name-only "$DEPLOY_BEFORE" HEAD)"
    [[ -n "$diff_files" ]] && sftp_upload_diff "$cfg_json" "$diff_files"
  fi
}

sftp_upload() {
  local cfg_json="$1"
  SFTP_CFG_JSON="$cfg_json" python3 "$ROOT_DIR/scripts/sftp-deploy.py"
}

sftp_upload_diff() {
  local cfg_json="$1" diff_files="$2" diff_json

  diff_json="$(make_diff_json "$diff_files")"
  SFTP_CFG_JSON="$cfg_json" SFTP_DIFF_JSON="$diff_json" python3 "$ROOT_DIR/scripts/sftp-deploy.py"
}

make_diff_json() {
  local diff_files="$1" vendor_full=false
  echo "$diff_files" | grep -qx "composer\.lock" && vendor_full=true

  python3 -c "
import sys, json
files = [f for f in sys.argv[1].splitlines() if f]
vendor = sys.argv[2] == 'true'
print(json.dumps({'diff_files': files, 'vendor_full': vendor}))
" "$diff_files" "$vendor_full"
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
