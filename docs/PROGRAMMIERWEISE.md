# Programmierweise

## Technische App-Konstanten

### Ziel
Technische Konstanten sollen Duplikate vermeiden und den Produktionscode klar halten.

### Entscheidungsmatrix
1. Lokal halten: Wenn eine Konstante nur in einer Klasse oder in einem Modul genutzt wird.
2. Geteilt auslagern: Wenn dieselbe Konstante in mehreren Produktionskontexten gebraucht wird (z. B. HTTP, CLI, Runtime).
3. Nicht global sammeln: Keine zentrale Sammeldatei für alle Konstanten.

### Form
- Modul-spezifisch: kleine `final class` am Dateianfang im passenden Bereich.
- Bereichsübergreifend: kleine, thematische Konstantenklasse mit klarer Zuständigkeit.
- Konstanten stehen in der jeweiligen Datei oben.

### Scope
- Erlaubt: Pfade, Dateinamen, Lock-Keys und andere technische Schlüssel.
- Nicht erlaubt: fachliche Werte oder umgebungsabhängige Konfiguration.

### Tests und Produktionsschnitt
- Tests dürfen produktive Konstanten direkt nutzen.
- Refactors in `src/` werden durch Produktionsnutzen begründet, nicht durch Test-Bequemlichkeit.
- Falls nur Tests betroffen sind und kein Produktionsnutzen entsteht, bleibt die Konstante lokal.

## Branch-Namen
- Branch-Namen sind auf Englisch zu vergeben.
- Empfohlenes Muster: `type/scope-short-description`.
- Beispiel: `feature/docs-app-programming-guidelines`.

### Beispiel
```php
final class IpSaltPaths
{
    public const STATE_DIR = 'var/state';
    public const LOCKS_DIR = 'var/state/locks';
}

final class IpSaltFiles
{
    public const SALT_FILE = 'ip_salt.txt';
    public const FINGERPRINT_FILE = 'ip_salt.fingerprint';
}
```
