MAILPIT_API_URL="${MAILPIT_API_URL:-http://mailpit:8025}"
CONTACT_SMOKE_PORT="${CONTACT_SMOKE_PORT:-8082}"

contact_smoke() {
  local pid

  prepare_contact_smoke_dirs
  pid="$(start_php_server "$CONTACT_SMOKE_PORT" "$DEPLOY_DIR/public" "/tmp/ci-contact.log")"
  trap "kill $pid 2>/dev/null || true" RETURN
  wait_for_http_server "$CONTACT_SMOKE_PORT"

  local captcha_id solution total_before status total_after
  captcha_id="$(fetch_captcha_id)"
  [[ -n "$captcha_id" ]] \
    || { echo "[contact-smoke] Captcha-ID nicht gefunden" >&2; return 1; }

  solution="$(read_captcha_solution "$captcha_id")"
  total_before="$(mailpit_message_total)"

  echo "[contact-smoke] Sende Kontaktformular..."
  status="$(submit_contact_form "$captcha_id" "$solution")"
  [[ "$status" == "200" ]] \
    || { echo "[contact-smoke] Formular-POST fehlgeschlagen (HTTP ${status})" >&2; return 1; }

  total_after="$(mailpit_message_total)"
  [[ "$total_after" -gt "$total_before" ]] \
    || { echo "[contact-smoke] Keine neue Mail nach Formular-Submit" >&2; return 1; }

  assert_contact_mail_content
  echo "[contact-smoke] OK: Formular-Mail empfangen"
}

assert_contact_mail_content() {
  local runtime_config expected_to subject to
  runtime_config="$(pipeline_config runtime)"
  expected_to="$(config_value "$runtime_config" CONTACT_TO_EMAIL)"
  read -r subject to < <(mailpit_latest_message_subject_and_to)

  [[ "$subject" == "Kontaktformular" ]] \
    || { echo "[contact-smoke] Unerwartetes Mail-Subjekt: '$subject'" >&2; return 1; }
  [[ "$to" == "$expected_to" ]] \
    || { echo "[contact-smoke] Unerwarteter Empfänger: '$to'" >&2; return 1; }
}

mailpit_latest_message_subject_and_to() {
  curl --fail --silent "${MAILPIT_API_URL}/api/v1/messages" \
    | jq -r '[.messages[0].Subject // "", .messages[0].To[0].Address // ""] | @tsv'
}

prepare_contact_smoke_dirs() {
  mkdir -p \
    "$DEPLOY_DIR/var/state/locks" \
    "$DEPLOY_DIR/var/tmp/captcha" \
    "$DEPLOY_DIR/var/tmp/ratelimit"
}

fetch_captcha_id() {
  curl --fail --silent "http://127.0.0.1:${CONTACT_SMOKE_PORT}/contact" \
    | extract_captcha_id_from_contact_html
}

extract_captcha_id_from_contact_html() {
  perl -ne 'if (m{/captcha\.png\?id=([^"&]+)}) { print "$1\n"; exit }'
}

read_captcha_solution() {
  local captcha_id="$1"
  jq -r '.solution_text' "$DEPLOY_DIR/var/tmp/captcha/${captcha_id}.json"
}

submit_contact_form() {
  local captcha_id="$1" solution="$2"
  curl --silent --output /dev/null --write-out "%{http_code}" \
    --data-urlencode "name=CI Test" \
    --data-urlencode "email=ci@ci.invalid" \
    --data-urlencode "message=Testformular aus CI" \
    --data-urlencode "captcha_id=${captcha_id}" \
    --data-urlencode "captcha_answer=${solution}" \
    "http://127.0.0.1:${CONTACT_SMOKE_PORT}/contact"
}

mailpit_message_total() {
  curl --fail --silent "${MAILPIT_API_URL}/api/v1/messages" \
    | jq -r '.total'
}
