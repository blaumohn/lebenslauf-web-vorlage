# Lebenslauf-Web-Vorlage (PHP)

Deutsch | [English](README.en.md)

[Projekt einschätzen](#projekt-einschätzen) · [Schnellstart](#schnellstart) · [Überblick](#überblick) · [Private Ansicht einrichten](#private-ansicht-einrichten) · [Technische Besonderheiten](#technische-besonderheiten)

---

## Projekt einschätzen
<small>*Vollständiger Abschnitt: [Projekt einschätzen](https://docs.template.ysdani.com/de/getting-started/projektprofil/)*</small>

> PHP-Seitenstarter für persönliche Websites auf Shared Hosting —
> entwickelt und betrieben als eigenes Projekt.
> Öffentliche Ansicht mit geschwärzten Kontaktdaten,
> token-gesicherte private Vollansicht ohne Login,
> fertige Dev- und CI/CD-Pipeline.
>
> **Demo:** [preview.ysdani.com](https://preview.ysdani.com)
>
> Das Projekt entstand als konkreter Liefernachweis: von der Anforderung
> über Architekturentscheidungen bis zum produktiven Deployment.
> Die öffentliche Dokumentation macht Entscheidungen, Abläufe und
> Qualitätsnachweise transparent nachvollziehbar.

---

## Schnellstart
<small>*Vollständiger Abschnitt: [Schnellstart](https://docs.template.ysdani.com/de/getting-started/schnellstart/)*</small>

> ```bash
> composer install
> php bin/cli setup dev --with-sample-content
> php bin/cli build dev
> composer run dev
> ```
>
> `--with-sample-content` legt Beispieldaten an, ohne bestehende Daten zu überschreiben.
> `composer run dev` kompiliert die Runtime-Konfiguration und startet den Entwicklungsserver.

---

## Überblick
<small>*Vollständiger Abschnitt: [Überblick](https://docs.template.ysdani.com/de/getting-started/ueberblick/)*</small>

> Betriebsfertiger PHP-Seitenstarter für Shared Hosting: öffentliche Ansicht
> mit geschwärzten Kontaktdaten, token-gesicherte private Vollansicht ohne Login,
> i18n-Lebenslauf-Inhalt in YAML und fertige Dev- und CI/CD-Pipelines.
>
> - **Öffentliche Sicht** — Lebenslauf mit geschwärzten Kontaktdaten
> - **Private Sicht** — vollständige Ansicht per URL-Token (`/cv?token=…`), kein Login nötig
> - **i18n YAML-Inhalt** — Lebenslauf-Daten in Deutsch und Englisch
> - **Fertige Pipelines** — lokale Entwicklung mit Docker, CI-Prüfung und SFTP-Deployment
> - **Sicherheitsschicht** — Rate-Limit, CAPTCHA und IP-Salt-Rotation für das Kontaktformular

---

## Private Ansicht einrichten
<small>*Vollständiger Abschnitt: [Private Ansicht einrichten](https://docs.template.ysdani.com/de/getting-started/private-ansicht/)*</small>

> Die private Ansicht zeigt den vollständigen Lebenslauf per URL-Token — kein Login, kein Account.
> Einrichtung nach `composer run dev`:
>
> 1. **Token erzeugen** — `php bin/cli token rotate preview`
> 2. **`.local/preview.yaml` anlegen** — `APP_ROOT_URL` und Deployment-Werte setzen.
>    Datei mit `chmod 600` sichern: nur vertrauenswürdige Benutzer dürfen sie lesen.
> 3. **GitHub Secrets/Vars setzen** — Pflichtfelder aus `src/resources/pipeline-config/manifest.yaml`,
>    Abschnitt `pipelines.preview`, im GitHub-Environment `preview`.
> 4. **CI lokal prüfen** — `composer run ci:preview`
> 5. **Aufrufen** — `https://domain/cv?token=<token>`

---

## Technische Besonderheiten
<small>*Vollständiger Abschnitt: [Technische Besonderheiten](https://docs.template.ysdani.com/de/getting-started/technische-besonderheiten/)*</small>

> - **Zwei-Baum-Deploy** — atomares Deployment ohne Ausfallzeit auf Shared Hosting,
>   ohne Blue-Green-Infrastruktur (vendor-a/b + app-a/b)
> - **Pipeline-Spec** — sprachenneutrale Konfiguration über PHP-, Python- und Shell-Grenzen,
>   Einweg-Datenfluss, maschinenlesbare YAML-Spezifikation
> - **Pipeline-Phasen-Modell** — App kennt ihren eigenen Laufzeitzustand (setup, build, runtime, deploy);
>   trennt CLI- von HTTP-Laufzeit-Komplexität

---
