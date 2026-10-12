# Tvheadend Netzwerk
Zeigt ein Netzwerk des Servers (zum Beispiel DVB-S, DVB-C oder IPTV): wie viele Muxe es hat und wie viele davon beim Scan funktionierten, Services, zugeordnete Kanäle und den Stand eines Scans. Ein Scan lässt sich per Funktion starten. Je Netzwerk gibt es eine Instanz, der Konfigurator legt sie an. Die Instanz braucht einen Benutzer mit Administratorrechten.

## Inhaltsverzeichnis
- [Tvheadend Netzwerk](#tvheadend-netzwerk)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
UUID des Netzwerks | Kennung des Netzwerks auf dem Server. Der Konfigurator trägt sie ein.
Abfrageintervall | Sekunden zwischen zwei Abfragen, Standard 300, mindestens 30, 0 schaltet die Abfrage ab. Muxe und Services ändern sich selten, ein Scan zeigt sich in der Warteschlange.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Aktiv | `Enabled` | Ja/Nein | Das Netzwerk ist im Server eingeschaltet.
Muxe | `Muxes` | Ganzzahl | Anzahl der Muxe des Netzwerks.
Muxe - Scan ok | `MuxesOk` | Ganzzahl | Muxe, deren letzter Scan funktioniert hat.
Muxe - Scan fehlgeschlagen | `MuxesFailed` | Ganzzahl | Muxe, deren letzter Scan fehlgeschlagen ist.
Services | `Services` | Ganzzahl | Anzahl der gefundenen Services.
Zugeordnete Kanäle | `Channels` | Ganzzahl | Kanäle, die zu Services dieses Netzwerks gehören.
Scan-Warteschlange | `ScanQueue` | Ganzzahl | Muxe, die auf einen Scan warten.
Scan läuft | `Scanning` | Ja/Nein | Ein Scan ist in der Warteschlange oder läuft.
Letzter Scan | `LastScan` | Datum/Uhrzeit | Zeitpunkt des jüngsten Muxscans.

Alle Variablen sind schreibgeschützt.

## 3. Funktionen

`void TVHN_Update(integer $InstanzID);`
Liest das Netzwerk und seine Muxe neu vom Server.

`boolean TVHN_StartScan(integer $InstanzID);`
Startet einen Scan des Netzwerks. Die Muxe werden nacheinander abgefragt, das kann je nach Netzwerk lange dauern.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
