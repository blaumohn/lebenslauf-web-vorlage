# ISSUE: Preview-Workflow Testmatrix und offene Entscheidungen

## Typ
- Issue (Delivery/Design)

## Status
- Aktiv

## Abgeleitet aus
- [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md) (P1-D Auslagerung)

## Problem
- In [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md) ist P1-D noch offen.
- Die notwendigen Entscheidungen fuer Testabdeckung, Workflow-Zuschnitt und Vorabpruefungen sind noch nicht als eigenes, abgeschlossenes Arbeitspaket gebuendelt.
- Ohne diese Klaerungen bleibt die Umsetzung von P1-D in ISS-005 unscharf und schwer nachweisbar.

## Ziel
- Vollstaendige Testmatrix fuer Build/Runtime/Deploy-Smoke festlegen.
- Offene Entscheidungen fuer den zukuenftigen Deploy-Workflow explizit treffen.
- Konkrete Restluecken mit Folgepfad dokumentieren.

## Scope
- P1-D-Teststrategie und Nachweisstruktur definieren:
  - Vertrags-Tests (`config lint`) je Pipeline-Phase.
  - Verhaltens-Tests (Build/Runtime/Deploy-Smoke) je relevanter Pipeline.
- Workflow-Entscheidungen fuer Preview und vorbereitend fuer Production ausarbeiten:
  - Parametrisierung, Branch->Environment-Mapping, Preflight-Schritte.
- Offene Punkte in einer zentralen Entscheidungstabelle pflegen.

## Nicht im Scope
- Vollstaendige Implementierung eines Production-Deployments.
- Grundlegende Refactor-Arbeit an Config-Modellen (siehe [ISS-003](ISS-003-phase-rules-typing-and-clarity.md)).

## Entscheidungen und Entwurfsstand (2026-02-10)
- Workflow-Design: Ein gemeinsamer Deploy-Workflow ohne `workflow_call` ist bevorzugt.
- Branch->Environment-Mapping: aktuell nur `preview` aktiv; `main -> prod` bleibt vorbereitet.
- Sicherheitsregel: Deploy-Job darf nur auf freigegebenen Branches laufen (kein generischer Fallback-Deploy auf beliebigen Branch).
- Pipeline-spezifisches Verhalten soll vorrangig in Config abgebildet werden.
- `--rotate-ip-salt` soll im selben Umsetzungsschritt entfernt werden, in dem die Runtime-Guardrails aktiv werden.
- `IP_SALT`-Richtung (Entwurf): Runtime-eigene Verwaltung in `var/state`, inklusive Fingerprint-Pruefung und konsistenter Bereinigung IP-bezogener Tabellen bei Rotation.
- Begriffsabgrenzung: CI/Workflow-Leak und Server-Dateizugriff sind getrennte Bedrohungsmodelle.

## `IP_SALT` Runtime-Verwaltung: Eigenschaften und Gruende
- Zielbild:
  - `IP_SALT` wird zur Laufzeit in `var/state` verwaltet (nicht als regulaerer Config-Wert in versionierten Dateien).
  - Runtime kann fehlenden Salt selbst erzeugen und stabil weiternutzen.
- Gruende:
  - Konsistenz: gleiche Hash-Basis ueber mehrere Requests/Deploys, solange `var/state` persistent bleibt.
  - Betriebsklarheit: Salt-Rotation und IP-Tabellen-Bereinigung sind ein zusammengehoeriger Vorgang.
  - CI-Hygiene: weniger Secret-/Parameter-Fluss nur fuer Salt-Rotation im Deploy-Workflow.
  - Fachbezug: `IP_SALT` ist primär Betriebszustand fuer Hash-Konsistenz, nicht fachlicher Eingabewert.
- Sicherheitsabgrenzung:
  - Runtime-Verwaltung reduziert nicht den Schaden bei kompromittiertem Server-Dateizugriff.
  - Sie reduziert aber Bedienfehler/Inkonsistenzen durch CI-seitige Rotationen ohne State-Bereinigung.
- Technische Leitplanken:
  - atomisches Schreiben (`tmp` + `rename`), Dateilock fuer Parallelzugriffe, restriktive Dateirechte.
  - Fingerprint/Marker zur Erkennung von Salt-Wechseln und automatischer Bereinigung der IP-bezogenen Tabellen.

## Ableitung fuer CLI und Config
- `--rotate-ip-salt`:
  - wird nicht uebergangsweise belassen, sondern mit Einfuehrung der Guardrails direkt entfernt.
  - Rotation erfolgt danach nur noch bewusst ueber einen expliziten Betriebsbefehl (z. B. `cli ip-hash reset`: rotiert Salt + leert IP-Tabellen).
- `IP_SALT` als Config-Key:
  - wird als externer Config-Wert in allen Pipelines abgebaut; Quelle ist die Runtime in `var/state`.
  - einmalige Migration alter externer Werte ist erlaubt, danach entfällt der Key aus regulären Betriebsconfigs.
- Workflow-Folge:
  - Salt-Rotation nicht mehr implizit in `setup` jedes Deploys.
  - Rotation nur bewusst ueber Betriebsbefehl bzw. klaren Wartungsschritt.

## Kurzfristige Entfernung von `--rotate-ip-salt` (Guardrails)
- `--rotate-ip-salt` wird kurzfristig entfernt, wenn alle Punkte gleichzeitig umgesetzt sind:
  - Runtime verwaltet `IP_SALT` robust in `var/state` (inkl. Locking + atomischem Schreiben).
  - Bei Salt-Wechsel wird IP-bezogener State automatisch und konsistent bereinigt.
  - Workflow und Skripte verwenden `--rotate-ip-salt` an keiner Stelle mehr.
- Ohne diese Guardrails darf keine Entfernung erfolgen.

## Offene Punkte und Entscheidungen
- Entscheidungsstatus: `entschieden`, `offen`, `vertagt`
- Umsetzungsstatus: `umgesetzt`, `in Arbeit`, `ausstehend`
| Bereich | Entscheidung / Entwurf | Entscheidungsstatus | Umsetzungsstatus | Restpunkt |
| --- | --- | --- | --- | --- |
| Workflow-Design | Ein gemeinsamer Deploy-Workflow ohne `workflow_call` ist gesetzt. | entschieden | in Arbeit | Job-Guards fuer erlaubte Branches im YAML finalisieren. |
| Branch-Mapping | Aktuell `preview -> preview`; `main -> prod` ist als spaeterer Ausbau vorgesehen. | entschieden | ausstehend | Produktions-Branchregel bei Prod-Einfuehrung verbindlich eintragen. |
| GitHub Environments | `preview` jetzt verbindlich; `production` wird vorbereitet, aber noch nicht aktiv genutzt. | vertagt | ausstehend | Aktivierung mit erstem Prod-Issue terminieren. |
| Parameterisierung | Workflow bleibt generisch; pipeline-spezifisches Verhalten vorrangig ueber Config statt Spezialschritte. | entschieden | in Arbeit | Bei Runtime-Verwaltung pruefen, welche Setup-Flags entfallen koennen. |
| Rolle von `bin/ci` | `bin/ci` ist kein eigener Testmaßstab; maßgeblich ist ausschließlich die P1-D-Testmatrix. Abweichende Doppeltests gelten als Rückstand. | offen | ausstehend | Die zwei unzutreffenden `bin/ci`-Tests entfernen oder auf Matrix-Nachweise umstellen. |
| Testschichten | Pflicht vor Deploy: `config lint`, `composer run test`, Build, Artefakt-Checks. | entschieden | in Arbeit | Reihenfolge + Failure-Messages im Workflow vereinheitlichen. |
| Pipeline-Abdeckung | Vertrags-Tests sollen pipelinespezifisch aus dem Manifest abgeleitet werden. | entschieden | in Arbeit | Umsetzung im Workflow/Script festziehen. |
| Verhaltens-Tests | Zuschnitt je Pipeline: `dev`-Smoke getrennt, `preview`-Deploy-Smoke verpflichtend. | entschieden | ausstehend | Produktiver Post-Deploy-Smoke fuer `prod` spaeter ergaenzen. |
| Deploy-Artefakt | Runtime-vollstaendige Inhalte muessen explizit geprueft werden. | offen | ausstehend | Endgueltige Artefaktliste und Checks festlegen. |
| Smoke-Ort | Pre-Deploy-Smoke bleibt; Post-Deploy-Smoke gegen Ziel-URL ist gewuenscht. | vertagt | ausstehend | Stabilen Post-Deploy-Checkpfad definieren. |
| FTP-Preflight | Vor Deploy separater Preflight (inkl. dry-run) ist vorgesehen. | entschieden | in Arbeit | Konkrete Action-Parameter final eintragen. |
| SMTP bei `MAIL_STDOUT=0` | MVP: Bei `MAIL_STDOUT=0` sind Verbindungscheck, Auth, SMTP-`250` und DNS-/Mail-Identitaetschecks (SPF, DKIM/DMARC, PTR/rDNS, HELO/EHLO) verpflichtend; ohne Konto-Beteiligung. | entschieden | ausstehend | Workflow-Checks verankern; konto-basierte Empfangspruefung als Erweiterung ueber [BLC-005](../backlog/items/BLC-005-smtp-empfangsnachweis-mit-konto-beteiligung.md) nachziehen. |
| Kosten/CI-Ressourcen | Service-Container (z. B. Mailpit) sind optional, nicht zwingend fuer P1-D. | vertagt | ausstehend | Aufwand/Kosten bei Bedarf gesondert bewerten. |
| `IP_SALT`-Strategie | `IP_SALT` ist runtime-intern (`var/state`): fehlt Salt oder passt der Fingerprint nicht, rotiert Runtime und bereinigt IP-State konsistent. | entschieden | umgesetzt | In [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md) umgesetzt (abgeschlossen am 2026-02-12). |
| Doku & Tracking | Restluecken werden in ISS-010 gepflegt; ISS-005 referenziert diese Matrix. | entschieden | in Arbeit | Bei Statuswechseln konsistent nachziehen. |
| DoD | P1-D ist erst abgeschlossen, wenn Entscheidungen umgesetzt/nachweisbar sind. | entschieden | ausstehend | Konkrete Nachweislinks pro Testlauf sammeln. |

## P1-D Testmatrix (Entwurf, Finalisierung nach ISS-012)
| Bereich | Ziel | Nachweis/Schritt | Status | Abhaengigkeit |
| --- | --- | --- | --- | --- |
| Vertrags-Tests | Config-Regeln je Pipeline-Phase valide | `php bin/cli config lint <pipeline>` (alle relevanten Pipelines/Phasen) | offen | - |
| Build-Tests | Preview-Build reproduzierbar mit Fixtures | `php bin/cli setup preview` + `php bin/cli build preview` | offen | - |
| Runtime-Tests | Fachlogik mit Preview-Runtime laeuft | `composer run test` plus pipeline-relevante Feature-Pruefungen | blockiert | [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md) |
| Deploy-Artefakt | Paket ist runtime-vollstaendig | Artefakt-Checks inkl. erwarteter Dateien/Config | offen | - |
| Pre-Deploy-Smoke | HTTP-Basischeck vor FTP-Deploy | Smoke-Schritt im Workflow vor FTP | offen | - |
| Post-Deploy-Smoke | Zielsystem antwortet korrekt | `curl` gegen Preview-URL (`/`, `/cv`, `/contact`) | vertagt | - |

## Akzeptanzkriterien
- Fuer alle Zeilen der Entscheidungstabelle sind `Entscheidungsstatus` und `Umsetzungsstatus` gepflegt (`entschieden`, `offen`, `vertagt` bzw. `umgesetzt`, `in Arbeit`, `ausstehend`).
- Es gibt eine verbindliche Testmatrix fuer Build/Runtime/Deploy-Smoke inkl. Nachweis-Kommandos.
- Blockierungen in der Testmatrix sind mit Folge-Issues markiert.
- ISS-005 verweist auf diese Issue als Voraussetzung fuer P1-D-Abschluss.
- Restluecken sind mit klaren Folge-Issues oder bewusster Verschiebung dokumentiert.

## Abhaengigkeiten
- Story-Kontext:
  - [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- Folge-Issue:
  - [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md) (`IP_SALT` Runtime-Verwaltung, erledigt am 2026-02-12)
- Wirkt auf:
  - [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)
