# Entscheidungen

## D-001: Strukturstandard für docs/agile verbindlich gemacht
Kurzsatz: `docs/agile` nutzt dauerhaft die vier Kernsorten mit fester Ordnerstruktur.
Gilt für: Alle künftigen Änderungen in `docs/agile`.
Prüfzeichen: Neue Einträge folgen dem Schema aus `00_index.md`.
Datum: 2026-02-13.
Grund: Vorherige Struktur mischte Plan, Analyse und Entscheidung und erschwerte verlässliche Verweise.
Gilt ab: Sofort.
Verweise: A-001, A-002, A-003, A-004.

## D-002: Legacy-Bestand als unveränderte Quelle archiviert
Kurzsatz: Frühere `issues/backlog`-Dokumente bleiben unverändert im Archiv und werden nicht mehr fortgeführt.
Gilt für: Bestand bis Stand 2026-02-13.
Prüfzeichen: Aktive Pflege erfolgt nur noch in `10/20/30/40/50`.
Datum: 2026-02-13.
Grund: Migration ohne Informationsverlust bei gleichzeitiger Trennung der Dokument-Sorten.
Gilt ab: Sofort.
Verweise: A-005, A-008.

## D-003: Verweisregel für technische Änderungen
Kurzsatz: Jede verhaltensändernde technische Änderung verweist auf mindestens `A-...` oder `D-...`.
Gilt für: PR- und Commit-Texte in App und Config-Lib.
Prüfzeichen: Referenz ist im PR-Text oder Commit-Text vorhanden.
Datum: 2026-02-13.
Grund: Anforderungen und Entscheidungen sollen Teil der Definition of Done sein.
Gilt ab: Sofort.
Verweise: A-008.
