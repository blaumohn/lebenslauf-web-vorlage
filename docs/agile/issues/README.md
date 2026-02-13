# Issue-Plan (Stand 2026-02-13)

## Ueberblick

## Aktueller Fokus

- [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md): Preview-Workflow aus `dev` wieder aktivieren
- [ISS-010](ISS-010-preview-workflow-testmatrix-und-entscheidungen.md): P1-D Testmatrix und offene Entscheidungen fuer Preview-Workflow
- [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md): Runtime-Concurrency und atomare Dateizugriffe haerten
- [ISS-014](ISS-014-app-interne-konstanten-fuer-pfade-und-runtime-schluessel.md): App-interne technische Konstanten fuer Pfade/Runtime-Schluessel vereinheitlichen

- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md): Qualitaetsrahmen fuer App und Config-Lib
- [ISS-002](ISS-002-preview-system-source-readiness.md): System-Source-Readiness (Basis gelegt, Split aktiv)
- [ISS-003](ISS-003-phase-rules-typing-and-clarity.md): Risikomuster repo-weit feststellen und Befundliste erstellen
- [ISS-004](ISS-004-dev-branch-foundation-and-repo-hygiene.md): `dev`-Baseline und Repo-Hygiene (erledigt am 2026-02-04)
- [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md): Preview-Workflow aus `dev` wieder aktivieren (in Umsetzung seit 2026-02-05)
- [ISS-006](ISS-006-cli-ux-config-missing-and-pipeline-phase.md): CLI-UX: Missing-Config + Pipeline-Phase-Syntax (neu)
- [ISS-007](ISS-007-conditional-config-validation.md): Konditionelle Config-Validierung (neu)
- [ISS-008](ISS-008-i18n-cli-runtime-messages-app-und-config-lib.md): i18n fuer CLI- und Runtime-Nachrichten (neu)
- [ISS-010](ISS-010-preview-workflow-testmatrix-und-entscheidungen.md): P1-D Testmatrix und offene Entscheidungen (neu)
- [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md): `IP_SALT` runtime-intern verwalten und Guardrails (erledigt am 2026-02-12)
- [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md): Runtime-Concurrency und atomare Dateizugriffe (aktiv; Lock-Entscheidungen festgelegt, Ausrollung offen)
- [ISS-014](ISS-014-app-interne-konstanten-fuer-pfade-und-runtime-schluessel.md): App-interne technische Konstanten fuer Pfade/Runtime-Schluessel (neu)
- [ISS-013](ISS-013-ftp-ftps-verwaltungs-skripte-fuer-preview-betrieb.md): FTP/FTPS-Verwaltungs-Skripte nach `feature/preview` (neu)
- [STY-002](STY-002-i18n-seitenvorlagen-und-templates.md): i18n fuer Seitenvorlagen und Template-Texte (neu)

## Derzeit nicht im App-Planning

- [ISS-009](ISS-009-json-local-automation-layer.md): JSON-Lokalschicht aktuell nicht geplant (Stand 2026-02-10)

## Abhaengigkeiten

- [ISS-002](ISS-002-preview-system-source-readiness.md) -> [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md) -> [ISS-003](ISS-003-phase-rules-typing-and-clarity.md)
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md) -> [ISS-004](ISS-004-dev-branch-foundation-and-repo-hygiene.md)
- [ISS-002](ISS-002-preview-system-source-readiness.md) -> [ISS-003](ISS-003-phase-rules-typing-and-clarity.md)
- [ISS-002](ISS-002-preview-system-source-readiness.md) -> [ISS-004](ISS-004-dev-branch-foundation-and-repo-hygiene.md)
- [ISS-002](ISS-002-preview-system-source-readiness.md) -> [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)
- [ISS-003](ISS-003-phase-rules-typing-and-clarity.md) -> [ISS-004](ISS-004-dev-branch-foundation-and-repo-hygiene.md)
- [ISS-004](ISS-004-dev-branch-foundation-and-repo-hygiene.md) -> [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)
- [ISS-010](ISS-010-preview-workflow-testmatrix-und-entscheidungen.md) -> [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)
- [ISS-010](ISS-010-preview-workflow-testmatrix-und-entscheidungen.md) -> [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md)
- [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md) -> [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)
- [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md) -> [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md)
- [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md) -> [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md) -> [ISS-014](ISS-014-app-interne-konstanten-fuer-pfade-und-runtime-schluessel.md)
- [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md) -> [ISS-014](ISS-014-app-interne-konstanten-fuer-pfade-und-runtime-schluessel.md)
- [ISS-014](ISS-014-app-interne-konstanten-fuer-pfade-und-runtime-schluessel.md) -> [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md)
- [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md) -> [ISS-013](ISS-013-ftp-ftps-verwaltungs-skripte-fuer-preview-betrieb.md)
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md) -> [ISS-006](ISS-006-cli-ux-config-missing-and-pipeline-phase.md)
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md) -> [ISS-007](ISS-007-conditional-config-validation.md)
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md) -> [ISS-008](ISS-008-i18n-cli-runtime-messages-app-und-config-lib.md)
- [BLC-002](../backlog/items/BLC-002-zentrales-fehlerkonzept.md) -> [ISS-008](ISS-008-i18n-cli-runtime-messages-app-und-config-lib.md)
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md) -> [STY-002](STY-002-i18n-seitenvorlagen-und-templates.md)
- [ISS-008](ISS-008-i18n-cli-runtime-messages-app-und-config-lib.md) -> [STY-002](STY-002-i18n-seitenvorlagen-und-templates.md)

## Geplanter Branch-Flow

- Danach `feature/preview` -> PR nach `dev`
- Danach PR `dev` -> `preview`
- Danach Refactors auf `dev` (zuerst Grundlagen/Lesbarkeit/Stabilitaet, inkl. Testluecken-Check und Ablaufpfad-Review).

## Repo-Betrieb (Erledigt)

- Entscheidung am 2026-02-04: Kein Rebase nur fuer die Umstellung von `docs/agile`.
- `docs/agile` wird ab jetzt in `dev` gepflegt.
- Die bestehende Historie im Branch `refactor/no-dotenv-config-app` bleibt unveraendert.

## Pflege-Regel

- Wenn ein Issue auf `Done` wechselt: alle Verlinkungen im Ordner `docs/agile/issues` suchen und Statushinweise aktualisieren.
- Empfohlener Check: `rg -n "ISS-[0-9]{3}" docs/agile/issues`.
