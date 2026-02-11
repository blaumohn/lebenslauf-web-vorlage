# Environments

Dieses Dokument beschreibt die Config-Architektur mit Pipeline-Phase.

## Kontext

- `PIPELINE`: Projekt-Pipeline (z. B. `dev`, `smoketest`, `delivery`)
- `PHASE`: Pipeline-Phase (z. B. `setup`, `build`, `runtime`, `deploy`, `python`)
- `Pipeline-Phase`: Kombination aus `PIPELINE` und `PHASE` (z. B. `preview/runtime`; kartesisches Produkt)

## Referenzen

- Beispielwerte: im Manifest unter `meta.example` (keine aktiven Config-Dateien)
- Zusatzhinweise: im Manifest unter `meta.notes`
- Struktur/Regeln: `src/resources/config/config.manifest.yaml` (variables + pipelines)

## Config-Ladereihenfolge

1) `src/resources/config/common.yaml` (optional)
2) `src/resources/config/<PIPELINE>.yaml`
3) `.local/<PIPELINE>.yaml`
4) `src/resources/config/<PIPELINE>-<PHASE>.yaml`
5) `.local/<PIPELINE>-<PHASE>.yaml`

Beispiel: `src/resources/config/dev-build.yaml`, `.local/dev-runtime.yaml`.

## Regeln

- `src/resources/config/config.manifest.yaml` definiert `variables` (Bereiche + Quellen) und `pipelines`.
- `allowed` kann Gruppen aus `variables` oder einzelne Keys enthalten.
- `sources` im Manifest erzwingt, aus welchen Quellen Variablen kommen duerfen (z. B. nur `system` oder `local`).
- Build erzeugt `var/config/config.php` als aufgeloeste Runtime-Konfiguration.
- Runtime liest nur `var/config/config.php` (kein `getenv()/putenv()`).
- Kompilieren via `php bin/cli config compile <pipeline> --phase runtime` (Pipeline-Phase: `<pipeline>/runtime`).
- Inhaltliche Defaults (z. B. Lebenslauf-Sprachen) liegen in Config-Keys.
- Labels sind Teil des UI und liegen unter `src/resources/build/labels.json`.
- Die Phase `python` ist fuer den Python-Runner und nutzt `PYTHON_CMD`/`PYTHON_PATHS`.

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

## Hinweise

- `.local/` ist nicht versioniert und ueberschreibt jeweils `src/resources/config/`.
- CI/CD kann Werte per `.local/<PIPELINE>-<PHASE>.yaml` bereitstellen oder ueberschreiben.
- Required-Keys sollten in `src/resources/config/` liegen; `.local/` ist nur fuer Overrides/Secrets gedacht.

## IP_SALT Laufzeitverwaltung

- `IP_SALT` wird zur Laufzeit intern unter `var/state` verwaltet.
- Runtime legt `var/state/ip_salt.txt` und `var/state/ip_salt.fingerprint` atomar an.
- Bei fehlendem Salt oder Fingerprint-Mismatch wird Salt rotiert und IP-bezogener State bereinigt:
  - `var/tmp/captcha`
  - `var/tmp/ratelimit`
- Bewusste Rotation erfolgt ueber `php bin/cli ip-hash reset`.
- Alte externe `IP_SALT`-Werte sind nur noch als Uebergang fuer Migration gedacht.

## Smoke-Test-Parameter

- `SMOKE_CACHE_ROOT` setzt optionale Cache-Verzeichnisse für Composer/NPM/PIP.
- `TMPDIR` kann für Testläufe gesetzt werden, falls das System-Temp-Verzeichnis nicht nutzbar ist.
