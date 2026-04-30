#!/usr/bin/env bash
set -euo pipefail

enable_rewrite() {
  a2enmod rewrite
}

allow_overrides() {
  sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
}

main() {
  enable_rewrite
  allow_overrides
  exec apache2-foreground
}

main "$@"
