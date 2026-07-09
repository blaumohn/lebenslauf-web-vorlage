<p align="right">Deutsch | <a href="README.en.md">English</a></p>

---

# Shared Hosting Site Toolkit

Build-, Runtime- und Deploy-Gerüst für kleine PHP-Shared-Hosting-Seiten.
Die Template-Ebene (Renderer, Schemas) trägt mehrsprachige Inhalte; die
PHP-Runtime bringt wiederverwendbare Module wie Token-Verwaltung, Security
(Rate-Limiting, CAPTCHA) und Task-Dispatching mit. Die aktuelle
Karriere-Profil-Vorlage — **Startseite, Lebenslauf, Kontakt, Blog** — ist
die erste konkrete Anwendung darauf.

[ysdani.com](https://ysdani.com) nutzt diese Vorlage produktiv — ausgewählte
Betriebs- und Architekturentscheidungen dahinter sind im
[Blog](https://ysdani.com/blog) dokumentiert, jeweils mit Verweis auf den Code.
[preview.ysdani.com](https://preview.ysdani.com) ist der Preview-Deploy dazu.

**Was kann es?**

- [Schnellstart](#schnellstart)
- [Private Ansicht einrichten](#private-ansicht-einrichten)
- [Preview-Deploy](#preview-deploy)
- [Prod-Deploy: vor dem Push](#prod-deploy-vor-dem-push)
- [Prod-Deploy: nach dem Push](#prod-deploy-nach-dem-push)
- [Inhalt veröffentlichen](#inhalt-veröffentlichen)

---

## Schnellstart
<small>*Ausgeführt in [tests/ci/readme-dev-user-flow.sh](https://github.com/blaumohn/shared-hosting-site-toolkit/blob/dev/tests/ci/readme-dev-user-flow.sh)*</small>

Lokal die Demo der Karriere-Profil-Vorlage zeigen.

```bash
git clone "$REPLACE_WITH_REPOSITORY_URL" shared-hosting-site-toolkit
cd shared-hosting-site-toolkit
PATH="$PWD/bin:$PATH"  # Hinweis: alternativ `php bin/cli ...` verwenden.
composer install
cli setup dev --with-sample-content
cli build dev
cli start dev > /tmp/lebenslauf-dev-server.log 2>&1 &
```

Die Demo läuft danach auf <http://127.0.0.1:8080/>.

[nach oben](#shared-hosting-site-toolkit)

---

## Private Ansicht einrichten
<small>*Ausgeführt in [tests/ci/readme-dev-user-flow.sh](https://github.com/blaumohn/shared-hosting-site-toolkit/blob/dev/tests/ci/readme-dev-user-flow.sh)*</small>

Die private Vollansicht öffnet sich per URL-Token, ohne Login.

```bash
token="$(cli token dev add demo)"
curl --fail --silent --show-error "http://127.0.0.1:8080/cv?token=${token}" \
  | grep -q '</html>'
```

[nach oben](#shared-hosting-site-toolkit)

---

## Preview-Deploy

*(Ablauf wird noch in CI verankert.)*

<!-- TODO(Teil 1b): pre-deploy in runner.py verankern; .local/preview.yaml im
     Prep-Wrapper beiseiteschieben (-> .local/preview.benutzer-config.yaml), damit
     `cli config` die echten fehlenden Vars meldet. -->

Voraussetzung: Shared-PHP-Hosting mit SFTP und ein SMTP-Konto (z. B. Mailtrap).

Konfigurationswerte einer Phase ansehen — lehrreich: Variablen sind an
Pipeline-Phasen gebunden:

```bash
cli config preview show --phase deploy
```

Darunter liegt [pipeline-config-spec](https://github.com/blaumohn/pipeline-config-spec-php):
Auflösung in Schichten, Secrets an erlaubte Quellen gebunden — Hintergrund im
[Blog-Artikel](https://ysdani.com/blog/system-statt-knoedel).

Alle noch offenen Werte als kommentierte Vorlage erzeugen:

```bash
cli config preview init
```

Die erzeugte `.local/preview.yaml` dient als Checkliste: die Werte — u. a.
SMTP, SFTP, `app_url` — als Secrets/Parameter im GitHub-Repo setzen;
Beschreibungen stehen als Kommentar in der Vorlage.

Deploy auslösen: ein Push auf `preview` nutzt Fixtures, kein Content-Upload:

```bash
git push <preview>
```

[nach oben](#shared-hosting-site-toolkit)

---

## Prod-Deploy: vor dem Push

*(Ablauf wird noch in CI verankert.)*

<!-- TODO(Teil 1b): pre-deploy gegen sftp-server; exakte content-sftp-upload-
     Aufrufsyntax verifizieren. Harness muss vor dem Upload eine sichtbare
     Inhalt-Änderung mocken (Sentinel-Wert) — „nach dem Push" (5) prüft genau
     diesen Sentinel als Beweis für den erfolgreichen Deploy.
     Der `.local`-Move-Trick (Abschnitt 6 des Handoffs) läuft im Wrapper,
     VOR dem Sourcen dieses Skripts: `.local/prod.yaml` kurz beiseiteschieben
     (`.local/prod.benutzer-config.yaml`), damit `cli config prod init`
     unten die Vorlage erzeugt wie beim Erstlauf; danach
     zurückkopieren. Der `mv` bleibt reine Harness-Sache (kein echter
     Erstnutzer hat eine Datei zum Verschieben) — `cli config prod init`
     und der `cp`-Hinweis unten sind dagegen echte Nutzerführung und bleiben
     im Readme-Skript sichtbar. -->

Anders als beim Preview-Deploy braucht `prod` die echten Zugangsdaten nicht nur
als GitHub-Secrets, sondern auch lokal in `.local/prod.yaml` — `content-sftp-upload`
liest die Pipeline-Config vom ausführenden Rechner. (Anerkannte Projekt-Schwäche:
Prod-Zugangsdaten liegen dadurch doppelt vor, lokal und in GitHub.)

Vorlage mit allen offenen Werten erzeugen und ausfüllen:

```bash
cli config prod init
```

Die erzeugte `.local/prod.yaml` trägt Beschreibung und Beispiel je Variable
als Kommentar ([Hintergrund](https://ysdani.com/blog/system-statt-knoedel)).
Wer schon eine ausgefüllte Datei hat, kopiert sie stattdessen an diesen
Platz:

```bash
cp <deine-vorbereitete-datei> .local/prod.yaml
```

Danach: mit echten Inhalten statt Fixtures.

Der Inhalt liegt schon lokal: `cli setup … --with-sample-content` (aus dem
Schnellstart) kopiert die Demo-Inhalte nach `CONTENT_PATH`; davon ausgehend
lässt sich abweichen. Inhalt auf den Server laden, damit der Deploy ihn verwendet:

```bash
cli python prod --phase deploy --phase build scripts/content-sftp-upload.py
```

`content-sftp-upload.py` liest sowohl `deploy` (SFTP-Zugangsdaten) als auch
`build` (`CONTENT_PATH`) — beide Phasen müssen mitgegeben werden.

Deploy auslösen:

```bash
git push <prod>
```

[nach oben](#shared-hosting-site-toolkit)

---

## Prod-Deploy: nach dem Push

*(Ablauf wird noch in CI verankert.)*

<!-- TODO(Teil 1b): post-deploy — bestehenden Smoke/QA aus bin/ci so umbauen,
     dass sein Prüf-Befehl hier liegt. Prüft hier den in Sektion 4 gemockten
     Inhalt-Sentinel (Nachweis, dass der Push den vorbereiteten Inhalt
     tatsächlich ausgeliefert hat). -->

Nach dem Push deployt GitHub Actions automatisch (Zwei-Baum-Slot-Switch). Der in
„vor dem Push" hochgeladene Inhalt erscheint auf der Prod-Seite — prüfen:

```bash
curl --fail --silent "https://<prod-domain>/" | grep -q '<inhalt-marker>'
```

[nach oben](#shared-hosting-site-toolkit)

---

## Inhalt veröffentlichen

*(Ablauf wird noch in CI verankert.)*

<!-- TODO(Teil 1b): post-deploy — Mock-Inhaltsänderung + cli publish + Assert
     Publish-Erfolg + Smoke, dass die Änderung auf Prod erscheint. -->

Nach dem Prod-Deploy einen Inhalt in `CONTENT_PATH` ändern und die
Aktualisierung veröffentlichen — ohne vollen Redeploy:

```bash
cli publish prod
```

[nach oben](#shared-hosting-site-toolkit)
