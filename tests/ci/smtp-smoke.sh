MAILPIT_API_URL="${MAILPIT_API_URL:-http://mailpit:8025}"

await_mailpit() {
  local attempt
  for attempt in $(seq 1 10); do
    curl --silent --fail "${MAILPIT_API_URL}/api/v1/info" > /dev/null 2>&1 && return
    sleep 1
  done
  echo "[smtp-smoke] Mailpit nicht erreichbar: ${MAILPIT_API_URL}" >&2
  exit 1
}

assert_smtp_tls() {
  local host="mailpit" port="1025" result
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

smtp_smoke() {
  await_mailpit
  assert_smtp_tls
  echo "[smtp-smoke] Sende Testmail..."
  php "$ROOT_DIR/scripts/smoke-send-mail.php"

  local total
  total="$(curl --fail --silent --show-error \
    "${MAILPIT_API_URL}/api/v1/messages" \
    | python3 -c "import sys,json; print(json.load(sys.stdin)['total'])")"

  [[ "$total" -ge 1 ]] \
    || { echo "[smtp-smoke] Keine Mail empfangen (total=${total})" >&2; exit 1; }
  echo "[smtp-smoke] OK: ${total} Mail(s)"
}
