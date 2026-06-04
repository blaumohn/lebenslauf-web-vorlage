# Lebenslauf-Web-Vorlage (PHP)

Deutsch | [English](README.en.md)

[Projekt einschätzen](#projekt-einschätzen) · [Schnellstart](#schnellstart) · [Überblick](#überblick) · [Private Ansicht einrichten](#private-ansicht-einrichten) · [Technische Besonderheiten](#technische-besonderheiten)

---

## Projekt einschätzen
<small>*Doku: [Projekt einschätzen](https://docs.template.ysdani.com/de/getting-started/projektprofil/)*</small>

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
<small>*[tests/ci/readme-dev-user-flow.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/tests/ci/readme-dev-user-flow.sh#L32-L42)*</small>
<small>*Doku: [Schnellstart](https://docs.template.ysdani.com/de/getting-started/schnellstart/)*</small>

> ```bash
> git clone "$REPLACE_WITH_REPOSITORY_URL" lebenslauf-web-vorlage
> cd lebenslauf-web-vorlage
> PATH="$PWD/bin:$PATH"  # Hinweis: alternativ `php bin/cli ...` verwenden.
> composer install
> cli setup dev --with-sample-content
> cli build dev
> cli start dev > /tmp/readme-dev-ux-server.log 2>&1 &
> dev_server_pid="$!"
> wait_for_dev_server
> ```

---

## Überblick
<small>*Doku: [Überblick](https://docs.template.ysdani.com/de/getting-started/ueberblick/)*</small>

> Betriebsfertiger PHP-Seitenstarter für Shared Hosting: öffentliche Ansicht
> mit geschwärzten Kontaktdaten, token-gesicherte private Vollansicht ohne Login,
> i18n-Lebenslauf-Inhalt in YAML und fertige Dev- und CI/CD-Pipelines.
>
> - **[Öffentliche Sicht](https://docs.template.ysdani.com/de/specs/systeme/app/)** —
>   Lebenslauf mit geschwärzten Kontaktdaten.
> - **[Private Sicht](https://docs.template.ysdani.com/de/getting-started/private-ansicht/)** —
>   vollständige Ansicht per URL-Token (`/cv?token=…`), kein Login nötig.
> - **[i18n YAML-Inhalt](https://docs.template.ysdani.com/de/specs/systeme/app/)** —
>   Lebenslauf-Daten in Deutsch und Englisch.
> - **[Fertige Pipelines](https://docs.template.ysdani.com/de/areas/cli-build/)** —
>   lokale Entwicklung mit Docker, CI-Prüfung und SFTP-Deployment.
> - **[Sicherheitsschicht](https://docs.template.ysdani.com/de/areas/http-runtime/)** —
>   Rate-Limit, CAPTCHA und IP-Salt-Rotation für das Kontaktformular.

---

## Private Ansicht einrichten
<small>*[tests/ci/readme-dev-user-flow.sh](https://github.com/blaumohn/lebenslauf-web-vorlage/blob/dev/tests/ci/readme-dev-user-flow.sh#L44-L49)*</small>
<small>*Doku: [Private Ansicht einrichten](https://docs.template.ysdani.com/de/getting-started/private-ansicht/)*</small>

> ```bash
> local token
> token="$(cli token dev rotate default)"
> curl --fail --silent --show-error "http://127.0.0.1:8080/cv?token=${token}" \
>   | grep -q '</html>'
> ```

---

## Technische Besonderheiten
<small>*Doku: [Technische Besonderheiten](https://docs.template.ysdani.com/de/getting-started/technische-besonderheiten/)*</small>

> - **[Zwei-Baum-Deploy](https://docs.template.ysdani.com/de/jira/issues/J01-135/steps/J01-142/)** —
>   atomares Deployment ohne Ausfallzeit auf Shared Hosting, ohne
>   Blue-Green-Infrastruktur (vendor-a/b + app-a/b);
>   der [Slot-Schalter](https://docs.template.ysdani.com/de/areas/deploy/slot-switch/)
>   hält den sichtbaren Umschaltpunkt nachvollziehbar.
> - **[Pipeline-Spec](https://docs.template.ysdani.com/de/specs/systeme/pipeline-spec/)** —
>   sprachenneutrale Konfiguration über PHP-, Python- und Shell-Grenzen,
>   Einweg-Datenfluss, maschinenlesbare YAML-Spezifikation.
> - **[Pipeline-Phasen-Modell](https://docs.template.ysdani.com/de/areas/cli-build/)** —
>   App kennt ihren eigenen Laufzeitzustand (setup, build, runtime, deploy);
>   trennt CLI- von HTTP-Laufzeit-Komplexität.

---
