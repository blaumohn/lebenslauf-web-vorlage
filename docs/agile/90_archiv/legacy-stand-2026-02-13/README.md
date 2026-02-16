# Agile Doku

- Issue-Plan: [issues/README.md](issues/README.md)
- Issues: [issues/](issues/)
- Backlog Candidates: [backlog/candidates.md](backlog/candidates.md)

## Konvention
- `ISS-xxx`: umsetzbare Issues im Delivery-Fluss.
- `STY-xxx`: uebergeordnete Story mit mehreren Teil-Issues.
- `BLC-xxx`: fruehe Backlog-Candidates (noch keine konkrete Umsetzung).

## Arbeitsregeln ab 2026-02-05
- Aenderungen in `docs/agile` nur auf Branch `dev`.
- Issue-bezogene Commits in `docs/agile` nutzen ein fixes Format: `docs(agile): ISS-<Nummer>`.
- Falls kein Issue direkt betroffen ist: `docs(agile): update`.

## Uebergabe an neue Codex-Sitzung
- Arbeitsort fuer `docs/agile`: Worktree `../lebenslauf-web-vorlage-agile` auf `dev`.
- Erst `docs/agile` aktualisieren, danach technische Umsetzung in Feature-Branches.
- Fuer technische Schritte den Plan in [issues/ISS-005-preview-workflow-reenable-from-dev.md](issues/ISS-005-preview-workflow-reenable-from-dev.md) als Single Source of Truth nutzen.
