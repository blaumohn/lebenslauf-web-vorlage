# BLC-002: Zentrales Fehlerkonzept

### Ziel
- Fehler nah an der Entstehungsquelle werfen und zentral einheitlich behandeln.

### Problem
- Fehlerbehandlung ist ueber mehrere Stellen verteilt.
- Inkonsistente Fehlermeldungen erschweren Diagnose und CI-Auswertung.

### Vorschlag
- Einheitliche Exception-Hierarchie einfuehren (z. B. ConfigLoadError, PolicyError).
- Fehler dort werfen, wo sie entstehen.
- Zentrale Uebersetzung fuer CLI/HTTP-Ausgabe in einer Stelle.

### Akzeptanzkriterien
- Einheitliches Fehlerformat in CLI-Ausgaben.
- Keine duplizierte Fehleraufbereitung in mehreren Klassen.
- Klare Trennung: Domainenfehler vs. Praesentationsausgabe.

### Moegliche Folge-Tasks
- Spike: Fehlerklassen und Mapping-Regeln definieren.
- Story: Policy-/Loader-Fehler auf neue Exceptions umstellen.
- Story: Zentrale Fehlerausgabe in CLI-App vereinheitlichen.
