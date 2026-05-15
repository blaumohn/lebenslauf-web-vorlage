pipeline_report_start() {
  if [[ "${PIPELINE_REPORT_STARTED:-0}" == "1" ]]; then
    return
  fi

  PIPELINE_REPORT_STARTED=1
  if [[ -z "${GITHUB_STEP_SUMMARY:-}" ]]; then
    return
  fi

  {
    printf '## CI/CD-Ablauf\n\n'
    printf '| Schritt | Ergebnis | Dauer |\n'
    printf '| --- | --- | ---: |\n'
  } >> "$GITHUB_STEP_SUMMARY"
}

run_step() {
  PIPELINE_STEP_LABEL="$1"
  shift

  pipeline_report_start
  pipeline_group_start "$PIPELINE_STEP_LABEL"
  PIPELINE_STEP_STARTED_AT="$(date +%s)"
  trap 'run_step_failed "$?"' ERR

  "$@"

  trap - ERR
  run_step_completed
}

run_step_failed() {
  local status="$1"
  local duration

  trap - ERR
  duration="$(pipeline_step_duration)"
  pipeline_group_end
  ci_note "Fehler: ${PIPELINE_STEP_LABEL} (${duration}s, Exit-Code ${status})"
  pipeline_report_row "$PIPELINE_STEP_LABEL" "Fehler" "$duration"
  exit "$status"
}

run_step_completed() {
  local duration

  duration="$(pipeline_step_duration)"
  pipeline_group_end
  ci_note "OK: ${PIPELINE_STEP_LABEL} (${duration}s)"
  pipeline_report_row "$PIPELINE_STEP_LABEL" "OK" "$duration"
}

pipeline_step_duration() {
  local finished_at

  finished_at="$(date +%s)"
  printf '%s\n' "$((finished_at - PIPELINE_STEP_STARTED_AT))"
}

ci_note() {
  printf '[ci] %s\n' "$*"
}

pipeline_group_start() {
  local label="$1"

  ci_note "Start: $label"
  if [[ "${GITHUB_ACTIONS:-}" == "true" ]]; then
    printf '::group::%s\n' "$label"
  fi
}

pipeline_group_end() {
  if [[ "${GITHUB_ACTIONS:-}" == "true" ]]; then
    printf '::endgroup::\n'
  fi
}

pipeline_report_row() {
  local label="$1"
  local status="$2"
  local seconds="$3"

  if [[ -z "${GITHUB_STEP_SUMMARY:-}" ]]; then
    return
  fi

  printf '| %s | %s | %ss |\n' "$label" "$status" "$seconds" >> "$GITHUB_STEP_SUMMARY"
}
