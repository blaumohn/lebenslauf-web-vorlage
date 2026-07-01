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
# Show missing configuration values (examples/descriptions of the variables:
# see [`manifest.yaml`](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/src/resources/pipeline-config/manifest.yaml);
# the values themselves belong in the GitHub repo secrets, not here):
#
# ```bash
# cli config preview show
# ```
#
# Set the reported values — SMTP, SFTP, `app_url`, among others — as
# secrets/parameters in the GitHub repo.
#
# Trigger a deploy: a push to `preview` uses fixtures, no content upload:
#
# ```bash
# git push <preview>
# ```
