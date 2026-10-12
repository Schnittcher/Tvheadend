[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-9.0%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Schnittcher/Tvheadend/actions/workflows/style.yml/badge.svg)](https://github.com/Schnittcher/Tvheadend/actions/workflows/style.yml)

# Tvheadend
Bindet einen Tvheadend-Server (TV-Server und Videorekorder) in IP-Symcon ein: Aufnahmen, Programm, Tuner, Streams, Netzwerke und Kanäle als Variablen, Funktionen und Kacheln für die Visualisierung, live über den WebSocket des Servers.

## Inhaltsverzeichnis
- [Tvheadend](#tvheadend)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Voraussetzungen](#1-voraussetzungen)
  - [2. Funktionsumfang](#2-funktionsumfang)
  - [3. Enthaltene Module](#3-enthaltene-module)
  - [4. Installation](#4-installation)
  - [5. Konfiguration in IP-Symcon](#5-konfiguration-in-ip-symcon)
  - [6. Spenden](#6-spenden)
  - [7. Lizenz](#7-lizenz)

## 1. Voraussetzungen

* mindestens IPS Version 9.0 (die maximierte Ansicht der Kacheln gibt es ab 9.1)
* Tvheadend 4.3 oder neuer, erreichbar über sein Webinterface (Standard-Port 9981)
* ein Benutzer des Tvheadend-Webinterfaces. Für Status, Tuner, Netzwerke, Streams und EPG-Grabber braucht er Administratorrechte, sonst stehen diese Werte nicht zur Verfügung.

## 2. Funktionsumfang
* Zustand des Servers: erreichbar, Version, Speicherplatz, nächste Aktivität, Ruhezustand, Wake-on-LAN
* Aufnahmen: geplante, laufende, fertige und fehlgeschlagene, Aufnahmen anlegen, stoppen und löschen
* Serien- und Zeitaufnahmen anlegen, bearbeiten, ein- und ausschalten und löschen
* Programm: was läuft gerade und was kommt als Nächstes, Suche, Aufnahme direkt aus dem Programm
* Tuner mit Belegung, Signal und SNR, laufende Streams und verbundene Clients
* Netzwerke, Muxe und Scans, Kanalverwaltung, Probleme im Serverlog und EPG-Grabber
* Kacheln für die Kachel-Visualisierung: Programmführer, Aufnahmen, Aufnahmeregeln, Tuner, Streaming und Kanäle
* Live-Daten: Der Server meldet Änderungen über den WebSocket, die Instanzen aktualisieren sich ohne Wartezeit

Die einzelnen Funktionen stehen bei den Instanzen unter "Enthaltene Module" und in der README des jeweiligen Moduls.

## 3. Enthaltene Module

* [Tvheadend Gateway](TvheadendGateway/README.md)
  * Verbindet IP-Symcon mit dem Server: HTTP-Abfragen der JSON-API und Live-Meldungen über den WebSocket. Alle anderen Instanzen hängen daran.
  * Spricht den WebSocket selbst über einen Client Socket und baut die Verbindung bei Stillstand neu auf.
* [Tvheadend Konfigurator](TvheadendConfigurator/README.md)
  * Legt die übrigen Instanzen an und zeigt Netzwerke des Servers.
* [Tvheadend Server](TvheadendServer/README.md)
  * Zeigt Online-Zustand, Version, Speicherplatz, nächste Aktivität und ob der Server in den Ruhezustand darf. Weckt den Server per Wake-on-LAN.
* [Tvheadend DVR](TvheadendDVR/README.md)
  * Zeigt geplante, laufende, fertige und fehlgeschlagene Aufnahmen, mit Kachel zum Stoppen und Löschen und Funktionen zum Anlegen.
* [Tvheadend EPG](TvheadendEPG/README.md)
  * Zeigt für ausgewählte Kanäle, was läuft und was kommt, sucht im Programm, nimmt Sendungen auf und liefert Stream-Adressen.
* [Tvheadend EPG-Kachel](TvheadendEPGTile/README.md)
  * Programmführer für die Visualisierung als Zeitleiste oder Liste nach Kanalnummer, mit Aufnehmen, Serie aufnehmen und Löschen.
* [Tvheadend Aufnahmeregeln](TvheadendRules/README.md)
  * Verwaltet alle Serien- und Zeitaufnahmen im Formular, in einer Kachel und per Funktion.
* [Tvheadend Tuner](TvheadendTuner/README.md)
  * Alle Tuner in einer Instanz mit Belegung, Signal, SNR, Bitrate, Variablen je Tuner und Listen-Kachel. Nur Anzeige.
* [Tvheadend Streaming](TvheadendStreaming/README.md)
  * Zeigt laufende Streams und verbundene Clients, trennt Clients, mit Kachel.
* [Tvheadend Netzwerk](TvheadendNetwork/README.md)
  * Zeigt ein Netzwerk des Servers mit Muxen, Services und Scan-Stand und startet Scans. Eine Instanz je Netzwerk.
* [Tvheadend Diagnose](TvheadendDiagnostics/README.md)
  * Sammelt auffällige Zeilen aus dem Serverlog, zeigt EPG-Grabber und nächste Abrufe und stößt EPG-Abrufe an.
* [Tvheadend Kanalverwaltung](TvheadendChannels/README.md)
  * Zeigt alle Kanäle nach Kanalnummer, schaltet sie ein und aus und benennt sie um, im Formular, in einer Kachel und per Funktion.
* Tvheadend (bisheriges Modul)
  * Das bisherige Modul der Bibliothek bleibt unverändert erhalten, damit bestehende Installationen weiterlaufen. Neue Installationen nutzen die Module oben.

## 4. Installation
Die Installation erfolgt über den IP-Symcon Module Store.

## 5. Konfiguration in IP-Symcon

1. Eine Instanz **Tvheadend Gateway** anlegen. IP-Symcon schlägt dabei einen Client Socket vor, den das Gateway selbst einrichtet. Host, Port, Benutzername und Passwort des Tvheadend-Webinterfaces eintragen. Der Status wechselt auf "verbunden".
2. Eine Instanz **Tvheadend Konfigurator** anlegen. Er hängt am Gateway.
3. Im Konfigurator die gewünschten Instanzen mit "Erstellen" anlegen: Server, DVR, EPG, Tuner und weitere. Die Netzwerke des Servers stehen in einer eigenen Gruppe.
4. Für die Kachel-Visualisierung die Instanzen mit Kachel (EPG-Kachel, DVR, Aufnahmeregeln, Tuner, Streaming, Kanalverwaltung) in der Visualisierung auf eine Seite legen. Ihre Größe stellt man dort im Bearbeitungsmodus ein.

Zugangsdaten stehen nur im Gateway. Alle anderen Instanzen reden nie direkt mit dem Server.

## 6. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 7. Lizenz

IPS-Module:
[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)

Das bisherige Modul verwendet phpseclib von Jim Wigginton <terrafrost@php.net>:
[MIT License](http://www.opensource.org/licenses/mit-license.html)
