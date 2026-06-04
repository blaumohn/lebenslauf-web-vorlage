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
<small>*Quelle: `tests/ci/readme-dev-user-flow.sh` > `schnellstart()`*</small>

> ```bash
> git clone https://github.com/blaumohn/lebenslauf-web-vorlage lebenslauf-web-vorlage
> cd lebenslauf-web-vorlage
> export PATH="$PWD/bin:$PATH"  # statt export: php bin/cli …
> composer install
> cli setup dev --with-sample-content
> cli build dev
> cli start dev > /tmp/readme-dev-ux-server.log 2>&1 &
> dev_server_pid="$!"
> wait_for_dev_server
> ```

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
<small>*Quelle: `tests/ci/readme-dev-user-flow.sh` > `private_ansicht_einrichten()`*</small>

> ```bash
> local token
> token="$(cli token dev rotate default)"
> curl --fail --silent --show-error "http://127.0.0.1:8080/cv?token=${token}" \
>   | grep -q '</html>'
> ```

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
