# ISSUE: CLI-UX: Missing-Config + Pipeline-Phase-Syntax

## Typ
- Issue (UX/Delivery)

## Status
- Backlog

## Problem
- Aktions-spezifische Hilfe ist unvollstaendig (z. B. `cli config lint --help` zeigt nur die Oberflaeche).
- Pipeline-Phase-Parameterisierung ist nicht konsistent, historisch gab es Varianten (Commit `d4c5355` als Kontext).
- Fehlende Konfigurationswerte sind fuer Nutzer nicht direkt sichtbar; es fehlt eine gezielte CLI-Ausgabe.

## Ziel
- Einheitliche Pipeline-Phase-Syntax (primär + optionaler Alias).
- Aktions-spezifische Hilfe fuer Unterbefehle.
- Eine klare, dokumentierte Syntax fuer `cli config missing` (Name, Argumente, Ausgabeformat).

## Entscheidungen (offen)
### Teil A: Pipeline-Phase + Hilfe
- Pipeline-Phase: `cli <pipeline> --phase <phase>` als Standard oder `--pipeline-phase <pipeline>,<phase>` (Alias?).
### Teil B: Config Missing
- Befehlssyntax: `config missing`, `missing-config` oder `config-missing`.
- Ausgabeformat: Text + optional YAML-Snippet fuer `.local/<pipeline>-<phase>.yaml`.

## Scope
### Teil A: Pipeline-Phase + Hilfe
- CLI:
  - Aktions-spezifische Hilfe fuer `config <action>` (z. B. `config lint --help`).
  - Pipeline-Phase-Syntax konsistent machen (primär + optionaler Alias).
- Doku:
  - Pipeline-Phase-Syntax dokumentieren.

### Teil B: Config Missing
- CLI:
  - `cli config missing <pipeline> --phase <phase>` (Name finalisieren).
  - Ausgabe: fehlende Variablen + erlaubte Quellen (`sources`).
  - Optional: `--format yaml` fuer ein kopierbares `.local/`-Snippet.
- Doku:
  - `sources` im Manifest kurz erklaeren (hier oder mit Verweis).

## Nicht im Scope
- Konfigurationswerte selbst aendern.
- Erweiterungen an Build/Deploy-Workflows.

## Akzeptanzkriterien
### Teil A: Pipeline-Phase + Hilfe
- `cli config lint --help` zeigt aktions-spezifische Usage.
- Eine primäre Pipeline-Phase-Syntax ist dokumentiert; ggf. Alias beschrieben.

### Teil B: Config Missing
- `cli config missing` liefert fehlende Variablen pro Pipeline-Phase inkl. `sources`.

## Abhaengigkeiten
### Teil A: Pipeline-Phase + Hilfe
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- [BLC-004](../backlog/items/BLC-004-pipeline-phase-terminologie-repo-weit.md)

### Teil B: Config Missing
- (keine)