# ISSUE: FTP/FTPS-Verwaltungs-Skripte fuer Preview-Betrieb

## Typ
- Issue (Ops/Delivery)

## Status
- Offen (direkt nach `feature/preview`)

## Abgeleitet aus
- Folge aus [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md)

## Problem
- Bei FTP/FTPS-only Deployments gibt es keinen direkten Remote-CLI-Zugriff.
- Betriebsaktionen (z. B. bewusster IP-Hash-Reset) sind ohne Zusatzpfad nur manuell und fehleranfaellig.

## Ziel
- Wiederholbare Verwaltungsablaeufe ueber FTP/FTPS bereitstellen.
- Operations-Pfade so gestalten, dass sie ohne SSH funktionieren.
- Ausfuehrung moeglichst an bestehende CLI-Semantik anlehnen.

## Scope
- FTP/FTPS-basierte Verwaltungs-Skripte fuer Preview.
- Marker-/Signaldatei-Ansatz fuer Runtime-Aktionen (z. B. geplanter IP-Hash-Reset).
- Dokumentation:
  - welcher Schritt lokal ausgefuehrt wird,
  - welcher Schritt via FTP/FTPS auf dem Zielsystem landet,
  - wie Erfolg/Nachweis geprueft wird.
- Tests:
  - lokale Simulation des Marker-Flows,
  - Deploy-Checkliste fuer Preview.

## Nicht im Scope
- Blockierung des aktuellen Preview-Gates in [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md).
- Produktions-Rollout mit separatem Betriebsmodell.

## Akzeptanzkriterien
- Fuer den definierten Verwaltungsfall existiert ein reproduzierbarer FTP/FTPS-Pfad.
- Ablauf ist dokumentiert und ohne manuelle Datei-Operationen im Zielsystem durchfuehrbar.
- Nachweise sind als eigenstaendiger Schritt nach `feature/preview` fuehrbar.

## Abhaengigkeiten
- Story-Kontext:
  - [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- Voraussetzung:
  - [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md) (Preview zuerst stabil)
