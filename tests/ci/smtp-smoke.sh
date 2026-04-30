smtp_smoke() {
  local mailpit_api_url="${MAILPIT_API_URL:-http://mailpit:8025}"
  await_mailpit "$mailpit_api_url"
  assert_smtp_tls
  echo "[smtp-smoke] Sende Testmail..."
  php "$ROOT_DIR/scripts/smoke-send-mail.php"

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

assert_smtp_tls() {
  local runtime_config host port result
  runtime_config="$(pipeline_config runtime)"
  host="$(config_value "$runtime_config" SMTP_HOST)"
  port="$(config_value "$runtime_config" SMTP_PORT)"
  echo "[smtp-smoke] Prüfe TLS-Verbindung..."
  result="$(echo \
    | openssl s_client -connect "${host}:${port}" -starttls smtp \
        -CAfile "$ROOT_DIR/tests/ci/ca.crt" 2>&1)"
  echo "$result" | grep -q "Verify return code: 0 (ok)" \
    || { echo "[smtp-smoke] TLS-Verifikation fehlgeschlagen" >&2
         echo "$result" | grep "Verify return code" >&2
         exit 1; }
  echo "[smtp-smoke] TLS OK"
}
