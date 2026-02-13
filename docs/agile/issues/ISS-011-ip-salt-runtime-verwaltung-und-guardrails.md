# ISSUE: IP_SALT Runtime-Verwaltung und Guardrails

## Typ
- Issue (Delivery/Design)

## Status
- Erledigt (abgeschlossen am 2026-02-12)

## Abgeleitet aus
- [ISS-010](ISS-010-preview-workflow-testmatrix-und-entscheidungen.md) (`IP_SALT`-Strategie)

## Problem
- `IP_SALT` war über externe Config/CLI-Rotation mitgesteuert, obwohl es fachlich ein runtime-interner Betriebszustand ist.
- Rotation ohne konsistente Bereinigung von IP-bezogenem State konnte zu Inkonsistenzen führen.
- Externe Pflicht-/Override-Logik für `IP_SALT` erhöhte Komplexität ohne stabilen Mehrwert.

## Zielbild (erreicht)
- Runtime besitzt `IP_SALT` vollständig in `var/state`.
- Bei fehlendem/inkonsistentem Zustand wird Salt deterministisch neu erzeugt und IP-bezogener State im selben Ablauf bereinigt.
- Setup-/Workflow-Pfade verwenden keinen `--rotate-ip-salt`-Schalter mehr; stattdessen gibt es einen expliziten Reset-Befehl.

## Umsetzungsergebnis
- Architekturrahmen für `IP_SALT` umgesetzt:
  - `LockRunner` mit `symfony/lock` und Fail-Fast-Timeout.
  - `AtomicWriter` für atomare Runtime-Schreibvorgänge.
  - `StateStore` / `StateValidator` / `ResetExecutor`.
  - Trigger-/Policy-Modell über `TriggerReason`, `DecisionPolicy`, `ActionPlan`.
- Konsistenzmodell umgesetzt:
  - Ein-Datei-State unter `var/state/ip_salt.state.json`.
  - Marker-Status `IN_PROGRESS` und `READY` inklusive `generation` und `updated_at`.
  - Deterministische Recovery bei inkonsistentem Markerzustand unter Lock.
- Betriebswege umgesetzt:
  - Expliziter Reset-Befehl `php bin/cli ip-hash reset`.
  - `--rotate-ip-salt` aus Setup entfernt.
- Config-Pfad umgesetzt:
  - `IP_SALT` ist vollständig aus aktiven Runtime-Konfigurationen entfernt.

## Akzeptanzkriterien (alle erfüllt)
- [x] Runtime kann ohne externen `IP_SALT` stabil starten und denselben Salt wiederverwenden.
- [x] Bei inkonsistentem Zustand (z. B. Mismatch/abgebrochener Lauf) wird Salt rotiert und IP-bezogener State im gleichen Lauf bereinigt.
- [x] `--rotate-ip-salt` ist aus Setup-/Deploy-Pfaden entfernt.
- [x] `IP_SALT` ist kein regulärer Pflicht-/Override-Key in aktiven Betriebsconfigs mehr.
- [x] Dokumentation beschreibt Betriebsmodus und Reset-Befehl ohne Legacy-Pfad.
- [x] Testnachweise für Erfolgs- und Fehlerpfade liegen vor.

## Nicht im Scope (bewusst unverändert)
- Allgemeine Änderungen am Rate-Limit-Fachverhalten außerhalb des Salt-/State-Lebenszyklus.
- Flächige Runtime-Concurrency-Härtung über weitere `var/`-Bereiche.

## Übergabe an ISS-012
- Gemeinsamer Einstiegspunkt für die Ausrollung ist der bestehende Runtime-Lock-Rahmen.
- Nächster Fokus:
  - Rate-Limit (`var/tmp/ratelimit`)
  - CAPTCHA-Verify (`var/tmp/captcha`)
  - Token-Rotation (`var/state/tokens`)
- `AppContext::buildIpSaltService` dient als Referenz für Komposition und Instanziierung des Lock-Runners.

## Abhängigkeiten
- Story-Kontext:
  - [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- Voraussetzungen:
  - [ISS-010](ISS-010-preview-workflow-testmatrix-und-entscheidungen.md)
- Wirkt auf:
  - [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)
- Folge-Issue:
  - [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md)

## Ergänzende Doku
- [Anlage: ISS-011/ISS-012 Näherung und Commit-Folge](ISS-011-012-anlage-naeherung-und-commitfolge.md)
