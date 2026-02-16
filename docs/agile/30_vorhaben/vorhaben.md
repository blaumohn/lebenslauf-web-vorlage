# Vorhaben

## V-001: Preview Readiness durch konsistente System-Source-Verarbeitung
Kurzsatz: Grundlagen für konsistente Source-Verarbeitung im Preview-Pfad herstellen.
Gilt für: App-Delivery.
Prüfzeichen: Maßnahmen aus dem Vorhaben sind umgesetzt oder als Entscheidung/Vorhaben nachgezogen.
Status: Backlog.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-002-preview-system-source-readiness.md`

## V-002: Risikomuster repo-weit feststellen und Befundliste erstellen
Kurzsatz: Technische Risiken systematisch erfassen und priorisieren.
Gilt für: App und Config-Lib.
Prüfzeichen: Befundliste mit Priorität und Folgeschritten liegt vor.
Status: Backlog.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-003-phase-rules-typing-and-clarity.md`

## V-003: Dev-Basis herstellen und Repo-Hygiene absichern
Kurzsatz: Branch- und Repo-Grundlagen stabilisieren.
Gilt für: Repo-Betrieb.
Prüfzeichen: Vereinbarte Hygieneregeln sind eingeführt.
Status: Erledigt (2026-02-04).
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-004-dev-branch-foundation-and-repo-hygiene.md`

## V-004: Preview-Workflow aus dev wieder aktivieren
Kurzsatz: Vorschau-Workflow aus `dev` zuverlässig lauffähig machen.
Gilt für: Preview-Pipeline.
Prüfzeichen: Workflow läuft auf Zielpfad stabil durch.
Status: Aktiv.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-005-preview-workflow-reenable-from-dev.md`

## V-005: CLI-UX Missing-Config und Pipeline-Phase-Syntax
Kurzsatz: Fehlende Config und CLI-Syntax für Pipeline-Phase nutzerklar gestalten.
Gilt für: CLI der App.
Prüfzeichen: CLI-Meldungen und Hilfe decken Fehlfälle verständlich ab.
Status: Backlog.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-006-cli-ux-config-missing-and-pipeline-phase.md`

## V-006: Konditionelle Config-Validierung
Kurzsatz: Validierung abhängig von Kontextbedingungen konsistent ausführen.
Gilt für: Config-Lib.
Prüfzeichen: Validierungslogik ist nachvollziehbar und testbar je Bedingung.
Status: Backlog.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-007-conditional-config-validation.md`

## V-007: i18n für CLI- und Runtime-Nachrichten
Kurzsatz: Laufzeit- und CLI-Texte mehrsprachig und konsistent bereitstellen.
Gilt für: App und Config-Lib.
Prüfzeichen: Zielsprachen decken definierte Nachrichtengruppen ab.
Status: Backlog.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-008-i18n-cli-runtime-messages-app-und-config-lib.md`

## V-008: JSON-Lokalschicht für Automationswerte
Kurzsatz: Lokale JSON-Schicht als Automationsspeicher bewerten.
Gilt für: Betriebsautomatisierung.
Prüfzeichen: Entscheidung getroffen: umgesetzt oder verworfen.
Status: Nicht geplant (Stand 2026-02-10).
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-009-json-local-automation-layer.md`

## V-009: Preview-Workflow Testmatrix und offene Entscheidungen
Kurzsatz: Testszenarien und offene Festlegungen für den Preview-Pfad abschließen.
Gilt für: Preview-Qualität.
Prüfzeichen: Testmatrix vollständig und referenzierte Entscheidungen dokumentiert.
Status: Aktiv.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-010-preview-workflow-testmatrix-und-entscheidungen.md`

## V-010: IP_SALT Runtime-Verwaltung und Guardrails
Kurzsatz: Laufzeitverwaltung und Schutzregeln für `IP_SALT` umsetzen.
Gilt für: Runtime-Sicherheit.
Prüfzeichen: Guardrails greifen in den definierten Fehlfällen.
Status: Erledigt (2026-02-12).
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md`

## V-011: Runtime-Concurrency, Locking und atomare Zugriffe
Kurzsatz: Gleichzeitige Zugriffe robust mit Locking und atomaren Operationen absichern.
Gilt für: Runtime-Dateizugriffe.
Prüfzeichen: Konkurrenzfälle sind reproduzierbar abgesichert und getestet.
Status: Aktiv (Restscope offen, Stand 2026-02-13).
Nächste Schritte:
- Runtime-Rahmen auf `RateLimiter`, `CaptchaService` und `TokenService` ausrollen.
- `RuntimeLockRunner` und atomare Write-Helfer in allen kritischen Schreibpfaden vereinheitlichen.
- Lock-Granularität verbindlich umsetzen: Rate-Limit pro Schlüssel, CAPTCHA pro `captcha_id`, Token pro Profil.
- Race-nahe Paralleltests für Rate-Limit, CAPTCHA und Token ergänzen.
- Integrationsnachweis für `V-004` (stabiler Preview-Pfad) nachziehen.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md`

## V-012: FTP/FTPS-Verwaltungs-Skripte für Preview-Betrieb
Kurzsatz: Betriebsnahe Verwaltungsfunktionen für Preview bereitstellen.
Gilt für: Ops/Preview.
Prüfzeichen: Skripte decken Zielaufgaben reproduzierbar ab.
Status: Offen.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-013-ftp-ftps-verwaltungs-skripte-fuer-preview-betrieb.md`

## V-013: App-interne Konstanten für Pfade und Runtime-Schlüssel
Kurzsatz: Technische Konstanten zentralisieren und konsistent verwenden.
Gilt für: App-Codebasis.
Prüfzeichen: Harte String-Duplikate sind ersetzt und zentral referenziert.
Status: Offen.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/ISS-014-app-interne-konstanten-fuer-pfade-und-runtime-schluessel.md`

## V-014: Qualitätsrahmen für App und Config-Lib
Kurzsatz: Qualitätsregeln übergreifend definieren und in Teilvorhaben herunterbrechen.
Gilt für: App und Config-Lib.
Prüfzeichen: Regeln sind als umsetzbare Vorhaben/Entscheidungen verlinkt.
Status: Aktiv.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/STY-001-qualitaetsrahmen-repo-app-und-config-lib.md`

## V-015: i18n für Seitenvorlagen und Template-Texte
Kurzsatz: Vorlagen und Template-Texte mehrsprachig konsistent machen.
Gilt für: Seitenvorlagen/Rendering.
Prüfzeichen: Alle Zieltemplates haben gepflegte Sprachvarianten.
Status: Backlog.
Legacy-Quelle: `90_archiv/legacy-stand-2026-02-13/issues/STY-002-i18n-seitenvorlagen-und-templates.md`
