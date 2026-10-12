# Tvheadend EPG-Kachel
Programmführer für die Kachel-Visualisierung: Alle Kanäle nach Kanalnummer, als Zeitleiste oder als Liste "Jetzt und als Nächstes", mit Detailfenster und der Möglichkeit, Sendungen aufzunehmen. Die Instanz wird über den Konfigurator angelegt und erscheint in der Visualisierung als Kachel.

## Inhaltsverzeichnis
- [Tvheadend EPG-Kachel](#tvheadend-epg-kachel)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Bedienung der Kachel](#4-bedienung-der-kachel)
  - [5. Spenden](#5-spenden)
  - [6. Lizenz](#6-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Standardansicht | Ansicht beim ersten Öffnen: Zeitleiste oder Liste. Die zuletzt gewählte Ansicht merkt sich die Kachel im Browser.
Sichtbare Stunden in der Zeitleiste | Zeitfenster der Zeitleiste, 1 bis 12 Stunden, Standard 3.

## 2. Variablen

Die Instanz legt keine Variablen an.

## 3. Funktionen

Die Instanz hat keine Funktionen für Skripte. Suche und Stream-Adressen bietet die Instanz "Tvheadend EPG".

## 4. Bedienung der Kachel

* **Zeitleiste:** Kanäle als Zeilen, Sendungen als Balken in ihrer Länge, rote Linie für "jetzt". Mit den Pfeilen blättert man in halben Zeitfenstern, "Jetzt" springt zurück.
* **Liste:** Pro Kanal die laufende Sendung mit Fortschrittsbalken und darunter die nächste.
* **Detailfenster:** Antippen einer Sendung zeigt Titel, Untertitel, Kanal, Zeit, Beschreibung und HD-Kennzeichen.
* **Aufnehmen:** "Aufnehmen" legt die Sendung als Timer an, "Serie aufnehmen" eine Serienaufnahme. Bei mehreren Aufnahmeprofilen lässt sich eines wählen. Eine geplante Aufnahme kann gelöscht werden, eine laufende gestoppt. Balken sind orange (geplant), rot (läuft) oder grün (aufgenommen).
* **Daten:** Die Kachel lädt nur die sichtbaren Kanäle und aktualisiert sich bei Änderungen im Programm und bei Aufnahmen selbst.
* **Logos:** Die Kachel zeigt Senderlogos, wenn der Server sie über seinen Image Cache ausliefert. Dafür muss der Image Cache im Tvheadend aktiviert sein und die Logo-Dateien (Picons) müssen für den Server erreichbar sein. Ohne Logos zeigt die Kachel Nummer und Name.
* Die maximierte Ansicht der Kachel gibt es ab IP-Symcon 9.1.

## 5. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
