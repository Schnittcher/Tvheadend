# Tvheadend Kanalverwaltung
Zeigt alle Kanäle des Servers nach Kanalnummer und lässt sie aktivieren, deaktivieren und umbenennen, im Konfigurationsformular, in einer Kachel für die Visualisierung und per Funktion. Die Instanz wird über den Konfigurator angelegt. Änderungen gehen direkt an den Tvheadend-Server.

## Inhaltsverzeichnis
- [Tvheadend Kanalverwaltung](#tvheadend-kanalverwaltung)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Kachel](#4-kachel)
  - [5. Spenden](#5-spenden)
  - [6. Lizenz](#6-lizenz)

## 1. Konfiguration

Im Konfigurationsformular steht die Liste "Kanäle":

Spalte | Beschreibung
------------ | ----------------
Aktiv | Haken direkt in der Tabelle schaltet den Kanal ein oder aus.
Nummer | Kanalnummer, nur Anzeige. Die Liste ist danach sortiert.
Name | Name des Kanals, im Bearbeiten-Dialog änderbar.
Tags | Kanal-Tags des Servers, nur Anzeige.

Deaktivierte Kanäle bleiben in der Liste und erscheinen abgeblendet.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Kanäle | `ChannelCount` | Ganzzahl | Anzahl aller Kanäle des Servers.
Aktive Kanäle | `EnabledCount` | Ganzzahl | Anzahl der aktivierten Kanäle.

Beide Variablen sind schreibgeschützt.

## 3. Funktionen

`void TVHK_Update(integer $InstanzID);`
Liest die Kanäle neu vom Server.

`string TVHK_GetChannels(integer $InstanzID);`
Alle Kanäle als JSON nach Kanalnummer: `uuid`, `number`, `name`, `enabled` und `tagNames`.

`boolean TVHK_SetChannelEnabled(integer $InstanzID, string $Channel, boolean $Enabled);`
Schaltet einen Kanal ein oder aus. `$Channel` ist die UUID oder der Name.

`boolean TVHK_RenameChannel(integer $InstanzID, string $Channel, string $NewName);`
Benennt einen Kanal um (UUID oder bisheriger Name).

## 4. Kachel

Die Instanz erscheint in der Kachel-Visualisierung als Liste aller Kanäle nach Kanalnummer: ein Schalter je Kanal zum Ein- und Ausschalten, ein Stift zum Umbenennen (Enter speichert, Esc bricht ab), die Tags des Kanals, Filter "Alle", "Aktiv", "Inaktiv" und eine Suche über Nummer, Name und Tags. Die maximierte Ansicht der Kachel gibt es ab IP-Symcon 9.1.

## 5. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
