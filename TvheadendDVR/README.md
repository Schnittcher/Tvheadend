# Tvheadend DVR
Zeigt die Aufnahmen des Servers: geplante, laufende, fertige und fehlgeschlagene. Die Instanz liefert Zähler, die nächste Aufnahme und eine Kachel für die Visualisierung, in der man Aufnahmen stoppen und löschen kann. Per Funktion lassen sich Aufnahmen anlegen. Die Instanz wird über den Konfigurator angelegt.

## Inhaltsverzeichnis
- [Tvheadend DVR](#tvheadend-dvr)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Kachel](#4-kachel)
  - [5. Spenden](#5-spenden)
  - [6. Lizenz](#6-lizenz)

## 1. Konfiguration

Die Instanz hat keine Einstellungen. Sie aktualisiert sich bei jeder Änderung an den Aufnahmen live über den WebSocket des Servers.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Aktive Aufnahme | `RecordingActive` | Ja/Nein | Mindestens eine Aufnahme läuft gerade.
Laufende Aufnahmen | `RecordingCount` | Ganzzahl | Anzahl der laufenden Aufnahmen.
Geplante Aufnahmen | `UpcomingCount` | Ganzzahl | Anzahl der geplanten Aufnahmen.
Fertige Aufnahmen | `FinishedCount` | Ganzzahl | Anzahl der fertigen Aufnahmen im Log des Servers.
Fehlgeschlagene Aufnahmen | `FailedCount` | Ganzzahl | Anzahl der fehlgeschlagenen Aufnahmen.
Nächste Aufnahme - Kanal | `NextChannel` | Text | Kanal der laufenden oder als Nächstes geplanten Aufnahme.
Nächste Aufnahme | `NextTitle` | Text | Titel dieser Aufnahme, "Keine Aufnahme geplant", wenn es keine gibt.
Nächste Aufnahme - Startzeit | `NextStart` | Datum/Uhrzeit | Beginn der Aufnahme.
Nächste Aufnahme - Endzeit | `NextStop` | Datum/Uhrzeit | Ende der Aufnahme.
Aufnahmen | `RecordingList` | Text (HTML) | Tabelle der laufenden und der nächsten geplanten Aufnahmen (bis zu zehn Einträge).

Alle Variablen sind schreibgeschützt.

## 3. Funktionen

`void TVHD_Update(integer $InstanzID);`
Liest die Aufnahmen neu vom Server.

`boolean TVHD_RecordEvent(integer $InstanzID, integer $EventId, string $ConfigUuid);`
Legt eine Aufnahme für eine Sendung an. Die `EventId` steht in den Ergebnissen von `TVHE_Search` (EPG-Instanz). Ohne `ConfigUuid` nutzt der Server sein Standardprofil.

`boolean TVHD_StopRecording(integer $InstanzID, string $Uuid);`
Beendet eine laufende Aufnahme sauber, die Datei bleibt erhalten.

`boolean TVHD_CancelRecording(integer $InstanzID, string $Uuid);`
Löscht einen Timer. Eine laufende Aufnahme wird abgebrochen und als fehlgeschlagen geführt.

`boolean TVHD_RemoveRecording(integer $InstanzID, string $Uuid);`
Löscht eine fertige oder fehlgeschlagene Aufnahme samt Datei vom Server. Das lässt sich nicht rückgängig machen.

## 4. Kachel

Die Instanz erscheint in der Kachel-Visualisierung als Liste mit den Reitern "Geplant", "Fertig" und "Fehlgeschlagen" samt Anzahl und einer Suche. Laufende Aufnahmen stehen oben und lassen sich stoppen. Timer und Aufnahmen lassen sich mit Rückfrage löschen (bei fertigen Aufnahmen auch die Datei). Die maximierte Ansicht der Kachel gibt es ab IP-Symcon 9.1.

## 5. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
