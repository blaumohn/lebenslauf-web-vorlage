# ISSUE: App-interne Konstanten fuer Pfade und Runtime-Schluessel

## Typ
- Issue (Delivery/Design)

## Status
- Offen

## Abgeleitet aus
- [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md)
- [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md)

## Problem
- App-interne technische Konstanten (z. B. Pfade, Dateinamen, Lock-Keys) sind mehrfach im Code verteilt.
- Dadurch entstehen Duplikate, inkonsistente Benennungen und hoehere Aenderungskosten bei Refactors.
- In neuen Runtime-Pfaden (u. a. `IP_SALT`) werden dieselben Pfadteile wiederholt gebaut.

## Ziel
- Technische Konstanten entlang ihres Verwendungsumfangs strukturieren (modulnah vor global).
- Geteilte Pfad- und Schluesseldefinitionen in kleinen, thematischen Konstantenklassen pflegen und in Runtime/CLI wiederverwenden.
- Die Lesbarkeit von `ISS-011`/`ISS-012`-nahem Code verbessern und Wiederholungen abbauen.

## Referenz-Zitat (Anforderung)
```php
+        return new IpSaltService(
+            $storage,
+            $lockRunner,
+            $writer,
+            Path::join($rootPath, 'var', 'state'),
+            Path::join($rootPath, 'var', 'tmp', 'captcha'),
+            Path::join($rootPath, 'var', 'tmp', 'ratelimit')
```

## Scope
- Konstanten-Schnitt definieren:
  - Modul-spezifisch: `final class` im jeweiligen Bereich (am Dateianfang).
  - Bereichsuebergreifend: kleine, thematische Konstantenklasse (kein globaler Sammelcontainer).
- Mindestens diese Bereiche konsolidieren:
  - Runtime-Pfade (`var/state`, `var/tmp/captcha`, `var/tmp/ratelimit`, `var/state/locks`)
  - Dateinamen/Keys fuer `IP_SALT`-Runtime (`ip_salt.state.json`, Marker-Keys, Lock-Key)
- Nutzung in `AppContext`, `IpSaltService`, `IpHashCommand` und testsnahen Hilfspfaden vereinheitlichen.
- Leitplanke dokumentieren:
  - Nur app-interne technische Konstanten.
  - Keine fachlichen/env-abhaengigen Config-Werte in diese Komponente verschieben.
  - Refactors in `src/` erfolgen aus Produktionssicht; Tests duerfen mitnutzen, treiben aber keine Strukturentscheidungen.

## Nicht im Scope
- Aenderungen an Config-Lib-Modellen oder Manifest-Semantik.
- Leistungsoptimierung als eigenes Zielkriterium.
- Vollstaendige Runtime-Concurrency-Migration (bleibt in [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md)).

## Akzeptanzkriterien
- Modul-spezifische Konstanten sind als `final class` im passenden Bereich gebuendelt.
- Bereichsuebergreifende Konstanten liegen in kleinen, thematischen Konstantenklassen statt in einer globalen Sammeldatei.
- Die `IP_SALT`-nahen Pfaddefinitionen werden nicht mehr mehrfach inline aufgebaut.
- Lock-Key-/Dateiname-Konstanten sind konsistent benoetigt und wiederverwendet.
- Tests nutzen dieselben produktiven Konstantenquellen; es entstehen keine testgetriebenen Umbauten ohne Produktionsnutzen.
- Keine funktionale Regression in `ISS-011`-Pfaden (nachweisbar ueber bestehende Tests).

## Abhaengigkeiten
- Story-Kontext:
  - [STY-001](STY-001-qualitaetsrahmen-repo-app-und-config-lib.md)
- Voraussetzungen:
  - [ISS-011](ISS-011-ip-salt-runtime-verwaltung-und-guardrails.md) (mindestens Rahmen stabil)
- Wirkt auf:
  - [ISS-012](ISS-012-runtime-concurrency-locking-und-atomare-zugriffe.md)
