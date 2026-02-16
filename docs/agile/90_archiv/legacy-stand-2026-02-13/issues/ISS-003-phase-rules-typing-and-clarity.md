# ISSUE: Risikomuster repo-weit feststellen und Befundliste erstellen

## Typ
- Issue (Analyse/Refactor-Vorbereitung)

## Status
- Backlog

## Problem
- Es fehlen klare, durchgaengige Standards fuer Code- und Architekturqualitaet.
- Unklare Benennungen, implizite Typen und inkonsistente API-Nutzung treten mehrfach auf.
- Dadurch sinken Lesbarkeit, Wartbarkeit und Entwicklungstempo.

## Ziel
- Repo-weite Befundliste fuer Risikomuster und Qualitaetsdefizite in App und Config-Lib erstellen.
- Befunde priorisieren (Impact, Risiko, Aufwand) und in umsetzbare Folge-Issues aufteilen.
- `PhaseConfig`-Thema explizit aufnehmen, aber als Teil eines groesseren Gesamtbilds.

## Scope
- Analysefokus:
  - App-Repo (`lebenslauf-web-vorlage`)
  - Config-Lib (`pipeline-config-spec-php`)
- Ergebnisartefakte:
  - Liste der Muster inkl. Fundstellen
  - Priorisierung in kurz/mittel/langfristig
  - Vorschlag fuer konkrete Folge-Issues
- Beispielpflicht:
  - `resolvePhaseConfig` / `PhaseConfig`-Unklarheit ist als Befund enthalten

## Nicht im Scope
- Vollstaendige Umsetzung aller gefundenen Befunde in diesem Issue.
- Preview-Workflow-Reaktivierung (siehe [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)).

## Akzeptanzkriterien
- Es gibt eine nachvollziehbare Befundliste mit Fundstellen in beiden Repos.
- Die Liste enthaelt die Punkte aus dem bisherigen `PhaseConfig`-Thema.
- Jeder Befund ist priorisiert und einem Folge-Issue zugeordnet oder als Backlog markiert.
- Die Liste ist so konkret, dass Umsetzungstickets direkt daraus erstellt/gestartet werden koennen.

## Abhaengigkeiten
- Story: [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- Voraussetzungen: [ISS-002](ISS-002-preview-system-source-readiness.md)
- Wirkt auf: [ISS-004](ISS-004-dev-branch-foundation-and-repo-hygiene.md) (Done: 2026-02-04)

## Workflow-Phase
- Aktuell: Todo
- Naechster Gate: Ready for Refactor Breakdown