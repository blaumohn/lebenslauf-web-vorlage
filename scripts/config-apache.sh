#!/usr/bin/env bash
set -euo pipefail

enable_rewrite() {
  a2enmod rewrite
}

allow_overrides() {
  sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
}

apply_run_user() {
  printf '\nexport APACHE_RUN_USER=%s\nexport APACHE_RUN_GROUP=%s\n' \
    "${APACHE_RUN_USER}" "${APACHE_RUN_GROUP}" >> /etc/apache2/envvars
}

main() {
  enable_rewrite
  allow_overrides
  apply_run_user
  exec apache2-foreground
}

main "$@"
