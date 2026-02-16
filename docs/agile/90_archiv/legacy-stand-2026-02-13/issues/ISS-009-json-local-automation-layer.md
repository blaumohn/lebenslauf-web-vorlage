# ISSUE: JSON-Lokaldatei fuer Automationswerte

## Typ
- Issue (Design/Tech)

## Status
- Aus App-Planning entfernt (nicht geplant, Stand 2026-02-10)

## Problem
- YAML ist fuer Menschen lesbar, aber fuer automatisierte Inplace-Aenderungen fragil.
- Regex-Updates sind fehleranfaellig (Einrueckung, quoted keys, YAML-Varianten) und damit ein echter Bug-Risiko-Pfad.
- Parser-Updates zerstoeren Format/Kommentare.

## Planungsentscheid (2026-02-10)
- Der bisherige Haupttreiber (`IP_SALT`-Automation) ist mit [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md) fachlich anders geloest:
  - `IP_SALT` ist runtime-intern und bewusst ausserhalb regulaerer Config geplant.
- Damit fehlt aktuell ein priorisierter Anwendungsfall fuer die lokale JSON-Automationsschicht.
- Die Idee bleibt dokumentiert, ist aber derzeit nicht Teil des aktiven App-Plans.

## Ziel
- Automationswerte lokal stabil schreiben/rotieren (z. B. `IP_SALT`).
- YAML-Dateien unveraendert lassen.
- Konflikte zwischen manuellen YAML-Werten und Automation verhindern.

## Vorschlag
- Zusaetzliche lokale JSON-Datei pro Pipeline-Phase:
  - `.local/config/<pipeline>-<phase>.json`
- Lokalebene konsolidieren:
  - YAML lokal ebenfalls unter `.local/config/<pipeline>-<phase>.yaml` fuehren.
  - Alte Pfade `.local/<pipeline>-<phase>.yaml` in Doku und Loadern ersetzen.
- JSON ist nur fuer Automatisierung gedacht (nicht fuer manuelle Werte).
- Regel: Keine Schnittmenge zwischen lokaler JSON und lokaler YAML fuer dieselbe Pipeline-Phase.
- `sources` im Manifest optional um `json` erweitern (z. B. fuer `IP_SALT`).
- Validierung meldet Konflikte und blockiert Builds/Deploys.
- Prioritaet: JSON gleichrangig zu `.local`-YAML (gleiche Ebene).

## Scope
- Config-Lib:
  - JSON-Loader implementieren.
  - Lokale Pfadbasis auf `.local/config/` konsolidieren (YAML + JSON).
  - Merge/Precedence dokumentieren.
  - Konfliktpruefung fuer lokale Ebene.
- Manifest:
  - `sources` um `json` erweitern (optional pro Key).
- Doku:
  - Regeln und Beispiele in `docs/ENVIRONMENTS.md`.

## Nicht im Scope
- Umbau bestehender YAML-Dateien.
- Globales JSON-Config-Format ausserhalb der lokalen Ebene.

## Akzeptanzkriterien
- JSON-Lokaldateien werden geladen und gemerged.
- Konfliktregel (JSON vs YAML lokal, gleiche Pipeline-Phase) wird validiert.
- `IP_SALT` kann ueber JSON automatisiert gesetzt/rotiert werden.
- Dokumentation beschreibt Zweck, Regeln und Beispiele.
- Doku zeigt `.local/config/` als lokale Root.
