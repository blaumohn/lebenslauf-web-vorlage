#!/bin/sh
set -e

WORK_DIR=$(mktemp -d /tmp/ci-XXXXXX)
git clone --local --no-hardlinks /src "$WORK_DIR"

diff=$(git -C /src -c safe.directory=/src diff --binary HEAD)
if [ -n "$diff" ]; then
    echo "$diff" | git -C "$WORK_DIR" apply --whitespace=nowarn
fi

git -C /src -c safe.directory=/src ls-files --others --exclude-standard | while IFS= read -r f; do
    mkdir -p "$WORK_DIR/$(dirname "$f")"
    cp "/src/$f" "$WORK_DIR/$f"
done

cd "$WORK_DIR"

composer install --optimize-autoloader --no-interaction
php bin/cli setup dev --copy-sample-content
php bin/cli build dev cv
php vendor/bin/phpunit

php -S 127.0.0.1:8080 -t public public/index.php > /tmp/ci-http.log 2>&1 &
SERVER_PID=$!
sleep 1
curl --fail --silent --show-error http://127.0.0.1:8080/cv | grep -q "Lebenslauf"
kill "$SERVER_PID"
