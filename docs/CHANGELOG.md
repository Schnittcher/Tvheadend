# Changelog

## 19.10.2026 - Version 2.0.1

### Fixes
- Die Instanzen mit Kachel (Kanäle, DVR, Aufnahmeregeln, EPG, Streaming und Tuner) lassen sich unter Symcon 9.0 wieder anlegen, vorher brach das Anlegen mit "Undefined constant" ab.

## 12.10.2026 - Version 2.0

### Neu
- Neue Module für Tvheadend ab Version 4.3: Gateway, Konfigurator, Server, DVR, EPG, EPG-Kachel, Aufnahmeregeln, Tuner, Streaming, Netzwerk, Diagnose und Kanalverwaltung. Das bisherige Modul bleibt unverändert erhalten.
- Live-Daten über den WebSocket des Servers: Aufnahmen, Streams, Tuner, Speicherplatz und mehr aktualisieren sich ohne Wartezeit, auch wenn der Server den WebSocket nur kurz hält.
- Gateway mit Konfigurator: Zugangsdaten stehen nur im Gateway, der Konfigurator legt alle weiteren Instanzen an.
- Server: Online-Zustand, Version, Speicherplatz, nächste Aktivität, "Ruhezustand erlaubt" und Wake-on-LAN.
- DVR: Zähler, nächste Aufnahme und Kachel mit geplanten, fertigen und fehlgeschlagenen Aufnahmen. Aufnahmen lassen sich anlegen, stoppen und löschen.
- EPG: Variablen für "Läuft jetzt" und "Als Nächstes" ausgewählter Kanäle, Suche im Programm, Aufnahme aus dem Programm und Stream-Adressen.
- EPG-Kachel: Programmführer für die Kachel-Visualisierung als Zeitleiste oder Liste nach Kanalnummer, mit Aufnehmen, Serie aufnehmen und Löschen.
- Aufnahmeregeln: Alle Serien- und Zeitaufnahmen anlegen, bearbeiten, ein- und ausschalten und löschen, im Formular, in einer Kachel und per Funktion.
- Tuner: Alle Tuner in einer Instanz mit Belegung, Signal, SNR und Bitrate, optional als Variablen je Tuner, mit Listen-Kachel.
- Streaming: Wer schaut gerade was, Clients trennen, mit Kachel.
- Netzwerk: Muxe, Scan-Ergebnisse, Services und Scan-Warteschlange je Netzwerk, Scan starten.
- Diagnose: Probleme im Serverlog und EPG-Grabber samt nächsten Abrufen.
- Kanalverwaltung: Kanäle nach Kanalnummer aktivieren, deaktivieren und umbenennen, im Formular, in einer Kachel und per Funktion.
- Kacheln übernehmen Schrift, Größe, Ränder und Farben der Kachel-Visualisierung. Die maximierte Ansicht gibt es ab IP-Symcon 9.1.
- Achtung: Die neuen Module sind eigenständig. Bestehende Instanzen des bisherigen Moduls laufen weiter, ein Umstieg bedeutet, neue Instanzen über Gateway und Konfigurator anzulegen. Ausschalten des Servers per SSH gibt es in den neuen Modulen nicht.

## 27.08.2020 - Version 1.8.1

### Fixes
- Wenn Server offline ist, kommen keine Fehlermeldungen mehr.

## 26.06.2019 - Version 1.8

### Fixes
- Beim Versuch den Server herunterzufahren, wenn er offline ist, gab es eine Fehlermeldung im Log.

## 15.02.2019 - Version 1.7

### Neu
- Übersetzungen hinzugefügt

## 07.02.2019 - Version 1.6

### Fixes
- module.json für IPS 5.1 angepasst

## 27.08.2018 - Version 1.5

### Neu
- Wenn keine Aufnahme vorhanden sind, werden die Variablen geleert

### Fixes
- Bei Subscriptions wird nun geprüft ob die Array Keys vorhanden sind.

## 27.08.2018 - Version 1.4

### Neu
- Variable hinzugefügt, ob eine Aufnahme aktiv ist oder nicht, eine Vorlaufzeit und Nachlaufzeit kann in den Einstellungen gesetzt werden.

### Fixes
- Fehler bei den Aufnahmen gefixt (subtitle wurde nicht gefunden)

## 26.08.2018 - Version 1.3

### Fixes
- Username und Passwort für das Webinterface konnten nicht eingestellt werden.

## 15.08.2018 - Version 1.2

### Fixes
- Konfigurationsformular JSON
- Errors wenn keine User mit dem TVH Server verbunden sind

## 31.07.2018 - Version 1.1

### Neu
- Anzahl der Subscriptions hinzugefügt

## 31.07.2018 - Version 1.0

### Neu
- Erste Version
