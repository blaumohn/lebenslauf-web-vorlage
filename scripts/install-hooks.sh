# shellcheck shell=sh
# Richtet die versionierten Git-Hooks ein.
# Aufruf: sh scripts/install-hooks.sh
set -eu

SCRIPT_DIR=$(cd -- "$(dirname "$0")" && pwd)
REPO_ROOT=$(cd -- "$SCRIPT_DIR/.." && pwd)
HOOK_NAMES="commit-msg pre-commit pre-push"

main() {
    for hook_name in $HOOK_NAMES; do
        install_hook "$hook_name"
    done
}

install_hook() {
    hook_name="$1"
    hook_src="$SCRIPT_DIR/hooks/$hook_name"
    hook_dst="$REPO_ROOT/.git/hooks/$hook_name"
    check_source "$hook_src"
    install_symlink "$hook_src" "$hook_dst"
    printf 'Hook eingerichtet: %s\n' "$hook_dst"
}

check_source() {
    hook_src="$1"
    [ -f "$hook_src" ] || {
        printf 'Fehler: Hook-Quelle nicht gefunden: %s\n' \
            "$hook_src" >&2
        exit 1
    }
}

install_symlink() {
    hook_src="$1"
    hook_dst="$2"
    ln -sf "$hook_src" "$hook_dst"
    chmod +x "$hook_src"
}

main
