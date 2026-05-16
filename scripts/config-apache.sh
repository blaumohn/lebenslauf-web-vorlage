set -euo pipefail

enable_rewrite() {
  a2enmod rewrite
}

allow_overrides() {
  sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
}

ensure_run_user() {
  if ! getent group "$APACHE_RUN_GROUP" >/dev/null; then
    groupadd --gid 1000 "$APACHE_RUN_GROUP"
  fi
  if ! id -u "$APACHE_RUN_USER" >/dev/null 2>&1; then
    useradd --uid 1000 --gid "$APACHE_RUN_GROUP" --home-dir /var/www --no-create-home "$APACHE_RUN_USER"
  fi
}

apply_run_user() {
  grep -rl "^User " /etc/apache2/ \
    | xargs -r sed -i "s|^User .*|User ${APACHE_RUN_USER}|"
  grep -rl "^Group " /etc/apache2/ \
    | xargs -r sed -i "s|^Group .*|Group ${APACHE_RUN_GROUP}|"
}

configure_php_errors() {
  local ini_dir
  ini_dir="$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')"
  printf 'log_errors = On\nerror_log = /dev/stderr\n' \
    > "${ini_dir}/99-task-errors.ini"
}

install_ci_ca_certificate() {
  if [ ! -f /usr/local/share/ca-certificates/ci-mailpit-ca.crt ]; then
    return
  fi
  update-ca-certificates
}

main() {
  enable_rewrite
  allow_overrides
  install_ci_ca_certificate
  ensure_run_user
  apply_run_user
  configure_php_errors
  exec apache2-foreground
}

main "$@"
