# ISSUE: Dev-Basis herstellen und Repo-Hygiene absichern

## Typ
- Issue (Delivery)

## Status
- Erledigt

## Problem
- Lokale Composer-`path`-Verknuepfung ist fuer den lokalen Dev-Flow ok, gefaehrdet aber reproduzierbare CI, wenn sie versioniert wird.
- Branch-Zielbild (`dev` als Stabilisierungsebene) ist noch nicht formalisiert.
- Ohne klare Baseline wird der naechste Preview-Anlauf unnoetig instabil.

## Ziel
- `dev` als Integrationsbranch fuer grundlegende Refactors etablieren.
- Repo-Zustand auf CI-faehige, remote reproduzierbare Dependencies bringen.
- Klare Gates definieren, bevor Preview-Workflow wieder aktiviert wird.

## Scope
- Git/Flow:
  - `refactor/no-dotenv-config` zuerst nach `dev` ueberfuehren (bei Bedarf per Rebase auf `main` vorbereiten)
  - PR `refactor/no-dotenv-config` nach `dev`
  - Folge-Refactors zuerst auf `dev`
- Composer-Hygiene:
  - lokale `path`-Quelle fuer `pipeline-config-spec/lib` nur lokal, nicht versioniert im Delivery-Stand
  - Lockfile konsistent fuer CI
- Dokumentation:
  - Branch-Regeln und Merge-Gates in Agile-Doku dokumentieren

## Nicht im Scope
- Reaktivierung des Preview-Workflows selbst (siehe [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)).

## Akzeptanzkriterien
- `dev` existiert als offizieller Integrationsbranch fuer Grundlagenarbeit.
- App-Repo ist im Delivery-Stand ohne lokale Path-Abhaengigkeit baubar.
- Merge-Gates fuer `dev` sind dokumentiert (mindestens: Config-Lint, Build, Tests).
- Folge-Preview-Issue kann ohne offene Basisthemen starten.

## Abhaengigkeiten
- Story:
  - [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- Voraussetzungen:
  - [ISS-002](ISS-002-preview-system-source-readiness.md)
  - [ISS-003](ISS-003-phase-rules-typing-and-clarity.md)
- Wirkt auf:
  - [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)

## Workflow-Phase
- Aktuell: Done (2026-02-04)
- Naechster Gate: Handover an [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)

## Abschlussnotiz
- PR `refactor/no-dotenv-config-app` -> `dev` ist gemergt.
- Entscheidung: Kein Rebase nur fuer die Umstellung von `docs/agile`.
- `docs/agile` wird ab jetzt in `dev` gepflegt.
- Schutz: `.local/githooks/pre-commit` blockiert das Committen einer lokalen Composer-`path`-Quelle fuer `pipeline-config-spec/lib`.