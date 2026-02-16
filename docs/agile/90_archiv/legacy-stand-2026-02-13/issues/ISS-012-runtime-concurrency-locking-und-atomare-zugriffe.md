# ISSUE: Runtime-Concurrency, Locking und atomare Zugriffe

## Typ
- Issue (Delivery/Tech)

## Status
- Aktiv (Restscope offen, Stand 2026-02-13)
- Teilentscheidungen aus ISS-011 umgesetzt: kein `flock`-Fallback, Lock-Timeout mit Polling.

## Abgeleitet aus
- [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md)
- Laufzeit-Befund zu Race-Conditions in `src/http`

## Problem
- Mehrere Runtime-Module schreiben gemeinsamen Dateizustand ohne durchgaengiges Locking.
- Read-Modify-Write-Pfade koennen unter Parallelzugriff inkonsistent werden.
- Atomare Writes sind teilweise vorhanden, aber nicht konsistent in allen kritischen Pfaden.

## Ziel
- Kritische Runtime-Schreibpfade sind unter Parallelzugriff konsistent.
- Locking wird einheitlich ueber `symfony/lock` umgesetzt.
- Read-Modify-Write-Operationen werden pro Schlüssel/Datei sauber serialisiert.

## Ergaenzende Doku
- [Anlage: Verwalter-Programmfluss und Betriebsvergleich](ISS-012-anlage-verwalter-programmfluss-und-betriebsvergleich.md)
- [Anlage: ISS-011/ISS-012 Näherung und Commit-Folge](ISS-011-012-anlage-naeherung-und-commitfolge.md)

## Scope
- Ausrollung des `symfony/lock`-Rahmens auf weitere Runtime-Dateizugriffe (fuer `IP_SALT` bereits umgesetzt).
- Architekturrahmen gemaess Anlage flaechig ausrollen:
  - Komposition mit `LockRunner`, `AtomicWriter`, `StateStore/StateValidator`, `ResetExecutor`.
  - Trigger-/Policy-Muster (`TriggerReason`, `DecisionPolicy`, `ActionPlan`) konsistent je Verwalter anwenden.
- Lock-Strategie festlegen:
  - schluesselbezogene Locks fuer Rate-Limit und CAPTCHA-Verify.
  - profilbezogene Locks fuer Token-Rotation.
  - zentrale Lock-Helfer fuer wiederverwendbare Guards.
- Atomare Write-Helfer harmonisieren (eindeutige Temp-Datei + `rename`).
- Tests:
  - Parallel-/Race-nahe Tests fuer kritische Pfade.
  - Regression-Tests fuer bestehendes Verhalten.
- Doku:
  - kurze Betriebsnotiz, welche Runtime-Bereiche gelockt sind und warum.

## Nicht im Scope
- FTP/FTPS-Remote-Verwaltung (eigene Folge-Issue).
- Allgemeines Refactoring ausserhalb der betroffenen I/O-Pfade.

## Akzeptanzkriterien
- Rate-Limit, CAPTCHA-Verify und Token-Rotation laufen ohne bekannte Read-Modify-Write-Races.
- File-Writes nutzen konsistent atomare Muster.
- Locking ist im Runtime-Code zentral erkennbar und testbar.
- `ISS-005` kann fuer Runtime-Stabilitaet auf diesen Nachweis verweisen.

## Abhaengigkeiten
- Story-Kontext:
  - [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- Voraussetzungen:
  - [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md)
- Wirkt auf:
  - [ISS-005](ISS-005-preview-workflow-reenable-from-dev.md)
- Folge-Issue:
  - [ISS-013](ISS-013-ftp-ftps-verwaltungs-skripte-fuer-preview-betrieb.md) (nachgelagert, nicht blockierend fuer ISS-005)

## Abgleich zum Ist-Stand (feature/iss-005-preview, Stand 2026-02-13)
- [x] Locking läuft einheitlich über `symfony/lock` (`RuntimeLockRunner`), ohne Fallback auf manuelles `flock`.
- [x] Lock-Erwerb nutzt Polling + Timeout (Startwert 300 ms, Poll-Intervall 25 ms), kein unbegrenztes `acquire(true)`.
- [x] `ISS-011`-Konsistenzmarker (`IN_PROGRESS`/`READY`) und Recovery-Regel sind produktiv umgesetzt.
- [ ] Ausrollung des Rahmens auf weitere Runtime-Modelle (`RateLimiter`, `CaptchaService`, `TokenService`) steht noch aus.
- [ ] Race-nahe Tests für die neuen Zielbereiche stehen noch aus.

## Entscheidungsfestlegung (festgelegt)
Stand: 2026-02-13

- Locking bleibt einheitlich über `symfony/lock`; kein Runtime-Fallback auf eigenes `flock`.
- Lock-Erwerb bleibt begrenzt (Polling + Timeout) mit deterministischem Fail-Fast.
- `ISS-011` gilt als Referenzpfad; `ISS-012` erweitert denselben Rahmen auf weitere Datenmodelle.

### Konkrete Umsetzungsvorschläge (ISS-012)
1. Infrastruktur verallgemeinern:
   - `RuntimeLockRunner` und `RuntimeAtomicWriter` als gemeinsame I/O-Bausteine für alle Verwalter nutzen.
2. Fachliche Schichten je Verwalter beibehalten:
   - pro Bereich `StateStore`/`StateValidator`, `DecisionPolicy`, `ActionPlan`, `ResetExecutor` analog zu `IpSaltService`.
3. Lock-Granularität verbindlich dokumentieren:
   - Rate-Limit pro Schlüssel, CAPTCHA pro `captcha_id` (plus optionaler Wartungs-Lock), Token pro Profil.
4. Ordner-/Namensschnitt festziehen:
   - Ressourcenschnittstellen werden als `*Service` gefuehrt (z. B. `IpSaltService`), gemeinsame I/O-Helfer liegen in einem bereichsneutralen Runtime-I/O-Ort.
5. Testsequenz vorziehen:
   - zuerst parallele Konfliktfälle für Rate-Limit/CAPTCHA/Token, danach Integrationsnachweis für `ISS-005`.

### Begründung
- Entscheidungen aus `ISS-011` sind bereits im Code belastbar und müssen nicht erneut offen geführt werden.
- Der verbleibende Aufwand liegt nicht mehr bei der Lock-Grundsatzfrage, sondern bei der flächigen Übernahme auf weitere Runtime-Modelle.

### Entwurfsreferenz
- Detaillierter Entwurf für Zerlegung, Auslösungs-/Entscheidungsmodell sowie Policy/Betriebsannahmen: [Anlage: Verwalter-Programmfluss und Betriebsvergleich](ISS-012-anlage-verwalter-programmfluss-und-betriebsvergleich.md).

### Arbeitsfolge gegenüber ISS-011
- `ISS-012` ist in der Arbeitsfolge direkt an `ISS-011` anschlussfähig (gleicher Rahmen).
- Die Umsetzung ist bewusst nicht 1:1 identisch: je Verwalter unterscheiden sich Trigger und Mutationen.
- Verbindlich gleich sind nur die Guardrails: Lock-Policy, atomare Writes, deterministische Fehlerpfade und testbarer Lock-Key-Zuschnitt.
