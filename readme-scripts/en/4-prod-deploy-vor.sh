# ## Prod deploy: before the push
#
# *(Flow not yet anchored in CI.)*
#
# <!-- TODO(Part 1b): pre-deploy against sftp-server; verify the exact
#      content-sftp-upload call syntax. Harness must mock a visible content
#      change (sentinel value) before the upload — "after the push" (5)
#      checks exactly this sentinel as proof of a successful deploy.
#      The `.local` move trick (handoff section 6) runs in the wrapper,
#      BEFORE sourcing this script: set aside `.local/prod.yaml`
#      (`.local/prod.benutzer-config.yaml`) so that `cli config prod show`
#      below reports the real missing vars as on a first run; copy it back
#      afterwards. The `mv` stays a pure harness concern (no real first-time
#      user has a file to move) — `cli config prod show` and the `cp` hint
#      below are real user guidance and stay visible in the readme script. -->
#
# Unlike the preview deploy, `prod` needs the real credentials not only as
# GitHub secrets, but also locally in `.local/prod.yaml` — `content-sftp-upload`
# reads the pipeline config from the executing machine. (Acknowledged project
# weak point: prod credentials therefore exist twice, locally and in GitHub.)
#
# Show missing configuration values:
#
# ```bash
# cli config prod show
# ```
#
# Enter the values in `.local/prod.yaml` — copy your prepared file into place:
#
# ```bash
# cp <deine-vorbereitete-datei> .local/prod.yaml
# ```
#
# After that: with real content instead of fixtures.
#
# The content is already local: `cli setup … --with-sample-content` (from the
# quickstart) copies the demo content to `CONTENT_PATH`; deviate from that as
# needed. Upload the content to the server so the deploy uses it:
#
# ```bash
# cli python prod --phase build scripts/content-sftp-upload.py
# ```
#
# Trigger the deploy:
#
# ```bash
# git push <prod>
# ```
