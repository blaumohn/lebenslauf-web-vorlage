<p align="right">Deutsch | <a href="README.en.md">English</a></p>

---

# Lebenslauf-Web-Vorlage (PHP)

PHP-Shared-Hosting-Boilerplate „vom `<div>` bis DevOps" für kleine Seiten —
mit einer Karriere-Profil-Vorlage als Beispiel: **Startseite, Lebenslauf,
Kontakt, Blog**.

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
<small>*[readme-scripts/1-schnellstart.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/1-schnellstart.sh)*</small>

Lokal die Demo der Karriere-Profil-Vorlage zeigen.

```bash
git clone "$REPLACE_WITH_REPOSITORY_URL" lebenslauf-web-vorlage
cd lebenslauf-web-vorlage
PATH="$PWD/bin:$PATH"  # Hinweis: alternativ `php bin/cli ...` verwenden.
composer install
cli setup dev --with-sample-content
cli build dev
cli start dev > /tmp/lebenslauf-dev-server.log 2>&1 &
```

Die Demo läuft danach auf <http://127.0.0.1:8080/>.

[nach oben](#lebenslauf-web-vorlage-php)

---

## Private Ansicht einrichten
<small>*[readme-scripts/2-private-ansicht.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/2-private-ansicht.sh)*</small>

Die private Vollansicht öffnet sich per URL-Token, ohne Login.

```bash
token="$(cli token dev rotate demo)"
curl --fail --silent --show-error "http://127.0.0.1:8080/cv?token=${token}" \
  | grep -q '</html>'
```

[nach oben](#lebenslauf-web-vorlage-php)

---

## Preview-Deploy
<small>*[readme-scripts/3-preview-deploy.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/3-preview-deploy.sh)*</small>

*(Ablauf wird noch in CI verankert.)*

<!-- TODO(Teil 1b): pre-deploy in runner.py verankern; .local/preview.yaml im
     Prep-Wrapper beiseiteschieben (-> .local/preview.benutzer-config.yaml), damit
     `cli config` die echten fehlenden Vars meldet. -->

Voraussetzung: Shared-PHP-Hosting mit SFTP und ein SMTP-Konto (z. B. Mailtrap).

Fehlende Konfigurationswerte anzeigen (Beispiele/Beschreibungen der Variablen:
siehe [`manifest.yaml`](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/src/resources/pipeline-config/manifest.yaml);
die Werte selbst gehören in die GitHub-Repo-Secrets, nicht hierher):

```bash
cli config preview show
```

Die gemeldeten Werte — u. a. SMTP, SFTP, `app_url` — als Secrets/Parameter im
GitHub-Repo setzen.

Deploy auslösen: ein Push auf `preview` nutzt Fixtures, kein Content-Upload:

```bash
git push <preview>
```

[nach oben](#lebenslauf-web-vorlage-php)

---

## Prod-Deploy: vor dem Push
<small>*[readme-scripts/4-prod-deploy-vor.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/4-prod-deploy-vor.sh)*</small>

*(Ablauf wird noch in CI verankert.)*

<!-- TODO(Teil 1b): pre-deploy gegen sftp-server; exakte content-sftp-upload-
     Aufrufsyntax verifizieren. Harness muss vor dem Upload eine sichtbare
     Inhalt-Änderung mocken (Sentinel-Wert) — „nach dem Push" (5) prüft genau
     diesen Sentinel als Beweis für den erfolgreichen Deploy.
     Der `.local`-Move-Trick (Abschnitt 6 des Handoffs) läuft im Wrapper,
     VOR dem Sourcen dieses Skripts: `.local/prod.yaml` kurz beiseiteschieben
     (`.local/prod.benutzer-config.yaml`), damit `cli config prod show`
     unten die echten fehlenden Vars meldet wie beim Erstlauf; danach
     zurückkopieren. Der `mv` bleibt reine Harness-Sache (kein echter
     Erstnutzer hat eine Datei zum Verschieben) — `cli config prod show`
     und der `cp`-Hinweis unten sind dagegen echte Nutzerführung und bleiben
     im Readme-Skript sichtbar. -->

Anders als beim Preview-Deploy braucht `prod` die echten Zugangsdaten nicht nur
als GitHub-Secrets, sondern auch lokal in `.local/prod.yaml` — `content-sftp-upload`
liest die Pipeline-Config vom ausführenden Rechner. (Anerkannte Projekt-Schwäche:
Prod-Zugangsdaten liegen dadurch doppelt vor, lokal und in GitHub.)

Fehlende Konfigurationswerte anzeigen:

```bash
cli config prod show
```

Werte in `.local/prod.yaml` eintragen — die vorbereitete Datei an ihren Platz
kopieren:

```bash
cp <deine-vorbereitete-datei> .local/prod.yaml
```

Danach: mit echten Inhalten statt Fixtures.

Der Inhalt liegt schon lokal: `cli setup … --with-sample-content` (aus dem
Schnellstart) kopiert die Demo-Inhalte nach `CONTENT_PATH`; davon ausgehend
lässt sich abweichen. Inhalt auf den Server laden, damit der Deploy ihn verwendet:

```bash
cli python prod --phase build scripts/content-sftp-upload.py
```

Deploy auslösen:

```bash
git push <prod>
```

[nach oben](#lebenslauf-web-vorlage-php)

---

## Prod-Deploy: nach dem Push
<small>*[readme-scripts/5-prod-deploy-nach.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/5-prod-deploy-nach.sh)*</small>

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

[nach oben](#lebenslauf-web-vorlage-php)

---

## Inhalt veröffentlichen
<small>*[readme-scripts/6-inhalt-veroeffentlichen.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/readme-scripts/6-inhalt-veroeffentlichen.sh)*</small>

*(Ablauf wird noch in CI verankert.)*

<!-- TODO(Teil 1b): post-deploy — Mock-Inhaltsänderung + cli publish + Assert
     Publish-Erfolg + Smoke, dass die Änderung auf Prod erscheint. -->

Nach dem Prod-Deploy einen Inhalt in `CONTENT_PATH` ändern und die
Aktualisierung veröffentlichen — ohne vollen Redeploy:

```bash
cli publish prod
```

[nach oben](#lebenslauf-web-vorlage-php)
