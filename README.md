# Lebenslauf Vorlage (PHP)

Deutsch | [English](README.en.md)

Modulare Lebenslauf-Vorlage für Shared Hosting (PHP + Twig). Inhalt und UI sind getrennt: Lebenslauf-Daten liegen außerhalb von `src/`, Labels/Übersetzungen liegen im Repo.

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

3) **Starten**

```bash
php bin/cli run dev
```

`run` kompiliert die Runtime-Config nach `var/config/config.php`.

Vor dem ersten Start `.local/dev-runtime.yaml` anlegen (siehe `src/resources/config/dev-runtime.yaml`).

## Daten bearbeiten

- YAML-Daten liegen standardmäßig in `.local/lebenslauf` (`LEBENSLAUF_DATEN_PFAD`).
- Nur Dateien `daten-<profil>.yaml` werden berücksichtigt (z. B. `daten-entwickler.yaml`).
- UI-Labels/Übersetzungen liegen in `src/resources/build/labels.json` (Repo-Beitrag möglich).
- Seitentexte (z. B. Seitentitel/Kontakt) liegen direkt in Twig-Templates.
- Build-Ressourcen (Schemas/Labels/Assets) liegen unter `src/resources/build/`.

Relevante Config-Werte (Runtime/Build):
- `LEBENSLAUF_PUBLIC_PROFILE` (Build)
- `LEBENSLAUF_LANG_DEFAULT`, `LEBENSLAUF_LANGS` (Runtime)
- `CONTACT_TO_EMAIL`, `CONTACT_FROM_EMAIL` (Runtime)

## Build (YAML -> JSON -> HTML)

```bash
php bin/cli build dev cv
php bin/cli build dev
```

## CLI-Modell

Phasen werden direkt ausgefuehrt:

```
cli <phase> <pipeline> [args]
```

Beispiele:

- `php bin/cli setup dev`
- `php bin/cli build dev cv`
- `php bin/cli run dev`
- `php bin/cli python dev --add-path . tests/py/smoke.py`
- `php bin/cli ip-hash reset`

## Python-Runner

- Config-Phase: `python`
- Defaults: `src/resources/config/dev-python.yaml`
- Wichtige Keys: `PYTHON_CMD`, `PYTHON_PATHS` (z. B. `src`)
- Zusatzelemente per CLI: `--add-path <pfad>`

## Projektstruktur

```
/lebenslauf-vorlage-2
├── src/
│   ├── resources/
│   │   ├── templates/          # Twig-Templates
│   │   └── build/              # Build-Ressourcen
│   │       ├── labels.json      # UI-Labels (Repo-Inhalt)
│   │       ├── assets/          # Build-Assets (CSS)
│   │       └── schemas/         # JSON-Schemas
│   ├── http/                   # HTTP-App
│   └── cli/                    # CLI-Tools
├── .local/
│   └── lebenslauf/             # YAML-Daten
├── src/resources/config/       # Config-Dateien + Manifest
├── tests/
└── docs/
```

## Umgebungsvariablen

Die Config-Policy (Pipeline-Phase = Pipeline + Phase) ist in `docs/ENVIRONMENTS.md` beschrieben.
Beispielwerte stehen in `src/resources/config/dev-runtime.yaml`, Regeln in `src/resources/config/config.manifest.yaml`.
Fuer Deployments wird die Runtime-Config als `var/config/config.php` erzeugt (siehe `php bin/cli config compile <pipeline>`).
