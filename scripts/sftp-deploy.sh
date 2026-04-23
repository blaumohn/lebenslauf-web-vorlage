#!/usr/bin/env sh
set -eu

setup_known_hosts() {
  install -m 700 -d "$HOME/.ssh"
  printf '%s\n' "$SSH_KNOWN_HOST_LINE" >> "$HOME/.ssh/known_hosts"
  chmod 600 "$HOME/.ssh/known_hosts"
}

write_batch_file() {
  cat > /tmp/deploy.sftp <<EOF
-mkdir $SFTP_REMOTE_DIR
cd $SFTP_REMOTE_DIR
lcd var/deploy
put -r .
bye
EOF
}

run_sftp() {
  sshpass -e sftp \
    -o StrictHostKeyChecking=yes \
    -o UserKnownHostsFile="$HOME/.ssh/known_hosts" \
    -o PreferredAuthentications=password \
    -P "$SFTP_PORT" \
    -b /tmp/deploy.sftp \
    "$SFTP_USER@$SFTP_HOST"
}

main() {
  setup_known_hosts
  write_batch_file
  run_sftp
}

main "$@"
