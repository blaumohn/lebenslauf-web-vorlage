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
# Konfigurationswerte einer Phase ansehen — lehrreich: Variablen sind an
# Pipeline-Phasen gebunden:
#
# ```bash
# cli config preview show --phase deploy
# ```
#
# Darunter liegt [pipeline-config-spec](https://github.com/blaumohn/pipeline-config-spec-php):
# Auflösung in Schichten, Secrets an erlaubte Quellen gebunden — Hintergrund im
# [Blog-Artikel](https://ysdani.com/blog/system-statt-knoedel).
#
# Alle noch offenen Werte als kommentierte Vorlage erzeugen:
#
# ```bash
# cli config preview init
# ```
#
# Die erzeugte `.local/preview.yaml` dient als Checkliste: die Werte — u. a.
# SMTP, SFTP, `app_url` — als Secrets/Parameter im GitHub-Repo setzen;
# Beschreibungen stehen als Kommentar in der Vorlage.
#
# Deploy auslösen: ein Push auf `preview` nutzt Fixtures, kein Content-Upload:
#
# ```bash
# git push <preview>
# ```
