# Tvheadend Server
Zeigt den Zustand des Tvheadend-Servers: online, Version, Speicherplatz am Aufnahmepfad, die nächste geplante Aktivität und ob der Server in den Ruhezustand darf. Dazu kann die Instanz den Server per Wake-on-LAN aufwecken. Die Instanz wird über den Konfigurator angelegt.

## Inhaltsverzeichnis
- [Tvheadend Server](#tvheadend-server)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
MAC-Adresse (Wake-on-LAN) | MAC-Adresse des Servers, im Format `AA:BB:CC:DD:EE:FF`. Nötig für "Server aufwecken".
Broadcast-Adresse des Netzwerks | Broadcast-Adresse, an die das Wake-on-LAN-Paket geht, zum Beispiel `192.168.1.255`.
Kein Ruhezustand vor der nächsten Aktivität | Minuten. Steht eine Aktivität (Aufnahme, EPG-Abruf, Scan) näher als dieser Wert bevor, ist "Ruhezustand erlaubt" nein. Standard 15.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Online | `Online` | Ja/Nein | Der Server ist über die API erreichbar.
Version | `Version` | Text | Version des Tvheadend-Servers.
Freier Speicher | `DiskFree` | Fließkommazahl (GB) | Freier Platz am Aufnahmepfad, live über den WebSocket.
Belegter Speicher | `DiskUsed` | Fließkommazahl (GB) | Von Aufnahmen belegter Platz, wie ihn der Server meldet (kann 0 sein).
Gesamtspeicher | `DiskTotal` | Fließkommazahl (GB) | Größe des Datenträgers am Aufnahmepfad.
Nächste Aktivität | `NextActivity` | Datum/Uhrzeit | Zeitpunkt der nächsten Aufnahme, des nächsten EPG-Abrufs oder Mux-Scans (0 = nichts geplant). Braucht Tvheadend ab 4.3-2405 und Administratorrechte.
Ruhezustand erlaubt | `SleepAllowed` | Ja/Nein | Ja, wenn niemand streamt und die nächste Aktivität weit genug entfernt ist. Eignet sich als Bedingung, den Server herunterzufahren.

Alle Variablen sind schreibgeschützt.

## 3. Funktionen

`void TVHS_Update(integer $InstanzID);`
Liest die Werte neu vom Server.

`boolean TVHS_WakeUp(integer $InstanzID);`
Sendet ein Wake-on-LAN-Paket an den Server. Gibt `false` zurück, wenn MAC- oder Broadcast-Adresse fehlen oder ungültig sind.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
