# ANLAGE: Verwalter-Programmfluss und Betriebsvergleich (ISS-012)

## Typ
- Anlage (Design/Tech)

## Bezug
- [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md)
- [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md)

## Ziel
- Gemeinsamen Programmfluss für Runtime-Verwalter vergleichbar machen.
- Festlegen, welche Rahmen-Bausteine in `ISS-011` vorgezogen werden dürfen.
- Trennscharf halten, welche flächigen Umstellungen in `ISS-012` verbleiben.

## Geltungsbereich
- Rate-Limit: `allow`
- CAPTCHA: `createChallenge`, `verify`, `cleanupExpired`
- Token: `rotate`, `verify`, `findProfileForToken`
- `IP_SALT`-Runtime aus `ISS-011` als Referenz für den Rahmen

## Empfohlener Zuschnitt (ISS-012)
- Gemeinsame Runtime-I/O-Bausteine:
  - `RuntimeLockRunner`
  - `RuntimeAtomicWriter`
- Pro Verwalter eigener Fachpfad:
  - Zustand (`*State`), Validierung (`*StateValidator`), Entscheidung (`*DecisionPolicy`), Ablauf (`*ActionPlan`), konkrete Mutation (`*Executor`/Writer)
- `IP_SALT` bleibt Referenzimplementierung; `RateLimiter`, `CaptchaService`, `TokenService` übernehmen dasselbe Muster schrittweise.

## Gemeinsamer Rahmen (verbindlich)
- Locking für kritische Schreibpfade wird mit `symfony/lock` umgesetzt.
- Lock-Erzeugung erfolgt zentral über `LockFactory`.
- Kritische Abschnitte laufen über einen gemeinsamen Helfer (`runWithLock`).
- Dateischreiben erfolgt atomar (Temp-Datei + `rename`).
- Fehler im kritischen Abschnitt brechen den Vorgang konsistent ab (Fail-Fast).

## Vergleich der Verwalter-Betriebe
| Bereich | Operation | Zugriffsmuster | Lock-Key-Granularität | Reset/Bereinigung | Atomarer Write |
| --- | --- | --- | --- | --- | --- |
| Rate-Limit | `allow(key, max, window)` | Read-Modify-Write einer Counter-Datei | pro `key` | keine globale Bereinigung; nur Fensterlogik | ja |
| CAPTCHA | `createChallenge(ipHash)` | Write einer neuen Challenge-Datei | kein Lock zwingend bei eindeutiger ID; optional global | keine | ja |
| CAPTCHA | `verify(captchaId, answer, ipHash)` | Read-Modify-Write derselben Challenge-Datei | pro `captchaId` | bei Erfolg `used_at` setzen; kein globaler Reset | ja |
| CAPTCHA | `cleanupExpired()` | Iterate + Delete vieler Dateien | optional globaler Wartungs-Lock | löscht abgelaufene/verbrauchte Challenges | n/a (Delete) |
| Token | `rotate(profile, tokens)` | Ersetzen einer Profil-Datei | pro `profile` | ersetzt alten Stand vollständig | ja |
| Token | `verify` / `findProfileForToken` | Read-only | kein Write-Lock; Konsistenz über atomare Writes | keine | n/a |
| IP_SALT | `resolveSalt()` | Read + Validierung + ggf. Rotate + Bereinigung | global (`ip_salt_runtime`) | bei Mismatch/Fehlen: IP-bezogenen State bereinigen | ja |
| IP_SALT | `resetSalt()` | explizite Rotation + Bereinigung | global (`ip_salt_runtime`) | immer IP-bezogenen State bereinigen | ja |

## Auslösungs- und Entscheidungsmodell (Entwurf)
| Bereich | Auslöser (Trigger) | Entscheidung (`DecisionPolicy`) | Ausführung (`ActionPlan`/Executor) |
| --- | --- | --- | --- |
| IP_SALT `resolveSalt` | `MISSING`, `INVALID`, `MISMATCH`, `CLEAN` | Trigger aus State und Fingerprint ableiten | bei `CLEAN`: no-op, sonst `IN_PROGRESS` -> rotate/clear -> `READY` |
| IP_SALT `resetSalt` | `EXPLICIT_RESET` | immer Reset | `IN_PROGRESS` -> rotate/clear -> `READY` |
| Rate-Limit `allow` | `WINDOW_OK`, `LIMIT_REACHED`, `STATE_INVALID` | erlauben/ablehnen bestimmen | bei `WINDOW_OK`: Counter atomar fortschreiben, bei `LIMIT_REACHED`: no-op |
| CAPTCHA `verify` | `MISSING`, `EXPIRED`, `USED`, `IP_MISMATCH`, `ANSWER_MISMATCH`, `SOLVED` | Gültigkeit und Antwort bewerten | `ANSWER_MISMATCH`: `fail_count` erhöhen, `SOLVED`: `used_at` setzen |
| CAPTCHA `cleanupExpired` | `EXPLICIT_CLEANUP` | Wartungslauf ausführen | abgelaufene/verbrauchte Dateien löschen |
| Token `rotate` | `EXPLICIT_ROTATE` | Rotation zulassen | Profil-Datei atomar ersetzen |
| Token `verify/findProfileForToken` | `READ_ONLY_CHECK` | nur Lesepfad | keine Mutation |

## Einheitlicher Programmfluss (Soll)
1. Eingaben prüfen und Lock-Key bestimmen.
2. Lock nur dort erwerben, wo Write-Race möglich ist.
3. Aktuellen Zustand laden und validieren.
4. Entscheidungsregel auswerten (kein Write, Update, Reset, Delete).
5. Zustand atomar schreiben oder gezielt bereinigen.
6. Ergebnisobjekt zurückgeben (Status + Kontext).
7. Lock immer in `finally` freigeben.

## Policy und Betriebsannahmen (verbindlich)
- Fail-Fast bleibt Standard: begrenzter Lock-Timeout, kein unbegrenztes Warten.
- Startwerte bleiben für MVP: Timeout 300 ms, Poll-Intervall 25 ms.
- Lock-Key-Strategie bleibt bereichsspezifisch:
  - global (`IP_SALT`)
  - pro Schlüssel (`Rate-Limit`, `CAPTCHA verify`)
  - pro Profil (`Token rotate`)
- Betriebsannahme Single-Host:
  - `FlockStore` ist ausreichend, solange alle Worker denselben Host und dasselbe Lock-Verzeichnis sehen.
- Betriebsannahme Multi-Host:
  - vor Multi-Host-Betrieb muss ein verteilter Lock-Store festgelegt und dokumentiert werden.
- Dateisystemannahme:
  - atomisches `rename` gilt nur innerhalb desselben Dateisystems.

## Abgrenzung ISS-011 vs ISS-012
| Baustein | In ISS-011 erlaubt | In ISS-012 verpflichtend |
| --- | --- | --- |
| Zentraler Lock-Helfer (`symfony/lock`) | ja, minimal für `IP_SALT` | ja, für alle kritischen Runtime-Schreibpfade |
| Atomarer Write-Helfer | ja, falls für `IP_SALT` benötigt | ja, harmonisiert für alle betroffenen Bereiche |
| Schlüssel-/Profil-Lockstrategie für alle Verwalter | nein | ja |
| Flächige Migration von Rate-Limit/CAPTCHA/Token auf den Rahmen | nein | ja |
| Race-nahe Tests pro Bereich | nur `IP_SALT`-Pfad | ja, komplett für alle Zielbereiche |

## Arbeitsreihenfolge (empfohlen)
1. In `ISS-011` nur den minimalen Rahmen für `IP_SALT` festziehen.
2. In `ISS-012` denselben Rahmen auf Rate-Limit, CAPTCHA und Token ausrollen.
3. Erst nach der Ausrollung gemeinsame Runtime-Race-Tests als Abschlussnachweis führen.
