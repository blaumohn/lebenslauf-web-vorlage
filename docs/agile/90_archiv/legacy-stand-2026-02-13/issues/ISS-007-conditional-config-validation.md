# ISSUE: Konditionelle Config-Validierung

## Typ
- Issue (Config-Lib)

## Status
- Backlog

## Problem
- Required-Keys haengen in der Praxis von anderen Keys ab (z. B. `MAIL_STDOUT`).
- Aktuell erzwingt die Config-Validierung Pflichtwerte ohne Bedingungen.
- Das fuehrt zu unnoetigen Fehlermeldungen in `dev`/`preview`, obwohl der Runtime-Pfad die Werte nicht nutzt.

## Ziel
- Konditionelle Required-Keys in der Config-Lib definieren koennen.
- Validierung bleibt deterministisch und dokumentierbar.

## Entscheidungen (offen)
- Syntax fuer Bedingungen (z. B. `when`, `if`, `depends_on`).
- Ausdrucksraum fuer Bedingungen (nur Gleichheit? Bool? mehrere Keys?).

## Scope
- Config-Lib:
  - Manifest-Schema um Bedingungen erweitern.
  - Validierungslogik fuer Conditional-Required.
  - Fehlerausgaben klar und konsistent halten.
- Doku:
  - Bedingungen im Manifest dokumentieren.
  - Beispiel mit `MAIL_STDOUT`.

## Nicht im Scope
- Ausbau der gesamten Config-Policy auf beliebige Regeln.
- Automatisches Ueberschreiben von Werten.

## Akzeptanzkriterien
- `SMTP_FROM_NAME` ist required nur wenn `MAIL_STDOUT=0`.
- Lint/Missing-Checks erkennen fehlende Werte unter Bedingungen korrekt.
- Ohne Bedingungen bleibt das bisherige Verhalten unveraendert.

## Abhaengigkeiten
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)

## Notizen
- Aktueller Bedarf kommt aus `dev`/`preview` Runtime-Validierung.
- Internes Beispiel: `SMTP_FROM_NAME` ist nur required, wenn `MAIL_STDOUT=0`.