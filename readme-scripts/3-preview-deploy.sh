# ## Preview-Deploy
#
# *(Ablauf wird noch in CI verankert.)*
#
# <!-- TODO(Teil 1b): pre-deploy in runner.py verankern; .local/preview.yaml im
#      Prep-Wrapper beiseiteschieben (-> .local/preview.benutzer-config.yaml), damit
#      `cli config` die echten fehlenden Vars meldet. -->
#
# Voraussetzung: Shared-PHP-Hosting mit SFTP und ein SMTP-Konto (z. B. Mailtrap).
#
# Fehlende Konfigurationswerte anzeigen (Beispiele/Beschreibungen der Variablen:
# siehe [`manifest.yaml`](https://github.com/blaumohn/shared-hosting-site-toolkit/blob/dev/src/resources/pipeline-config/manifest.yaml);
# die Werte selbst gehören in die GitHub-Repo-Secrets, nicht hierher):
#
# ```bash
# cli config preview show
# ```
#
# Die gemeldeten Werte — u. a. SMTP, SFTP, `app_url` — als Secrets/Parameter im
# GitHub-Repo setzen.
#
# Deploy auslösen: ein Push auf `preview` nutzt Fixtures, kein Content-Upload:
#
# ```bash
# git push <preview>
# ```
