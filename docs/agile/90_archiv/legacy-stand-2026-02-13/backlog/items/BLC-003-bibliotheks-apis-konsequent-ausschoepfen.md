# BLC-003: Bibliotheks-APIs konsequent ausschoepfen

### Ziel
- Vorhandene Bibliotheken in `lebenslauf-web-vorlage` und `pipeline-config-spec-php` breiter nutzen, statt Hilfslogik selbst zu bauen.

### Problem
- Bibliotheken werden derzeit nur teilweise genutzt.
- Beispiel: Bei `symfony/filesystem` wird aktuell vor allem `Path` verwendet, obwohl weitere Bausteine wiederkehrende Dateisystemlogik abdecken koennen.
- Eigene Hilfslogik erhoeht Umfang und Uneinheitlichkeit im Code.

### Vorschlag
- Pro Teilbereich pruefen, welche Bibliotheksfunktionen Standardaufgaben bereits abdecken.
- Wiederkehrende Eigenlogik schrittweise durch robuste Bibliotheksbausteine ersetzen.
- Konvention definieren: Erst Bibliothek pruefen, dann eigene Abstraktion bauen.
- `symfony/filesystem` nur als Startpunkt behandeln; das Prinzip gilt allgemein fuer relevante Bibliotheken.

### Akzeptanzkriterien
- In beiden Projekten sind konkrete Stellen dokumentiert, an denen Bibliotheksbausteine Eigenlogik ersetzen.
- Code wird kuerzer oder klarer, ohne Verhaltensaenderung.
- Einfache Standardaufgaben (z. B. Dateisystemoperationen) folgen konsistenten Bibliotheksmustern.
- Tests bleiben gruen bzw. werden bei Refactorings passend ergaenzt.

### Moegliche Folge-Tasks
- Spike: Inventur wiederkehrender Eigenlogik in beiden Projekten.
- Story: Dateisystemnahe Hilfslogik auf `symfony/filesystem`-Bausteine umstellen.
- Story: Team-Konvention fuer Bibliotheksnutzung in `README` oder Contribution-Guide festhalten.
