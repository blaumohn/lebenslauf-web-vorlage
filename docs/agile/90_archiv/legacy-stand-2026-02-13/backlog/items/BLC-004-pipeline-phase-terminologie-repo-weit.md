# BLC-004: Pipeline-Phase Terminologie repo-weit vereinheitlichen

### Ziel
- Den Begriff `Pipeline-Phase` fuer `pipeline + phase` in App, Config-Lib und Doku einheitlich verwenden.

### Problem
- Unterschiedliche Begriffe (`Pipeline-Kontext`, freie Varianten) fuehren zu uneinheitlicher Sprache.
- Inkonsistente Terminologie erschwert Onboarding, Reviews und Suchabfragen.

### Vorschlag
- Terminologie-Audit repo-weit durchfuehren und Altbegriffe auf `Pipeline-Phase` umstellen.
- Suchmuster definieren und dokumentieren (z. B. `Pipeline-Kontext`, `pipeline + phase`).
- Aenderungen auf rein sprachliche Konsistenz begrenzen (keine funktionalen Aenderungen).

### Akzeptanzkriterien
- In den definierten Zielbereichen werden keine Altbegriffe mehr verwendet.
- `Pipeline-Phase` ist als Standardbegriff in den relevanten Doku- und CLI-Texten konsistent.
- Der Abschluss wird ueber einen dokumentierten Such-Check nachvollziehbar gemacht.

### Moegliche Folge-Tasks
- Spike: Zielbereiche und Suchmuster final festlegen.
- Story: Konsistenz-Update in App/CLI/Doku als kleine PR-Serie umsetzen.
- Story: Terminologie-Check in Doku-Pflegeprozess aufnehmen.
