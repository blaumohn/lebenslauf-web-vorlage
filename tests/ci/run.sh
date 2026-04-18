#!/bin/sh
set -e

WORK_BASE=${CI_WORK_BASE:-${HOME:-/tmp}}
mkdir -p "$WORK_BASE"
WORK_DIR=$(mktemp -d "$WORK_BASE/ci-XXXXXX")
CI_PIPELINES=${CI_PIPELINES:-dev,preview}

git clone --local --no-hardlinks /src "$WORK_DIR"

diff=$(git -C /src -c safe.directory=/src diff --binary HEAD)
if [ -n "$diff" ]; then
    echo "$diff" | git -C "$WORK_DIR" apply --whitespace=nowarn
fi

git -C /src -c safe.directory=/src ls-files --others --exclude-standard | while IFS= read -r f; do
    case "$f" in
        .local/*|.venv/*|node_modules/*|public/*|var/cache/*|vendor/*)
            continue
            ;;
    esac
    mkdir -p "$WORK_DIR/$(dirname "$f")"
    cp "/src/$f" "$WORK_DIR/$f"
done

cd "$WORK_DIR"

bash bin/ci test-matrix "$CI_PIPELINES"
