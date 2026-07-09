# ## Prod-Deploy: nach dem Push
#
# *(Ablauf wird noch in CI verankert.)*
#
# <!-- TODO(Teil 1b): post-deploy — bestehenden Smoke/QA aus bin/ci so umbauen,
#      dass sein Prüf-Befehl hier liegt. Prüft hier den in Sektion 4 gemockten
#      Inhalt-Sentinel (Nachweis, dass der Push den vorbereiteten Inhalt
#      tatsächlich ausgeliefert hat). -->
#
# Nach dem Push deployt GitHub Actions automatisch (Zwei-Baum-Slot-Switch —
# [warum zwei feste Bäume](https://ysdani.com/blog/zwei-baeume-statt-symlink-flip),
# [atomarer Switch](https://ysdani.com/blog/atomarer-htaccess-switch)). Der in
# „vor dem Push" hochgeladene Inhalt erscheint auf der Prod-Seite — prüfen:
#
# ```bash
# curl --fail --silent "https://<prod-domain>/" | grep -q '<inhalt-marker>'
# ```
