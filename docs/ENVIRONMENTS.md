# Environments

Dieses Dokument beschreibt die Config-Architektur mit Pipeline-Phase.

## Kontext

- `PIPELINE`: Projekt-Pipeline (z. B. `dev`, `smoketest`, `delivery`)
- `PHASE`: Pipeline-Phase (z. B. `setup`, `build`, `runtime`, `deploy`, `python`)
- `Pipeline-Phase`: Kombination aus `PIPELINE` und `PHASE` (z. B. `preview/runtime`; kartesisches Produkt)

## Referenzen

- Beispielwerte: im Manifest unter `meta.example` (keine aktiven Config-Dateien)
- Zusatzhinweise und Abhängigkeiten: im Manifest unter `meta.notes`
- Struktur/Regeln: `src/resources/config/config.manifest.yaml`
  (`variable-groups` + `pipelines`)

## Config-Ladereihenfolge

1) `src/resources/config/common.yaml` (optional)
2) `src/resources/config/<PIPELINE>.yaml`
3) `.local/<PIPELINE>.yaml`
4) `src/resources/config/<PIPELINE>-<PHASE>.yaml`
5) `.local/<PIPELINE>-<PHASE>.yaml`

Beispiel: `src/resources/config/dev-build.yaml`, `.local/dev-runtime.yaml`.

## Regeln

- `src/resources/config/config.manifest.yaml` definiert `variable-groups`
  (Gruppen, Variablen, `meta`, `sources`) und `pipelines`.
- `pipelines.common.<phase>` traegt die phasenweite Schnittmenge.
- `pipelines.<pipeline>.<phase>` traegt nur pipeline-spezifische Differenzen.
- Eine Gruppen-Referenz in einer Phase nutzt entweder `select: "*"` fuer
  die ganze Gruppe oder `variables` fuer eine explizite Teilmenge.
- `sources` im Manifest erzwingt, aus welchen Quellen Variablen kommen
  duerfen (z. B. nur `system` oder `local`).
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
- Vollgruppen oder Teilmengen werden ueber `select: "*"` bzw. `variables`
  beschrieben; es gibt keine zusaetzliche `required`-/`policy`-Logik.
- Fachliche Abhaengigkeiten wie `MAIL_STDOUT` versus `SMTP_*` werden in
  `meta.notes` dokumentiert statt im Manifest gesondert ausgewertet.
- Im aktuellen Zielstand bleibt `MAIL_STDOUT` in `common/runtime`; `SMTP_*`
  gehoert nur noch zu `preview/runtime`.
- `setup --copy-sample-content` kopiert nur die feste Fixture nach
  `.local/lebenslauf/daten-<LEBENSLAUF_PUBLIC_PROFILE>.yaml`.
- `LEBENSLAUF_PUBLIC_PROFILE` gehoert dafuer nicht in die gemeinsame
  `setup`-Phase. Die Pipeline, die Sample-Seeding anbietet, muss den Key
  fuer ihre `setup`-Phase im Manifest erlauben und einen Wert im
  Pipeline-Spec liefern.

## IP_SALT Laufzeitverwaltung

- `IP_SALT` wird zur Laufzeit intern unter `var/state` verwaltet.
- Runtime verwaltet den Zustand atomar in `var/state/ip_salt.state.json`.
- Die State-Datei enthält `salt`, `fingerprint`, `status`, `generation`, `updated_at`.
- Marker-Status:
  - `IN_PROGRESS` während eines laufenden Reset-/Recovery-Schritts.
  - `READY` nach erfolgreichem Abschluss.
- Bei fehlendem Salt oder Fingerprint-Mismatch wird Salt rotiert und IP-bezogener State bereinigt:
  - `var/tmp/captcha`
  - `var/tmp/ratelimit`
- Bewusste Rotation erfolgt ueber `php bin/cli ip-hash reset`.

## Runtime-Concurrency (Stand 2026-02-13)

- Der gemeinsame Runtime-Rahmen besteht aus:
  - `RuntimeLockRunner` (`symfony/lock`, Polling + Timeout, Fail-Fast).
  - `RuntimeAtomicWriter` (atomare Dateiersetzung ueber Temp-Datei + `rename`).
- Dieser Rahmen ist produktiv fuer `IP_SALT` im Einsatz.
- `ISS-012` erweitert denselben Rahmen auf weitere Runtime-Modelle:
  - `RateLimiter` (`var/tmp/ratelimit`)
  - `CaptchaService` (`var/tmp/captcha`)
  - `TokenService` (`var/state/tokens`)
- Ziel: konsistente Read-Modify-Write-Pfade unter Parallelzugriff.

## Smoke-Test-Parameter

- `SMOKE_CACHE_ROOT` setzt optionale Cache-Verzeichnisse für Composer/NPM/PIP.
- `TMPDIR` kann für Testläufe gesetzt werden, falls das System-Temp-Verzeichnis nicht nutzbar ist.
