# ## Preview deploy
#
# *(Flow not yet anchored in CI.)*
#
# <!-- TODO(Part 1b): anchor pre-deploy in runner.py; set aside .local/preview.yaml
#      in the prep wrapper (-> .local/preview.benutzer-config.yaml) so that
#      `cli config` reports the real missing vars. -->
#
# Prerequisite: shared PHP hosting with SFTP and an SMTP account (e.g. Mailtrap).
#
# Inspect one phase's configuration values — instructive: variables are bound
# to pipeline phases:
#
# ```bash
# cli config preview show --phase deploy
# ```
#
# Underneath sits [pipeline-config-spec](https://github.com/blaumohn/pipeline-config-spec-php):
# layered resolution, secrets bound to allowed sources — background in the
# [blog post](https://ysdani.com/blog/pipeline-spec-in-action).
#
# Generate a commented template of all still-open values:
#
# ```bash
# cli config preview init
# ```
#
# The generated `.local/preview.yaml` doubles as a checklist: set the values —
# SMTP, SFTP, `app_url`, among others — as secrets/parameters in the GitHub
# repo; descriptions sit as comments in the template.
#
# Trigger a deploy: a push to `preview` uses fixtures, no content upload:
#
# ```bash
# git push <preview>
# ```
