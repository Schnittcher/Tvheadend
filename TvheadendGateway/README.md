# Tvheadend Gateway
Verbindet IP-Symcon mit einem Tvheadend-Server: Das Gateway fragt die JSON-API des Servers ab und empfängt dessen Live-Meldungen über den WebSocket. Alle anderen Tvheadend-Instanzen hängen an diesem Gateway und reden nur mit ihm. Es wird als Erstes angelegt, danach der Konfigurator.

## Inhaltsverzeichnis
- [Tvheadend Gateway](#tvheadend-gateway)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Das Gateway verbindet sich über einen **Client Socket** mit dem Server. Beim Anlegen schlägt IP-Symcon ihn vor, Host, Port und Verschlüsselung trägt das Gateway selbst dort ein. Der WebSocket-Client von IP-Symcon wird bewusst nicht verwendet: Er sendet jede Minute einen Ping, auf den Tvheadend nicht antwortet und danach keine Meldungen mehr schickt.

Feld | Beschreibung
------------ | ----------------
Host | Adresse des Tvheadend-Servers (Name oder IP-Adresse).
Port | Port des Webinterfaces, Standard 9981.
SSL verwenden | Verbindung per HTTPS und verschlüsseltem WebSocket.
Benutzername | Benutzer des Tvheadend-Webinterfaces. Für Status, Tuner, Netzwerke und Grabber braucht er Administratorrechte, sonst stehen diese Werte nicht zur Verfügung.
Passwort | Passwort des Benutzers.
Intervall für den Vollabgleich | Sekunden zwischen zwei Vollabgleichen aller Instanzen (Standard 300, mindestens 30). Zusätzlich gleicht das Gateway bei jedem Verbindungsaufbau ab.

Statuscodes der Instanz: 102 verbunden, 104 inaktiv, 201 Host fehlt, 202 Benutzername oder Passwort falsch, 203 Server nicht erreichbar.

Das Gateway prüft alle 15 Sekunden die Verbindung. Kommt 60 Sekunden lang nichts vom Server an (er sendet sonst alle 15 Sekunden), baut es den Socket neu auf.

## 2. Variablen

Das Gateway legt keine Variablen an.

## 3. Funktionen

`void TVHG_Sync(integer $InstanzID);`
Fragt Version, Rechte und Fähigkeiten des Servers ab und stößt bei allen Instanzen einen Abgleich an.

`string TVHG_Request(integer $InstanzID, string $Path, string $ParamsJSON, string $Method);`
Ruft eine Funktion der Tvheadend-API auf, zum Beispiel `api/serverinfo`. Die Parameter werden als JSON übergeben (Standard `{}`), die Methode ist `GET` oder `POST`. Die Antwort ist ein JSON-Text mit `code` (HTTP-Status), `data` (Antwort des Servers) und `error`. Damit lassen sich auch Funktionen nutzen, die keine eigene Instanz hat. Schreibende Aufrufe ändern den Server.

`void TVHG_CheckConnection(integer $InstanzID);`
Prüft die WebSocket-Verbindung und baut sie bei Stillstand neu auf. Läuft automatisch.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
