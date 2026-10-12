# Tvheadend Streaming
Zeigt, wer gerade was schaut: laufende Streams und verbundene Clients des Servers, als Variablen, Tabellen und Kachel für die Visualisierung. Clients lassen sich trennen. Die Werte kommen live über den WebSocket. Die Instanz wird über den Konfigurator angelegt und braucht einen Benutzer mit Administratorrechten.

## Inhaltsverzeichnis
- [Tvheadend Streaming](#tvheadend-streaming)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Kachel](#4-kachel)
  - [5. Spenden](#5-spenden)
  - [6. Lizenz](#6-lizenz)

## 1. Konfiguration

Die Instanz hat keine Einstellungen. Der Server meldet laufende Streams im Sekundentakt, die Instanz fragt höchstens alle fünf Sekunden neu ab und holt nach dem Ende eines Streams den Endstand.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Aktive Streams | `ActiveStreams` | Ganzzahl | Anzahl der laufenden Streams (Subscriptions).
Verbundene Clients | `Clients` | Ganzzahl | Anzahl der verbundenen Clients.
Schaut gerade | `Watching` | Text | Kanal, Benutzer und Client aller laufenden Streams, durch Komma getrennt. Leer, wenn niemand schaut.
Streams | `StreamList` | Text (HTML) | Tabelle der Streams mit Kanal, Benutzer, Profil, Zustand, Start und Fehlern.
Clients | `ClientList` | Text (HTML) | Tabelle der Clients mit Typ, Benutzer, Adresse und Verbindungszeit.

Alle Variablen sind schreibgeschützt.

## 3. Funktionen

`void TVHM_Update(integer $InstanzID);`
Liest Streams und Clients neu vom Server.

`string TVHM_GetSessions(integer $InstanzID);`
Streams und Clients als JSON: `{"streams":[...],"clients":[...]}`. Jeder Eintrag hat eine `id`.

`boolean TVHM_CancelConnection(integer $InstanzID, string $ConnectionId);`
Trennt einen Client (`id` aus `TVHM_GetSessions`) oder mit `all` alle Clients. `all` braucht Tvheadend ab 4.3.1680.

## 4. Kachel

Die Instanz erscheint in der Kachel-Visualisierung mit den Reitern "Streams" und "Clients" samt Anzahl. Streams zeigen Kanal, Benutzer, Client, Profil, Zustand, Beginn und Fehler. Clients lassen sich mit "Trennen" nach Rückfrage trennen. Die maximierte Ansicht der Kachel gibt es ab IP-Symcon 9.1.

## 5. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
