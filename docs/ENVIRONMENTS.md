# Environments

Dieses Dokument beschreibt die Env-Architektur mit Pipeline/Phase/Profil.

## Kontext

- `PIPELINE`: Projekt-Pipeline (z. B. `dev`, `smoketest`, `delivery`)
- `PHASE`: Pipeline-Phase (z. B. `setup`, `build`, `runtime`, `deploy`)
- `PROFILE`: optionales Profil (z. B. `dev`, `preview`, `prod`)

## Referenzen

- Beispielwerte: `.env.template`
- Struktur/Regeln: `config/env.manifest.yaml` (variables + pipelines)

## Dotenv-Ladereihenfolge

1) System-Env
2) `.env`
3) `.env.local`
4) `.env.<PIPELINE>`
5) `.env.<PIPELINE>.local`
6) `.env.<PIPELINE>.<PHASE>`
7) `.env.<PIPELINE>.<PHASE>.local`
8) `.env.<PIPELINE>.<PROFILE>` (optional)
9) `.env.<PIPELINE>.<PROFILE>.local` (optional)
10) `.env.<PIPELINE>.<PROFILE>.<PHASE>` (optional)
11) `.env.<PIPELINE>.<PROFILE>.<PHASE>.local` (optional)

Beispiel: `.env.dev.build`, `.env.dev.runtime`, `.env.dev.preview.runtime`.

## Regeln

- `config/env.manifest.yaml` definiert `variables` (Bereiche + Quellen) und `pipelines`.
- `allowed` kann Gruppen aus `variables` oder einzelne Keys enthalten.
- `sources` im Manifest erzwingt, aus welchen Quellen Variablen kommen dürfen (z. B. nur `system` oder `local`).
- Build erzeugt `var/config/env.php` als aufgeloeste Runtime-Konfiguration.
- Runtime liest nur `var/config/env.php` (kein `getenv()/putenv()`).
- Kompilieren via `php bin/cli env compile --phase runtime --pipeline <name> --profile <name>`.
- Inhaltliche Defaults gehoeren in `.local/content.ini` (keine Env-Variable).
- Labels sind Teil des UI und liegen unter `src/resources/labels.json`.

## Hinweise

- Für lokale Entwicklung kann `setup` eine `.env.local` aus `.env.template` ableiten.
- CI/CD setzt Variablen über Workflow-Umgebungen.

## Smoke-Test-Parameter

- `SMOKE_CACHE_ROOT` setzt optionale Cache-Verzeichnisse für Composer/NPM/PIP.
- `TMPDIR` kann für Testläufe gesetzt werden, falls das System-Temp-Verzeichnis nicht nutzbar ist.
