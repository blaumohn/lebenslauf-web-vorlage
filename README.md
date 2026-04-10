# Lebenslauf Vorlage (PHP)

Deutsch | [English](README.en.md)

Modulare Lebenslauf-Vorlage für Shared Hosting (PHP + Twig). Inhalt und UI
sind getrennt: Lebenslauf-Daten liegen außerhalb von `src/`,
Labels/Übersetzungen liegen im Repo.

Dieses Repo enthält den Quelltext der App.
Die öffentliche Projektdoku liegt nicht unter `docs/` in diesem Repo,
sondern in GitHub Pages:

- Öffentliche Doku: <https://docs.template.ysdani.com/de/>
- GitHub-Pages-Repo: <https://github.com/blaumohn/lebenslauf-web-vorlage-docs>
- Quellcode-Repo: <https://github.com/blaumohn/lebenslauf-web-vorlage>

Bis zum Preview-Deployment ist `dev` der maßgebliche Arbeitsbranch.
Die Verweise bleiben hier absichtlich branch-neutral.

Funktionen:

- Schnelle Anpassung der Lebenslauf-Daten ohne Code-Änderungen
- Mehrsprachigkeit mit statischen HTML-Ausgaben pro Sprache
- Statischer Build für Public/Private-Varianten

## Verwendung

1) **Installieren**

```bash
composer install
```

2) **Setup**

```bash
php bin/cli setup dev
```

Optional für Demo-Inhalt, ohne bestehende Daten zu überschreiben:

```bash
php bin/cli setup dev --copy-sample-content
```

3) **Starten**

```bash
php bin/cli run dev
```

`run` kompiliert die Runtime-Config nach `var/config/config.php`.

Vor dem ersten Start `.local/dev-runtime.yaml` anlegen
(siehe `src/resources/config/dev-runtime.yaml`).

## Daten bearbeiten

- YAML-Daten liegen standardmäßig in `.local/lebenslauf`
  (`LEBENSLAUF_DATEN_PFAD`).
- Nur Dateien `daten-<profil>.yaml` werden berücksichtigt
  (z. B. `daten-entwickler.yaml`).
- UI-Labels/Übersetzungen liegen in `src/resources/build/labels.json`
  (Repo-Beitrag möglich).
- Seitentexte (z. B. Seitentitel/Kontakt) liegen direkt in Twig-Templates.
- Build-Ressourcen (Schemas/Labels/Assets) liegen unter
  `src/resources/build/`.

Relevante Config-Werte:
- `LEBENSLAUF_PUBLIC_PROFILE` (Build; in `dev` auch Setup-Seed)
- `LEBENSLAUF_LANG_DEFAULT`, `LEBENSLAUF_LANGS` (Runtime)
- `CONTACT_TO_EMAIL`, `MAIL_STDOUT` (Kontakt-Runtime)
- `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME` und weitere `SMTP_*`-Werte
  (nur `preview`-Runtime, Gruppe `smtp`)
- `CONTACT_TO_EMAIL` muss in Runtime-Phasen eine gültige E-Mail-Adresse sein.

Der Setup-Sample-Pfad nutzt die feste Fixture
`src/resources/fixtures/lebenslauf/daten-gueltig.yaml` und kopiert sie bei
Bedarf nach `.local/lebenslauf/daten-<LEBENSLAUF_PUBLIC_PROFILE>.yaml`.
Der Profilwert muss in der Pipeline-Spec fuer die Setup-Phase erlaubt und
gesetzt sein; in `dev` kommt er fuer Setup aus
`src/resources/config/dev-setup.yaml`.

## Build (YAML -> JSON -> HTML)

```bash
php bin/cli build dev cv
php bin/cli build dev
```

## Weitere Doku

- Einstieg: <https://docs.template.ysdani.com/de/getting-started/>
- Betrieb und Runbooks: <https://docs.template.ysdani.com/de/operations/>
- Richtlinien und Entscheidungen: <https://docs.template.ysdani.com/de/policies/>

## CLI-Modell

Phasen werden direkt ausgeführt:

```text
cli <phase> <pipeline> [args]
```

Beispiele:

- `php bin/cli setup dev`
- `php bin/cli build dev cv`
- `php bin/cli run dev`
- `php bin/cli python dev --add-path . tests/py/smoke.py`

## Python-Runner

- Config-Phase: `python`
- Defaults: `src/resources/config/dev-python.yaml`
- Wichtige Keys: `PYTHON_CMD`, `PYTHON_PATHS` (z. B. `src`)
- Zusatzelemente per CLI: `--add-path <pfad>`

## Projektstruktur

```text
/lebenslauf-vorlage-2
├── src/
│   ├── resources/
│   │   ├── templates/          # Twig-Templates
│   │   └── build/              # Build-Ressourcen
│   │       ├── labels.json     # UI-Labels (Repo-Inhalt)
│   │       ├── assets/         # Build-Assets (CSS)
│   │       └── schemas/        # JSON-Schemas
│   ├── http/                   # HTTP-App
│   └── cli/                    # CLI-Tools
├── .local/
│   └── lebenslauf/             # YAML-Daten
├── src/resources/config/       # Config-Dateien + Manifest
├── tests/
└── docs/
```

## Umgebungsvariablen

Die Config-Policy (Pipeline/Phase) ist in `docs/ENVIRONMENTS.md`
beschrieben.
Beispielwerte stehen in `src/resources/config/dev-runtime.yaml`,
Regeln in `src/resources/config/config.manifest.yaml`.
Für Deployments wird die Runtime-Config als `var/config/config.php`
erzeugt (siehe `php bin/cli config compile <pipeline>`).
