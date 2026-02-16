# Agile-Index

## Zweck
Diese Struktur ist die verbindliche Arbeitsgrundlage für `docs/agile`.
Sie trennt strikt zwischen Anforderungen, Entwürfen, Vorhaben und Entscheidungen.

## Feste Ordnung
- `10_anforderungen/`: nur Anforderungen (`A-...`)
- `20_entwuerfe/`: nur Entwürfe (`E-...`)
- `30_vorhaben/`: nur Vorhaben (`V-...`)
- `40_entscheidungen/`: nur Entscheidungen (`D-...`)
- `50_protokolle/`: kurze Protokolle
- `90_archiv/`: Altbestand und unsichere Zulieferung

## Verbindliche Regeln
- Pro Datei genau eine Dokument-Sorte.
- Jede Einheit hat eine stabile Kennung und wird nie umnummeriert.
- Doppelte Inhalte sind unzulässig; außerhalb der Quellstelle nur Verweise.
- Verhaltensändernde technische Arbeit verweist auf mindestens eine Kennung `A-...` oder `D-...` (PR-Text oder Commit-Text).
- Änderungen an Verhalten und zugehörige Agile-Doku werden gemeinsam ausgeliefert.

## Startpunkte
- Anforderungen: [10_anforderungen/anforderungen.md](10_anforderungen/anforderungen.md)
- Entwürfe: [20_entwuerfe/entwuerfe.md](20_entwuerfe/entwuerfe.md)
- Vorhaben: [30_vorhaben/vorhaben.md](30_vorhaben/vorhaben.md)
- Entscheidungen: [40_entscheidungen/entscheidungen.md](40_entscheidungen/entscheidungen.md)
- Protokolle: [50_protokolle/protokolle.md](50_protokolle/protokolle.md)
- Legacy-Archiv: [90_archiv/legacy-stand-2026-02-13/](90_archiv/legacy-stand-2026-02-13/)
