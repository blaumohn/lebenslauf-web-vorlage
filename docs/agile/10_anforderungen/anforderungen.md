# Anforderungen

## A-001: Verbindliche Dokument-Sorten
Kurzsatz: In `docs/agile` sind nur vier Kernsorten zulässig: Anforderungen, Entwürfe, Vorhaben, Entscheidungen.
Gilt für: Gesamtes Repo, inklusive App und Config-Lib-Bezug.
Prüfzeichen: Jede neue Datei ist eindeutig einer Sorte zugeordnet.

## A-002: Feste Ordnerstruktur
Kurzsatz: Die Verzeichnisstruktur aus `00_index` ist verbindlich und darf nur per Entscheidung `D-...` geändert werden.
Gilt für: `docs/agile` im `dev`-Worktree.
Prüfzeichen: Keine fachlichen Dateien außerhalb `10/20/30/40/50/90`.

## A-003: Stabile Kennungen
Kurzsatz: Einheiten nutzen unveränderliche Kennungen `A-...`, `E-...`, `V-...`, `D-...`.
Gilt für: Alle neuen und migrierten Einträge.
Prüfzeichen: Verweise bleiben über Zeit stabil und zeigen auf genau eine Einheit.

## A-004: Pflicht-Kopf je Einheit
Kurzsatz: Jede Einheit enthält mindestens Kurzsatz, Geltungsbereich und Prüfzeichen.
Gilt für: Anforderungen, Entwürfe, Vorhaben, Entscheidungen.
Prüfzeichen: Strukturprüfung je Eintrag ergibt alle Pflichtfelder.

## A-005: Schreibregeln ohne Dopplung
Kurzsatz: Inhalte werden nicht kopiert; außerhalb des Quellorts stehen nur Verweise.
Gilt für: Alle Agile-Dokumente.
Prüfzeichen: Gleichlautende Regeltexte existieren nicht mehrfach in verschiedenen Dateien.

## A-006: Entwurfsqualität
Kurzsatz: Entwürfe dokumentieren Varianten (mindestens A/B), Folgen und offene Punkte.
Gilt für: Alle Einträge `E-...`.
Prüfzeichen: Jeder Entwurf hat die Felder Varianten, Folgen, offene Punkte.

## A-007: Entscheidungsqualität
Kurzsatz: Entscheidungen sind endgültige Festlegungen mit Datum, Grund, Gültigkeit ab und Verweisen auf A/E.
Gilt für: Alle Einträge `D-...`.
Prüfzeichen: Jeder Entscheidungseintrag enthält Datum, Grund, gilt ab, Verweise.

## A-008: Git- und Worktree-Anbindung
Kurzsatz: `docs/agile` wird ausschließlich im `dev`-Worktree gepflegt; verhaltensändernde Arbeit referenziert `A-...` oder `D-...`.
Gilt für: PR- und Commit-Ablauf der App und Config-Lib.
Prüfzeichen: PR-/Commit-Text enthält Referenz; Doku-Änderung ist Teil derselben Lieferung.
