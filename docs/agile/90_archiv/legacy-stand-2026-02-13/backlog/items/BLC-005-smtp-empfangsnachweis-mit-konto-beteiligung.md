# BLC-005: SMTP-Empfangsnachweis mit Konto-Beteiligung

### Ziel
- Echten Empfang von Testmails pro Umgebung nachweisbar machen, nicht nur SMTP-Uebergabe.

### Problem
- SMTP-Handshake, Authentifizierung und `250 accepted` belegen nur die Uebergabe an den naechsten Mailserver.
- Ohne Konto-Abruf bleibt unklar, ob Mails im Zielpostfach tatsaechlich ankommen oder auf dem Weg verworfen werden.

### Vorschlag
- Fuer jede Zielumgebung ein dediziertes Testpostfach bzw. einen Testordner definieren.
- Nach Testversand eine Empfangspruefung ueber IMAP/Provider-API durchfuehren.
- Pruefung mit festem Timeout und eindeutiger Korrelation (`run_id` im Betreff) ausfuehren.
- Ergebnis als Workflow-Artefakt speichern (Zeit, Message-ID, Treffer ja/nein, Dauer).

### Akzeptanzkriterien
- Versand und Empfang sind als getrennte Nachweise im Workflow sichtbar.
- Empfangspruefung endet deterministisch (Erfolg oder Timeout-Fehler).
- Nachweisdaten sind als CI-Artefakt pro Lauf verfuegbar.
- Geheimnisse und Zugriffsrechte fuer Konto-Abruf sind dokumentiert und minimal gehalten.

### Moegliche Folge-Tasks
- Spike: IMAP vs Provider-API je Umgebung bewerten.
- Story: `scripts/mail/verify-inbox` fuer Betreff-/Message-ID-Suche bauen.
- Story: Workflow-Job fuer Versand + Empfangspruefung + Artefaktablage integrieren.
