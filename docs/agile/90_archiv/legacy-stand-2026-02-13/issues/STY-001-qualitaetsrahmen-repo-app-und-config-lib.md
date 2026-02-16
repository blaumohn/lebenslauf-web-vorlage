# STORY: Qualitaetsrahmen fuer App und Config-Lib

## Typ
- Story (uebergeordnet)

## Status
- Aktiv

## Problem
- Im Repo treten wiederholt Risikomuster und Inkonsistenzen auf.
- `PhaseConfig` ist ein Beispiel, aber nicht das einzige.
- Ohne systematische Sicht entstehen weiter lokale Einzelfixes statt stabilem Standard.

## Ziel
- Qualitaetskriterien als explizite Anforderungen verankern.
- Risikomuster repo-weit in App und Config-Lib erfassen, priorisieren und schrittweise abbauen.
- Terminologie fuer zentrale Fachbegriffe repo-weit vereinheitlichen (z. B. `Pipeline-Phase` fuer `pipeline + phase`).

## Teil-Issues
- [ISS-003](ISS-003-phase-rules-typing-and-clarity.md): Befundliste von Risikomustern und Qualitaetsdefiziten fuer App + Config-Lib erstellen.
- [ISS-004](ISS-004-dev-branch-foundation-and-repo-hygiene.md): `dev`-Baseline und Repo-Hygiene stabilisieren (Done: 2026-02-04).
- [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md): Preview-Workflow nach Stabilisierung wieder aktivieren.
- [ISS-014](ISS-014-app-interne-konstanten-fuer-pfade-und-runtime-schluessel.md): App-interne technische Konstanten fuer Pfade und Runtime-Schluessel vereinheitlichen.

## Backlog-Folgearbeit
- [BLC-004](../backlog/items/BLC-004-pipeline-phase-terminologie-repo-weit.md): Terminologie `Pipeline-Phase` repo-weit vereinheitlichen.

## Abhaengigkeiten
- Voraussetzungen:
  - [ISS-002](ISS-002-preview-system-source-readiness.md)

## Workflow-Phase
- Aktuell: In Progress
- Naechster Gate: Ready for Story Breakdown
