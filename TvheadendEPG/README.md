# Tvheadend EPG
Zeigt, was auf den ausgewählten Kanälen gerade läuft und was als Nächstes kommt, und sucht im Programm. Für die gewählten Kanäle entstehen Variablen, die sich für Ereignisse eignen ("Sendung X beginnt"). Per Funktion lassen sich Sendungen suchen, aufnehmen und die Stream-Adresse eines Kanals holen. Die Instanz wird über den Konfigurator angelegt.

## Inhaltsverzeichnis
- [Tvheadend EPG](#tvheadend-epg)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Beobachtete Kanäle | Liste der Kanäle, für die Variablen angelegt werden. Die Auswahl zeigt alle Kanäle des Servers. Entfernte Kanäle verlieren ihre Variablen.

Die Instanz lädt "Läuft jetzt" und "Als Nächstes" neu, sobald die laufende Sendung endet, bei Änderungen im Programm und mindestens alle zehn Minuten.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Überblick: Jetzt im Programm | `Overview` | Text (HTML) | Tabelle aller beobachteten Kanäle mit der laufenden und der nächsten Sendung.
&lt;Kanal&gt; - Läuft jetzt | `Now_<Kennung>` | Text | Titel der laufenden Sendung.
&lt;Kanal&gt; - Läuft jetzt bis | `NowEnd_<Kennung>` | Datum/Uhrzeit | Ende der laufenden Sendung.
&lt;Kanal&gt; - Als Nächstes | `Next_<Kennung>` | Text | Titel der folgenden Sendung.
&lt;Kanal&gt; - Als Nächstes ab | `NextStart_<Kennung>` | Datum/Uhrzeit | Beginn der folgenden Sendung.

Die vier letzten Variablen entstehen je beobachtetem Kanal. Die Kennung ist die UUID des Kanals ohne Sonderzeichen. Alle Variablen sind schreibgeschützt.

## 3. Funktionen

`void TVHE_Update(integer $InstanzID);`
Liest "Jetzt" und "Als Nächstes" der beobachteten Kanäle neu.

`string TVHE_Search(integer $InstanzID, string $Title, string $ParamsJSON);`
Sucht Sendungen, deren Titel `$Title` enthält. In `$ParamsJSON` stehen weitere Parameter von `epg/events/grid` als JSON, zum Beispiel `{"channel":"Das Erste HD","limit":20,"durationMin":1800}`. Die Antwort ist ein JSON-Text mit der Liste der Sendungen (`eventId`, `title`, `start`, `stop`, `channelName`, `dvrState`, ...).

`string TVHE_GetNow(integer $InstanzID, string $Channel);`
Die laufende Sendung eines Kanals (Name oder UUID) als JSON, `{}`, wenn nichts gefunden wurde.

`string TVHE_GetStreamUrl(integer $InstanzID, string $Channel);`
Stream-Adresse des Kanals (Name oder UUID). Die Adresse enthält keine Zugangsdaten, der Player muss sich am Server anmelden.

`boolean TVHE_RecordEvent(integer $InstanzID, integer $EventId, string $ConfigUuid);`
Legt eine Aufnahme für eine Sendung an (`eventId` aus `TVHE_Search`). Ohne `ConfigUuid` gilt das Standardprofil des Servers.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
