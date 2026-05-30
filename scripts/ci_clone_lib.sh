prepare_repo_dir() {
  local prefix="${1:-ci}" dir
  dir="$(mktemp -d "$CI_WORK_BASE/${prefix}-XXXXXX")"
  git clone --local --no-hardlinks "$SOURCE_REPO_DIR" "$dir"
  (cd "$dir" && apply_working_state && commit_working_state)
  echo "$dir"
}

apply_working_state() {
  apply_tracked_working_diff
  copy_untracked_working_files
}

commit_working_state() {
  [[ -z "$(git status --porcelain)" ]] && return 0
  git add -A
  git -c user.name="CI Test" -c user.email="ci@test.invalid" \
    commit -m "tmp: lokaler Arbeitsstand" >&2
}

apply_tracked_working_diff() {
  local diff
  diff="$(git -C "$SOURCE_REPO_DIR" -c safe.directory="$SOURCE_REPO_DIR" diff --binary HEAD)"
  if [[ -z "$diff" ]]; then
    return 0
  fi
  echo "$diff" | git apply --whitespace=nowarn
}

copy_untracked_working_files() {
  git -C "$SOURCE_REPO_DIR" -c safe.directory="$SOURCE_REPO_DIR" ls-files --others --exclude-standard \
    | while IFS= read -r f; do
        copy_untracked_working_file "$f"
      done
}

copy_untracked_working_file() {
  local file="$1"
  mkdir -p "$(dirname "$file")"
  cp "$SOURCE_REPO_DIR/$file" "$file"
}
