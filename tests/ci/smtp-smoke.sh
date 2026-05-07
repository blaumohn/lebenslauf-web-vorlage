smtp_smoke() {
  local mailpit_api_url="${MAILPIT_API_URL:-http://mailpit:8025}" runtime_config
  await_mailpit "$mailpit_api_url"
  runtime_config="$(pipeline_config runtime)"
  echo "[smtp-smoke] Prüfe SMTP-Verbindung..."
  SMTP_CFG_JSON="$runtime_config" python3 tests/ci/smtp-smoke.py

  local total
  total="$(curl --fail --silent --show-error \
    "${mailpit_api_url}/api/v1/messages" \
    | jq -r '.total')"

  [[ "$total" -ge 1 ]] \
    || { echo "[smtp-smoke] Keine Mail empfangen (total=${total})" >&2; exit 1; }
  echo "[smtp-smoke] OK: ${total} Mail(s)"
}

await_mailpit() {
  local mailpit_api_url="$1" attempt
  for attempt in $(seq 1 10); do
    curl --silent --fail "${mailpit_api_url}/api/v1/info" > /dev/null 2>&1 && return
    sleep 1
  done
  echo "[smtp-smoke] Mailpit nicht erreichbar: ${mailpit_api_url}" >&2
  exit 1
}
