# BLC-001: Typensicherheit statt vieler Type-Guards

### Ziel
- Laufzeitpruefungen reduzieren, indem mehr Struktur ueber Typen modelliert wird.

### Problem
- Viele verteilte Type-Guards machen Code laenger und schwerer wartbar.
- Fehler werden oft erst zur Laufzeit sichtbar.

### Vorschlag
- Typen fuer zentrale Config-Daten und Snapshot-Strukturen schaerfen.
- Guards nur an I/O-Grenzen behalten (Datei, Env, CLI).
- Interne Verarbeitung auf typisierte Strukturen umstellen.

### Akzeptanzkriterien
- Weniger Guard-Logik in Kernpfaden.
- Gleiche oder bessere Testabdeckung.
- Keine funktionale Regression in `values()`, `validate()` und `compile()`.

### Moegliche Folge-Tasks
- Spike: Typmodell fuer Manifest/Phase/Sources entwerfen.
- Story: Loader/Resolver auf typisierte DTOs umstellen.
- Story: Unnoetige Guards im Kern entfernen.
