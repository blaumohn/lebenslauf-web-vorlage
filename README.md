# Lebenslauf Vorlage (PHP)

Deutsch | [English](README.en.md)

PHP-Vorlage für eine Lebenslauf-Site auf Shared-Hosting: öffentliche
Ansicht mit geschwärzten Kontaktdaten, privater Zugang per Token,
Build- und Deployment-Ablauf inklusive.
Es baut auf der früheren statischen Vorlage aus [lebenslauf-vorlage](https://github.com/blaumohn/lebenslauf-vorlage) für Inhalt und i18n auf und ergänzt sie um den heutigen dynamischen PHP-Bereich.
[Vollständige Dokumentation → docs.template.ysdani.com](https://docs.template.ysdani.com/de/)

## Einrichten

1. **Abhängigkeiten laden** — PHP-Pakete installieren, darunter das CLI.

   ```bash
   composer install
   ```

2. **Projekt einrichten** — Verzeichnisse anlegen, npm-Pakete und
   Python-Umgebung einrichten. Setzt Schritt 1 voraus.

   ```bash
   php bin/cli setup dev --with-sample-content
   ```

   `--with-sample-content` legt Beispieldaten an, ohne bestehende Daten zu
   überschreiben.

3. **Lebenslauf bauen** — Beispieldaten in HTML-Ansichten rendern.

   ```bash
   php bin/cli build dev
   ```

4. **Starten** — Runtime-Config kompilieren und Entwicklungsserver starten.

   ```bash
   composer run dev
   ```

Eigene Daten und Konfiguration (E-Mail, SMTP, Deployment):
[Dokumentation → docs.template.ysdani.com](https://docs.template.ysdani.com/de/getting-started/)

## CI lokal prüfen

Die lokale CI läuft containerisiert mit getrennten Einstiegen für
`dev` und `preview`:

```bash
composer run ci:dev
composer run ci:preview
```

Für den Preview-Lauf wird der Compose-Stack nach Ende von `ci-preview`
automatisch beendet und anschließend heruntergefahren, damit der
langlebige Hilfsdienst `sftp-server` den Lauf nicht offen hält.

Optional richtet ein versionierter Pre-Push-Hook diesen Lauf lokal vor
jedem Push ein:

```bash
sh scripts/install-hooks.sh
```
