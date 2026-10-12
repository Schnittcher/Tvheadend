# Tvheadend Tuner
Zeigt alle Tuner des Servers in einer Instanz: wie viele es gibt, welche belegt sind, mit Signal, SNR und Bitrate, als Variablen je Tuner, als Tabelle und als Listen-Kachel für die Visualisierung. Die Werte kommen live über den WebSocket. Die Instanz zeigt nur an: Tuner aktiviert und deaktiviert man im Tvheadend (Konfiguration, DVB-Eingänge, TV-Adapter). Sie wird über den Konfigurator angelegt.

## Inhaltsverzeichnis
- [Tvheadend Tuner](#tvheadend-tuner)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Kachel](#4-kachel)
  - [5. Spenden](#5-spenden)
  - [6. Lizenz](#6-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Variablen für jeden Tuner anlegen | Legt je Tuner fünf Variablen an (für Archiv und Ereignisse). Abgeschaltet bleiben nur Zusammenfassung, Tabelle und Kachel, die Variablen je Tuner werden entfernt. Standard: an.
Abfrageintervall | Sekunden zwischen zwei Abfragen von Hardware und Tunern, Standard 60, mindestens 10, 0 schaltet die Abfrage ab. Belegte Tuner meldet der Server zusätzlich jede Sekunde selbst.

Die Tuner liest die Instanz aus dem Hardwarebaum des Servers (Administratorrechte nötig). Tuner, die der Server nicht aufführt, gelten als frei.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Tuner | `TunerCount` | Ganzzahl | Anzahl aller Tuner des Servers.
Aktive Tuner | `EnabledCount` | Ganzzahl | Anzahl der im Server aktivierten Tuner.
Belegte Tuner | `BusyCount` | Ganzzahl | Anzahl der Tuner, die gerade einen Stream oder eine Aufnahme bedienen.
Freie Tuner | `FreeCount` | Ganzzahl | Aktive Tuner, die gerade frei sind.
Tunerliste | `TunerList` | Text (HTML) | Tabelle aller Tuner mit Zustand, Signal, SNR, Bitrate und Mux.

Je Tuner, wenn "Variablen für jeden Tuner anlegen" aktiv ist (die Kennung `T…` besteht aus zehn Zeichen und hängt am Namen des Tuners):

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
&lt;Tuner&gt; - Aktiv | `T<Kennung>_Enabled` | Ja/Nein | Der Tuner ist im Server aktiviert (nur Anzeige).
&lt;Tuner&gt; - Belegt | `T<Kennung>_Busy` | Ja/Nein | Der Tuner bedient gerade einen Stream oder eine Aufnahme.
&lt;Tuner&gt; - Signal | `T<Kennung>_Signal` | Fließkommazahl (% oder dB) | Signalstärke. Die Einheit folgt der Skala, die der Server liefert.
&lt;Tuner&gt; - SNR | `T<Kennung>_Snr` | Fließkommazahl (% oder dB) | Signal-Rausch-Verhältnis.
&lt;Tuner&gt; - Bitrate | `T<Kennung>_Bitrate` | Ganzzahl (kbit/s) | Aktuelle Datenrate, 0 im Leerlauf.

Alle Variablen sind schreibgeschützt. Signal und SNR behalten ihren letzten Wert, wenn der Tuner frei wird.

## 3. Funktionen

`void TVHT_Update(integer $InstanzID);`
Liest Tuner und ihren Zustand neu vom Server.

`string TVHT_GetTuners(integer $InstanzID);`
Alle Tuner als JSON: Name, UUID, Aktiv-Zustand, Belegung, Mux, Bitrate, Signal und SNR mit Skala.

## 4. Kachel

Die Instanz erscheint in der Kachel-Visualisierung als Liste mit einer Zeile je Tuner: grüner Punkt bei Belegung, Mux, Anzahl der Streams, Bitrate und Balken für Signal und SNR. Freie Tuner sind abgeblendet, deaktivierte tragen den Hinweis "Deaktiviert". Dazu gibt es die Filter "Alle", "Belegt" und "Frei" und eine Suche. Die maximierte Ansicht der Kachel gibt es ab IP-Symcon 9.1.

## 5. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
