#!/bin/sh
# Richtet den versionierten Pre-Push-Hook ein.
# Aufruf: sh scripts/install-hooks.sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
REPO_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
HOOK_SRC="$SCRIPT_DIR/hooks/pre-push"
HOOK_DST="$REPO_ROOT/.git/hooks/pre-push"

main() {
    check_source
    install_symlink
    printf 'Hook eingerichtet: %s\n' "$HOOK_DST"
}

check_source() {
    [ -f "$HOOK_SRC" ] || {
        printf 'Fehler: Hook-Quelle nicht gefunden: %s\n' "$HOOK_SRC" >&2
        exit 1
    }
}

install_symlink() {
    ln -sf "$HOOK_SRC" "$HOOK_DST"
    chmod +x "$HOOK_SRC"
}

main
