# STORY: i18n fuer Seitenvorlagen und Template-Texte

## Typ
- Story (Qualitaet/UX)

## Status
- Backlog

## Problem
- UI-Texte in Seitenvorlagen/Layouts sind hart kodiert.
- Lebenslauf-Content ist i18n-faehig, aber die umgebenden UI-Texte nicht.
- Es fehlt ein konsistenter Ort fuer uebersetzbare Template-Texte.

## Ziel
- Seitenvorlagen und Template-Texte werden i18n-faehig.
- Ein konsistenter Mechanismus fuer Template-Text-Keys liegt vor.
- DE/EN als Mindestumfang ist dokumentiert.

## Entscheidungen (offen)
- Format der Template-Text-Kataloge (JSON/YAML/PHP).
- Ablageort und Naming-Konventionen fuer Template-Keys.
- Fallback-Strategie fuer fehlende Uebersetzungen.

## Scope
- Template-Texte in Seitenlayout und generischen UI-Elementen.
- Zugriff aus Templates auf i18n-Keys.
- Doku fuer Pflege und Erweiterung der Texte.

## Nicht im Scope
- CLI/Runtime-Nachrichten (separate Issue).
- Fachlicher Lebenslauf-Content.

## Akzeptanzkriterien
- Templates nutzen i18n-Keys statt hart codierter Strings.
- Mindestens DE/EN ist konsistent verfuegbar.
- Fallback ist dokumentiert und technisch abgesichert.

## Abhaengigkeiten
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- [ISS-008](ISS-008-i18n-cli-runtime-messages-app-und-config-lib.md)

## Notizen
- UI-Texte koennen an bestehenden Label-Dateien andocken, sofern sinnvoll.