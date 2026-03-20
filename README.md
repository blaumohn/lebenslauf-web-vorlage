# Lebenslauf Vorlage (PHP)

Deutsch | [English](README.en.md)

PHP-Vorlage für eine Lebenslauf-Site auf Shared-Hosting: öffentliche
Ansicht mit geschwärzten Kontaktdaten, privater Zugang per Token,
Build- und Deployment-Ablauf inklusive.
[Vollständige Dokumentation → docs.template.ysdani.com](https://docs.template.ysdani.com/de/)

## Einrichten

1. **Abhängigkeiten laden** — PHP-Pakete installieren, darunter das CLI.

   ```bash
   composer install
   ```

2. **Projekt einrichten** — Verzeichnisse anlegen, npm-Pakete und
   Python-Umgebung einrichten. Setzt Schritt 1 voraus.

   ```bash
   php bin/cli setup dev --reset-sample-content
   ```

3. **Lebenslauf bauen** — Beispieldaten in HTML-Ansichten rendern.

   ```bash
   php bin/cli build dev
   ```

4. **Starten** — Runtime-Config kompilieren und Entwicklungsserver starten.

   ```bash
   php bin/cli run dev
   ```

Eigene Daten und Konfiguration (E-Mail, SMTP, Deployment):
[Dokumentation → docs.template.ysdani.com](https://docs.template.ysdani.com/de/getting-started/)
