require_set() {
  local name

  for name in "$@"; do
    : "${!name?$name nicht gesetzt}"
  done
}

require_nonempty() {
  local name

  for name in "$@"; do
    : "${!name:?$name leer oder nicht gesetzt}"
  done
}

required_set_var() {
  local name=${1:?"Variablenname fehlt"}

  require_set "$name"
  printf '%s\n' "${!name}"
}

required_nonempty_var() {
  local name=${1:?"Variablenname fehlt"}

  require_nonempty "$name"
  printf '%s\n' "${!name}"
}

resolve_root_dir() {
  local script_path=${1:?"script_path fehlt"}

  cd "$(dirname "$script_path")/.." && pwd
}
