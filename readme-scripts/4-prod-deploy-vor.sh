# ## Prod-Deploy: vor dem Push
#
# *(Ablauf wird noch in CI verankert.)*
#
# <!-- TODO(Teil 1b): pre-deploy gegen sftp-server; exakte content-sftp-upload-
#      Aufrufsyntax verifizieren. Harness muss vor dem Upload eine sichtbare
#      Inhalt-Änderung mocken (Sentinel-Wert) — „nach dem Push" (5) prüft genau
#      diesen Sentinel als Beweis für den erfolgreichen Deploy.
#      Der `.local`-Move-Trick (Abschnitt 6 des Handoffs) läuft im Wrapper,
#      VOR dem Sourcen dieses Skripts: `.local/prod.yaml` kurz beiseiteschieben
#      (`.local/prod.benutzer-config.yaml`), damit `cli config prod init`
#      unten die Vorlage erzeugt wie beim Erstlauf; danach
#      zurückkopieren. Der `mv` bleibt reine Harness-Sache (kein echter
#      Erstnutzer hat eine Datei zum Verschieben) — `cli config prod init`
#      unten ist dagegen echte Nutzerführung und bleibt im Readme-Skript
#      sichtbar. -->
#
# Anders als beim Preview-Deploy braucht `prod` die echten Zugangsdaten nicht nur
# als GitHub-Secrets, sondern auch lokal in `.local/prod.yaml` — `content-sftp-upload`
# liest die Pipeline-Config vom ausführenden Rechner. (Anerkannte Projekt-Schwäche:
# Prod-Zugangsdaten liegen dadurch doppelt vor, lokal und in GitHub.)
#
# Vorlage mit allen offenen Werten erzeugen und ausfüllen:
#
# ```bash
# cli config prod init
# ```
#
# Die erzeugte `.local/prod.yaml` trägt Beschreibung und Beispiel je Variable
# als Kommentar ([Hintergrund](https://ysdani.com/blog/system-statt-knoedel)).
#
# Danach: mit echten Inhalten statt Fixtures.
#
# Der Inhalt liegt schon lokal: `cli setup … --with-sample-content` (aus dem
# Schnellstart) kopiert die Demo-Inhalte nach `CONTENT_PATH`; davon ausgehend
# lässt sich abweichen. Inhalt auf den Server laden, damit der Deploy ihn verwendet:
#
# ```bash
# cli python prod --phase deploy --phase build scripts/content-sftp-upload.py
# ```
#
# `content-sftp-upload.py` liest sowohl `deploy` (SFTP-Zugangsdaten) als auch
# `build` (`CONTENT_PATH`) — beide Phasen müssen mitgegeben werden.
#
# Deploy auslösen:
#
# ```bash
# git push <prod>
# ```
