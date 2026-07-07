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
    line_no=1
    seen_bullet=0
    prev_blank=1
    in_paragraph=0
    sed '1d' "$MESSAGE_FILE" | while IFS= read -r line; do
        line_no=$((line_no + 1))
        if [ -z "$line" ]; then
            prev_blank=1
            in_paragraph=0
            continue
        fi
        case "$line" in
            "- "*)
                seen_bullet=1
                prev_blank=0
                in_paragraph=0
                continue
                ;;
        esac
        if [ "$seen_bullet" -eq 1 ]; then
            if [ "$prev_blank" -eq 1 ] || [ "$in_paragraph" -eq 1 ]; then
                prev_blank=0
                in_paragraph=1
                continue
            fi
        fi
        fail "Body-Zeile $line_no muss leer sein, mit '- ' beginnen oder ein durch Leerzeile abgetrennter Stichpunkt-Körper sein."
    done
}

fail() {
    printf 'Fehler: %s\n' "$1" >&2
    exit 1
}

main
