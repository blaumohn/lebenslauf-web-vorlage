. scripts/pipeline_output.sh
. scripts/web_checks.sh

CONTENT_SFTP_FETCHED=0

run_pipeline() {
  local deploy_dir='' is_dev=''
  local -a ci_ca_cert_arg=()

  if [[ $PIPELINE == dev ]]; then
    is_dev=1
  else
    deploy_dir="${1:?deploy_dir fehlt}"
    [[ "${2:-}" == "--ci-ca-cert" ]] && ci_ca_cert_arg=("$2")
  fi

  require_nonempty PIPELINE

  write_pipeline_config_from_stdin

  pipeline_report_start
  run_step "Setup ($PIPELINE)" pipeline_setup "$is_dev"

  if [[ ! $is_dev ]]; then
    run_step "SMTP-Auth-Prüfung" run_smtp_credentials_check "${ci_ca_cert_arg[@]}"
    run_step "Content-Daten" prepare_content_data
  fi

  run_step "Build ($PIPELINE)" pipeline_build
  # run_step "Tests" run_unit_and_feature_tests

  if [[ $is_dev ]]; then
    run_step "Tests" composer test
    run_step "QA-Tooling-Selbsttest" npm run qa:tooling
    run_step "HTTP-Smoke lokal" with_dev_server "public" run_smoke_checks
    run_step "Link-Check lokal" with_dev_server "public" run_link_checks
    return
  fi

  run_step "Deploy-Artefakt" prepare_deploy "$deploy_dir"
  run_step "Artefakt-HTTP-Smoke" with_dev_server "$deploy_dir/public" run_smoke_checks
  run_step "Artefakt-HTML/A11y-QA" run_artifact_html_accessibility_checks "$deploy_dir"
  run_step "Artefakt-Link-Check" with_dev_server "$deploy_dir/public" run_link_checks
  run_step "SFTP-Deploy"           deploy
  reset_content_sftp_if_used
}

pipeline_setup() {
  local is_dev="$1"

  cli setup "$PIPELINE" ${is_dev:+--with-sample-content}
}

pipeline_build() {
  cli build "$PIPELINE"
}

write_pipeline_config_from_stdin() {
  local config_file=".local/${PIPELINE}.yaml"
  [[ -f "$config_file" ]] && return
  [[ -t 0 ]] && return
  mkdir -p .local
  filter_empty_yaml_values > "$config_file"
}

filter_empty_yaml_values() {
  awk '
    /^[a-zA-Z]/ { pending=$0; next }
    /: ""$/      { next }
    { if (pending) { print pending; pending="" }; print }
  '
}

run_unit_and_feature_tests() {
  if [ ! -x vendor/bin/phpunit ]; then
    echo "PHPUnit nicht installiert; PHP-Tests werden übersprungen."
    composer test:python
    return
  fi

  composer test
}

prepare_deploy() {
  local deploy_dir="${1:?deploy_dir fehlt}"
  prepare_deploy_dir "$deploy_dir"
  verify_artifact "$deploy_dir"
}

prepare_deploy_dir() {
  local deploy_dir="${1:?deploy_dir fehlt}"
  rm -rf "$deploy_dir"
  mkdir -p "$deploy_dir/var/cache"
  mkdir -p "$deploy_dir/src"
  cp -a public vendor "$deploy_dir/"
  cp -a src/Http src/resources "$deploy_dir/src/"
  cp -a var/cache/html var/cache/templates "$deploy_dir/var/cache/"
  mkdir -p "$deploy_dir/var/config"
  cp -a var/config/config.json "$deploy_dir/var/config/config.json"
  copy_slot_htaccess "src" "$deploy_dir/src/.htaccess"
  copy_slot_htaccess "var" "$deploy_dir/var/.htaccess"
}

copy_slot_htaccess() {
  local sub="$1" target="$2"
  cp "src/resources/deploy-root/app-slot${sub:+/$sub}/.htaccess" "$target"
}

verify_artifact() {
  local deploy_dir="${1:?deploy_dir fehlt}" missing=0
  local files=(
    "$deploy_dir/public/index.php"
    "$deploy_dir/public/.htaccess"
    "$deploy_dir/src/Http/bootstrap.php"
    "$deploy_dir/var/config/config.json"
    "$deploy_dir/src/.htaccess"
    "$deploy_dir/var/.htaccess"
  )
  for f in "${files[@]}"; do
    if [[ ! -f "$f" ]]; then
      echo "[verify] Artefakt fehlt: ${f#"$deploy_dir/"}" >&2
      missing=1
    fi
  done
  if ! diff -rq var/cache/html "$deploy_dir/var/cache/html" > /dev/null 2>&1; then
    echo "[verify] HTML-Cache im Artefakt unvollständig" >&2
    missing=1
  fi
  return "$missing"
}

no_changes_since_deploy() {
  local last_deploy_commit="${1:?last_deploy_commit fehlt}"
  is_first_deploy_commit "$last_deploy_commit" && return 1
  diff=$(git diff --name-only "$last_deploy_commit" HEAD) || return 1
  [ -z "$diff" ]
}

is_first_deploy_commit() {
  local zero_sha
  zero_sha="$(printf '%040d' 0)"
  [[ "$1" == "$zero_sha" ]]
}

content_path_has_data() {
  find "$(content_base_path)" -mindepth 1 -type d -print -quit 2>/dev/null | grep -q .
}

prepare_content_data() {
  if content_path_has_data; then
    CONTENT_SFTP_FETCHED=0
    return 0
  fi
  fetch_content
  CONTENT_SFTP_FETCHED=1
}

content_base_path() {
  cli config "$PIPELINE" get CONTENT_PATH --phase build
}

fetch_content() {
  cli python "$PIPELINE" --phase build --phase deploy -- scripts/content-sftp-fetch.py
}

reset_content_sftp_if_used() {
  [[ "$CONTENT_SFTP_FETCHED" == "1" ]] || return 0
  run_step "Content-SFTP-Reset" loeschen_content_sftp
}

loeschen_content_sftp() {
  cli python "$PIPELINE" --phase deploy -- scripts/content-sftp-reset.py
}

run_smtp_credentials_check() {
  cli python "$PIPELINE" --phase runtime -- scripts/smtp-credentials-check.py "$@"
}

deploy() {
  sftp_upload
}

sftp_upload() {
  local overrides_arg=()
  [[ -n "${SFTP_DEPLOY_OVERRIDES:-}" ]] \
    && overrides_arg=(--overrides "$SFTP_DEPLOY_OVERRIDES")
  cli python "$PIPELINE" --phase deploy --phase runtime "${overrides_arg[@]}" scripts/sftp-deploy.py
}
