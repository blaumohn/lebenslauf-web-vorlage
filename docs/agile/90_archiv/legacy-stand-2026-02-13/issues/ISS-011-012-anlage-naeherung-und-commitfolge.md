# ANLAGE: ISS-011/ISS-012 Näherung und Commit-Folge

## Typ
- Anlage (Delivery/Tech)

## Bezug
- [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md)
- [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md)
- [ISS-012 Anlage: Verwalter-Programmfluss und Betriebsvergleich](ISS-012-anlage-verwalter-programmfluss-und-betriebsvergleich.md)

## Ziel
- Eine gemeinsame, umsetzbare Näherung für `ISS-011` und `ISS-012` festlegen.
- Eine realistische Commit-Folge vorgeben, die auch mit unvollständiger Detailplanung sofort startbar ist.

## Gemeinsame Näherung (7 Schritte)
1. Scope fixieren:
   - für `ISS-011` exakt festlegen, welche IP-bezogenen Zustände bei Salt-Wechsel bereinigt werden.
2. Schnittstellen festlegen:
   - eine Runtime-Komponente als einzige Quelle für Salt laden/erzeugen/rotieren.
3. Guardrails definieren:
   - Locking über `symfony/lock`, atomisches Schreiben (`tmp` + `rename`), Dateirechte, Fingerprint-Prüfung.
4. Migrationspfad festziehen:
   - externe `IP_SALT`-Abhängigkeit abbauen, Altwerte nur als Übergang.
5. CLI-Pfad planen:
   - `--rotate-ip-salt` entfernen, expliziten Reset-Befehl (`cli ip-hash reset`) verwenden.
6. Testplan vorab festlegen:
   - Parallelzugriff, Fingerprint-Mismatch, fehlender Salt, Reset-Befehl, Bereinigungsnachweis.
7. DoD messen:
   - erst `done`, wenn Guardrails, Rückbau und Tests gemeinsam nachweisbar sind.

## Verbindliche Architekturbausteine
- Gemeinsame Bausteine als Komposition:
  - `LockRunner` (mit `symfony/lock`)
  - `AtomicWriter`
  - `StateStore` / `StateValidator`
  - `ResetExecutor`
- Nicht als Zielbild:
  - keine große abstrakte Basisklasse fuer alle Verwalter.
  - keine fachbereichsuebergreifende Einheits-Reset-Logik.

## Trigger- und Entscheidungsmodell (verbindlich)
- Ja abstrahieren:
  - gemeinsames Trigger-Modell mit z. B. `MISSING`, `INVALID`, `MISMATCH`, `EXPLICIT_RESET`, `EXPIRED`.
- Nicht abstrahieren:
  - die konkrete Reaktion je Verwalter.
- Pragmatisches Muster:
  1. `TriggerReason` (Enum/Value Object) zentral.
  2. Je Verwalter eine `DecisionPolicy` (`Zustand -> TriggerReason`).
  3. Je Verwalter ein `ActionPlan` (konkrete Reaktion ausfuehren).

## Lock-Policy und Betriebsannahmen
- Lock-Policy:
  - kurze kritische Abschnitte.
  - deterministisches Fehlerverhalten bei Lock-Problemen (Fail-Fast statt endlos warten).
  - Lock-Key-Granularitaet je Bereich dokumentieren (global, pro key, pro profile).
- Betriebsannahmen:
  - bei Single-Host ist `FlockStore` ausreichend.
  - bei Multi-Host ist ein verteilter Lock-Store erforderlich.
  - diese Annahme ist vor `ISS-012`-Abschluss explizit zu bestaetigen.

## Verteilung auf ISS-011 und ISS-012
| Schritt | Schwerpunkt ISS-011 | Schwerpunkt ISS-012 |
| --- | --- | --- |
| 1 | Pflicht | nur Referenz |
| 2 | Pflicht (IP_SALT) | Pflicht (Rate-Limit/CAPTCHA/Token ausrollen) |
| 3 | Pflicht (minimaler Rahmen) | Pflicht (flächige Vereinheitlichung) |
| 4 | Pflicht | nur Folgeanpassung |
| 5 | Pflicht | keine |
| 6 | Pflicht (IP_SALT-Pfade) | Pflicht (Race-nahe Tests je Verwalter) |
| 7 | Pflicht für ISS-011 | Pflicht für ISS-012 |
| Architekturbausteine | Pflicht (minimal fuer IP_SALT) | Pflicht (flaechig fuer alle Verwalter) |
| Trigger-/Policy-Modell | Pflicht (minimal fuer IP_SALT) | Pflicht (konsistent ueber alle Verwalter) |
| Lock-Policy/Betriebsannahmen | Pflicht | Pflicht |

## Vergleichbarkeit der Arbeitsfolge
- Vergleichbar ist das Muster, nicht die konkrete Mutationslogik jeder Ressource.
- Gemeinsames Muster: Lock -> State lesen/validieren -> Trigger/Entscheidung -> notwendige Mutation -> Lock freigeben.
- Nicht 1:1 uebertragbar: `IN_PROGRESS`/`READY` ist fuer `IP_SALT` verbindlich, fuer andere Verwalter nur falls fachlich noetig.
- Die Tabelle beschreibt die Abstraktionsebene (Rahmen + Verantwortung), nicht identische Code-Schritte pro Verwalter.

## Commit-Folge (historisch + aktueller Stand)
Hinweis:
- Die Folge bleibt als Referenz fuer die Reviewbarkeit erhalten.
- Fuer die aktuelle Umsetzung ist nur der aktive Block relevant.

### Startsequenz (historisch, ISS-011 abgeschlossen)
1. [x] `docs(agile): dokumentiere ISS-011/012-Näherung und Commit-Folge (iss-011)`
2. [x] `refactor(runtime): fuehre LockRunner, AtomicWriter, StateStore/StateValidator und ResetExecutor ein (iss-011)`
3. [x] `feat(runtime): fuehre TriggerReason, DecisionPolicy und ActionPlan fuer IP_SALT ein (iss-011)`
4. [x] `feat(runtime): integriere IP_SALT-Manager mit symfony/lock und Fingerprint-Guardrails (iss-011)`
5. [x] `chore(cli+config): entferne --rotate-ip-salt und nutze ip-hash reset (iss-011)`
6. [x] `test(runtime): ergaenze IP_SALT-Parallel- und Guardrail-Tests (iss-011)`

### Folgeblöcke (aktiv fuer ISS-012)
7. [ ] `refactor(runtime): uebernehme Locking-Rahmen fuer Rate-Limit und CAPTCHA-Verify (iss-012)`
8. [ ] `refactor(runtime): uebernehme Locking-Rahmen fuer Token-Rotation (iss-012)`
9. [ ] `test(runtime): ergaenze Race-nahe Tests fuer Rate-Limit, CAPTCHA und Token (iss-012)`
10. [ ] `docs(runtime): aktualisiere Nachweise und Betriebsnotiz fuer ISS-012 (iss-012)`

## Einschätzung des Ablaufs
- Kohärenz: hoch, weil `ISS-011` als schmaler Referenzpfad den Rahmen setzt.
- Risiko: mittel, aber gut beherrschbar durch kleine Commits und klare Scope-Grenzen.
- Reviewbarkeit: hoch, da die Startsequenz getrennt nach Rahmen, Fachlogik und Migration arbeitet.
- Hauptgefahr: Scope-Drift durch zu frühe flächige Abstraktion.
- Gegenmaßnahme: in `ISS-011` nur minimalen Rahmen bauen und erst in `ISS-012` breit ausrollen.

## Statusabgleich aus Branch-Review (Stand 2026-02-13)
- [x] `ISS-012`: Kein Fallback von `symfony/lock` auf eigenes `flock` (Fail-Fast bei fehlender Dependency).
- [x] `ISS-012`: Lock-Erwerb mit Polling + Timeout statt unbegrenztem Blockieren.
- [x] `ISS-011`: Konsistenzmarker und Recovery-Regel für den IP-bezogenen Runtime-State umgesetzt.
- [ ] `ISS-012`: Rahmen auf `RateLimiter`, `CaptchaService`, `TokenService` ausrollen.
- [ ] `ISS-012`: Race-nahe Tests für diese Bereiche als Abschlussnachweis ergänzen.

## Entscheidungsfestlegung (festgelegt)
Stand: 2026-02-13

1. `ISS-012`: Kein Fallback von `symfony/lock` auf eigenes `flock`.
2. `ISS-012`: Lock-Erwerb via Polling + Timeout statt unbegrenztem `acquire(true)`.
3. `ISS-011`: Für MVP primär Konsistenzmarker + Recovery-Regel; in ISS-011 umgesetzt.

### Architekturentwurf (MVP)
- Gemeinsamer schlanker Ablauf pro Ressource:
  - Eingaben prüfen und Lock-Key bestimmen.
  - Bei Write-Race einen Lock erwerben.
  - State lesen und validieren.
  - Trigger/Entscheidung ableiten.
  - Nur notwendige Mutation atomar schreiben.
  - Lock freigeben.
- Marker-Regel:
  - `IN_PROGRESS`/`READY` ist für mehrstufige Übergänge sinnvoll (verbindlich für `IP_SALT`, optional für andere Verwalter).
- Recovery:
  - Inkonsistenter Zustand führt zu deterministischer Wiederherstellung unter Lock, domänenspezifisch je Verwalter.

### Risiken und Gegenmaßnahmen
- Risiko: Zu kurzer Timeout erzeugt unnötige Fehlschläge.
  Gegenmaßnahme: Timeout konfigurierbar halten, Startwert 300 ms, Messung und Nachjustierung.
- Risiko: Fehlende Lock-Dependency im Setup.
  Gegenmaßnahme: Fail-Fast mit klarer Fehlermeldung und CI-Prüfung auf Lockfile-Konsistenz.
