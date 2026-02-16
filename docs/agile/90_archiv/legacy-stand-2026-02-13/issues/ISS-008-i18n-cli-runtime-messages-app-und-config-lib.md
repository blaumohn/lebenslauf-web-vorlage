# ISSUE: i18n fuer CLI- und Runtime-Nachrichten (App + Config-Lib)

## Typ
- Issue (Qualitaet/UX)

## Status
- Backlog

## Problem
- Fehlermeldungen und CLI-Ausgaben sind sprachlich fix und nicht i18n-faehig.
- App und Config-Lib liefern teils unterschiedliche Formate und Texte.
- In gemischten Teams/Umgebungen fehlt eine konsistente, uebersetzbare Schicht.

## Ziel
- i18n-faehige Ausgabe fuer CLI- und Runtime-Nachrichten etablieren.
- Einheitliche Message-IDs und konsistente Formate in App + Config-Lib.
- Klarer Pfad fuer spaetere Uebersetzungen (DE/EN als Minimum).

## Entscheidungen (offen)
- Format fuer Message-Kataloge (z. B. YAML/JSON/PHP-Array).
- Ort der Message-IDs (App vs. Config-Lib) und Versionierung.
- Fallback-Strategie (z. B. Default-Sprache Deutsch).

## Scope
- App:
  - CLI-Ausgaben (Setup/Config/Build/Run).
  - Runtime-Fehlertexte (z. B. Config-Validierung).
- Config-Lib:
  - Fehler- und Validierungsnachrichten als Message-IDs.
  - Mapping/Lookup fuer Ausgabe.
- Doku:
  - i18n-Konzept (Message-IDs, Kataloge, Fallback).

## Nicht im Scope
- Vollstaendige Uebersetzung aller UI-Labels.
- Internationalisierung von Content-Texten (Lebenslauf-Content).
- Seitenvorlagen/Template-Texte ausserhalb von CLI/Runtime.

## Akzeptanzkriterien
- Messages sind ueber IDs adressierbar und ueber Kataloge uebersetzbar.
- App + Config-Lib verwenden konsistente IDs.
- Fallback-Sprache ist dokumentiert und technisch abgesichert.

## Abhaengigkeiten
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- [BLC-002](../backlog/items/BLC-002-zentrales-fehlerkonzept.md)

## Notizen
- Bedarf ergibt sich aus mehreren Kontexten (CLI-UX, Config-Validierung, Tests).