# Tvheadend Diagnose
Sammelt auffällige Zeilen aus dem Log des Servers (live über den WebSocket) und zeigt den Stand der EPG-Grabber samt ihrer nächsten Abrufe. Per Funktion lassen sich EPG-Abrufe anstoßen. Die Instanz wird über den Konfigurator angelegt und braucht einen Benutzer mit Administratorrechten.

## Inhaltsverzeichnis
- [Tvheadend Diagnose](#tvheadend-diagnose)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Der Server meldet seine Logzeilen ohne Angabe der Schwere. Die Instanz merkt sich deshalb Zeilen, die eines der Stichwörter enthalten.

Feld | Beschreibung
------------ | ----------------
Stichwörter für Problemzeilen | Durch Komma getrennt, ohne Beachtung der Groß- und Kleinschreibung. Standard: `error,failed,fail,unable,cannot,timeout,invalid,warning`.
Zu behaltende Problemzeilen | Wie viele Zeilen die Instanz aufbewahrt, 10 bis 1000, Standard 100.
Jede Logzeile in die Debug-Ausgabe schreiben | Zum Prüfen: Alle Logzeilen erscheinen in der Debug-Ausgabe der Instanz.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Problemzeilen | `ProblemCount` | Ganzzahl | Anzahl der gemerkten Problemzeilen.
Letztes Problem | `LastProblem` | Text | Die jüngste Problemzeile.
Letztes Problem - Zeit | `LastProblemTime` | Datum/Uhrzeit | Zeitpunkt der jüngsten Problemzeile.
Probleme | `ProblemList` | Text (HTML) | Tabelle der letzten 20 Problemzeilen, neueste oben.
EPG-Grabber | `Grabbers` | Text (HTML) | Die eingeschalteten EPG-Grabber des Servers.
Nächster EPG-Abruf über die Luft | `NextOtaGrab` | Datum/Uhrzeit | Zeitpunkt des nächsten Abrufs der EPG-Daten aus dem Sendesignal (0 = keiner geplant).
Nächster interner EPG-Abruf | `NextInternalGrab` | Datum/Uhrzeit | Zeitpunkt des nächsten Abrufs der internen Grabber, zum Beispiel XMLTV.
Nächster Mux-Scan | `NextMuxScan` | Datum/Uhrzeit | Zeitpunkt des nächsten geplanten Mux-Scans.

Alle Variablen sind schreibgeschützt. Die Problemzeilen gehen beim Neustart von IP-Symcon nicht verloren.

## 3. Funktionen

`void TVHX_Update(integer $InstanzID);`
Liest EPG-Grabber und nächste Abrufe neu vom Server.

`string TVHX_GetProblems(integer $InstanzID);`
Die gemerkten Problemzeilen als JSON, neueste zuerst: `[{"t": Zeitstempel, "m": "Text"}, ...]`.

`void TVHX_ClearProblems(integer $InstanzID);`
Löscht die gemerkten Problemzeilen und setzt die Zähler zurück.

`boolean TVHX_TriggerOtaGrab(integer $InstanzID, integer $DelaySeconds);`
Startet den EPG-Abruf aus dem Sendesignal nach `$DelaySeconds` Sekunden (1 Sekunde bis eine Woche, Standard 1).

`boolean TVHX_RerunInternalGrabbers(integer $InstanzID);`
Startet die internen EPG-Grabber (zum Beispiel XMLTV) sofort.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
