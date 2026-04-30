require_env_nonempty() {
  local name

  for name in "$@"; do
    : "${!name:?$name leer oder nicht gesetzt}"
  done
}

require_env_set() {
  local name

  for name in "$@"; do
    : "${!name?$name nicht gesetzt}"
  done
}

resolve_root_dir() {
  local script_path="$1"

  cd "$(dirname "$script_path")/.." && pwd
}
