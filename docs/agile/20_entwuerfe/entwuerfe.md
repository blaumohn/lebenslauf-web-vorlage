# Entwürfe

## E-001: Typensicherheit statt vieler Type-Guards
Kurzsatz: Laufzeitprüfungen sollen durch stärkere Typmodellierung reduziert werden.
Gilt für: App und Config-Lib.
Prüfzeichen: Entwurf ist entscheidungsreif, wenn konkrete Typmuster und Migrationsgrenzen benannt sind.
Varianten: A) schrittweise je Modul; B) gebündelt je Subsystem.
Folgen: A) geringeres Risiko pro PR; B) schnellere Vereinheitlichung, höheres Integrationsrisiko.
Offene Punkte: Priorität gegenüber laufenden Preview-Themen.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/backlog/items/BLC-001-typensicherheit-statt-guards.md`

## E-002: Zentrales Fehlerkonzept
Kurzsatz: Fehler sollen lokal geworfen und zentral konsistent übersetzt werden.
Gilt für: App und Config-Lib.
Prüfzeichen: Entwurf ist entscheidungsreif, wenn Fehlerklassen, Übersetzungspunkte und CLI-Ausgaben spezifiziert sind.
Varianten: A) gemeinsame Fehlerabstraktion; B) getrennte Domänenfehler mit Adapter.
Folgen: A) konsistente UX, höherer Abstimmungsaufwand; B) schnellere Einführung, mehr Mapping-Code.
Offene Punkte: Zuständigkeit für gemeinsame Fehlercodes.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/backlog/items/BLC-002-zentrales-fehlerkonzept.md`

## E-003: Bibliotheks-APIs konsequent nutzen
Kurzsatz: Wiederkehrende Hilfslogik soll durch bestehende Bibliotheken ersetzt werden.
Gilt für: Dateisystem-, Prozess- und Konfigurationspfade.
Prüfzeichen: Entwurf ist entscheidungsreif, wenn Ziel-APIs und Ausschlusskriterien je Modul benannt sind.
Varianten: A) nur neue Stellen umstellen; B) bestehende Stellen aktiv migrieren.
Folgen: A) niedrige Kosten, langsame Vereinheitlichung; B) hohe Anfangskosten, schnellere Konsolidierung.
Offene Punkte: Mindestabdeckung durch Tests vor großem Umbau.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/backlog/items/BLC-003-bibliotheks-apis-konsequent-ausschoepfen.md`

## E-004: Terminologie Pipeline-Phase vereinheitlichen
Kurzsatz: Der Begriff „Pipeline-Phase“ wird repo-weit einheitlich verwendet.
Gilt für: App, Config-Lib, Doku.
Prüfzeichen: Entwurf ist entscheidungsreif, wenn Zielbegriffe und Übergangsregeln dokumentiert sind.
Varianten: A) strikter Sofortwechsel; B) Übergangsphase mit Alias-Begriffen.
Folgen: A) klare Sprache sofort, höherer Migrationsdruck; B) geringeres Risiko, längere Inkonsistenz.
Offene Punkte: Laufzeit bestehender Alias-Strings.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/backlog/items/BLC-004-pipeline-phase-terminologie-repo-weit.md`

## E-005: SMTP-Empfangsnachweis mit Konto-Beteiligung
Kurzsatz: SMTP-Übergabe soll durch echten Kontoabruf ergänzt werden.
Gilt für: Betriebsnachweis je Umgebung.
Prüfzeichen: Entwurf ist entscheidungsreif, wenn Nachweisweg, Sicherheit und Laufzeitkosten geklärt sind.
Varianten: A) IMAP-Polling im Testlauf; B) externe Inbox-Prüfung über Monitoring.
Folgen: A) direkte Kontrolle im Repo, höherer Betriebsaufwand; B) entkoppelter Betrieb, zusätzliche Systeme.
Offene Punkte: Geheimnisverwaltung und Datenschutz im Testbetrieb.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/backlog/items/BLC-005-smtp-empfangsnachweis-mit-konto-beteiligung.md`
