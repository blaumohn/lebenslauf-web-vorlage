#!/bin/sh
# Prüft Commit-Nachrichten auf Conventional-Commit-Form mit Jira-Key.
set -eu

MESSAGE_FILE="${1:-}"
ALLOWED_TYPES="feat fix docs style refactor perf test build ci chore revert"
HEADER_RE='^\(feat\|fix\|docs\|style\|refactor\|perf\|test\|build\|ci\|chore\|revert\)([A-Za-z0-9._/-][A-Za-z0-9._/-]*): .\+ (J01-[0-9][0-9]*)$'

main() {
    [ -n "$MESSAGE_FILE" ] || fail "Commit-Message-Datei fehlt."
    [ -f "$MESSAGE_FILE" ] || fail "Commit-Message-Datei nicht gefunden: $MESSAGE_FILE"
    validate_header
    validate_body
}

validate_header() {
    header=$(sed -n '1p' "$MESSAGE_FILE")
    printf '%s\n' "$header" | grep -q "$HEADER_RE" && return 0
    fail "Erwartet: <typ>(<scope>): <titel> (J01-123). Erlaubte Typen: $ALLOWED_TYPES"
}

validate_body() {
    line_no=0
    sed '1d' "$MESSAGE_FILE" | while IFS= read -r line; do
        line_no=$((line_no + 2))
        [ -z "$line" ] && continue
        case "$line" in
            "- "*) continue ;;
        esac
        fail "Body-Zeile $line_no muss leer sein oder mit '- ' beginnen."
    done
}

fail() {
    printf 'Fehler: %s\n' "$1" >&2
    exit 1
}

main
