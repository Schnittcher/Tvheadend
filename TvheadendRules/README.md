# Tvheadend Aufnahmeregeln
Verwaltet alle Serien- und Zeitaufnahmen des Servers in einer Instanz: anlegen, bearbeiten, ein- und ausschalten und löschen, im Konfigurationsformular, in einer Kachel für die Visualisierung und per Funktion. Die Instanz wird über den Konfigurator angelegt. Änderungen gehen direkt an den Tvheadend-Server.

## Inhaltsverzeichnis
- [Tvheadend Aufnahmeregeln](#tvheadend-aufnahmeregeln)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Kachel](#4-kachel)
  - [5. Spenden](#5-spenden)
  - [6. Lizenz](#6-lizenz)

## 1. Konfiguration

Die Instanz hat keine festen Einstellungen. Im Konfigurationsformular steht die Liste "Aufnahmeregeln" mit allen Regeln des Servers:

Spalte | Beschreibung
------------ | ----------------
Aktiv | Haken direkt in der Tabelle schaltet die Regel ein oder aus.
Typ | Serienaufnahme oder Zeitaufnahme.
Name, Titel, Kanal, Wochentage, Start nach | Die wichtigsten Werte der Regel.

"Hinzufügen" und "Bearbeiten" öffnen einen Dialog, "Löschen" entfernt die Regel auf dem Server. Bereits geplante Aufnahmen einer gelöschten Regel bleiben bestehen.

Feld im Dialog | Beschreibung
------------ | ----------------
Typ | Serien- oder Zeitaufnahme, nur beim Anlegen wählbar.
Aktiv, Name | Ein- und Ausschalten und Bezeichnung der Regel.
Titel (regulärer Ausdruck) | Bei Serien ein regulärer Ausdruck, der auf den Titel der Sendung passt, bei Zeitaufnahmen der Titel der Aufnahme.
Volltextsuche | Nur Serien: Der Ausdruck wird auch in der Beschreibung gesucht.
Kanal | Beschränkt die Regel auf einen Kanal, "Alle Kanäle" ohne Beschränkung.
Start nach, Start vor | Nur Serien: `Any` oder eine Uhrzeit `HH:MM`.
Ende | Nur Zeitaufnahmen: Uhrzeit `HH:MM`.
Wochentage | An welchen Tagen die Regel gilt.
Aufnahmeprofil, Kommentar | Profil des Servers für die Aufnahmen und eine Bemerkung.

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------ | ------------ | ----------------
Aufnahmeregeln | `RuleCount` | Ganzzahl | Anzahl aller Serien- und Zeitaufnahmen.
Aktive Aufnahmeregeln | `ActiveCount` | Ganzzahl | Anzahl der eingeschalteten Regeln.
Aufnahmeregeln - Liste | `RuleList` | Text (HTML) | Tabelle mit Typ, Name, Kanal, Wochentagen und Aktiv-Zustand.

Alle Variablen sind schreibgeschützt.

## 3. Funktionen

`void TVHR_Update(integer $InstanzID);`
Liest die Regeln neu vom Server.

`string TVHR_GetRules(integer $InstanzID);`
Alle Regeln als JSON: `uuid`, `type` (`series` oder `time`), `name`, `title`, `channel`, `channelName`, `enabled`, `weekdays`, `start` und weitere Felder.

`boolean TVHR_SetRuleEnabled(integer $InstanzID, string $Rule, boolean $Enabled);`
Schaltet eine Regel ein oder aus. `$Rule` ist die UUID oder der Name der Regel.

`boolean TVHR_DeleteRule(integer $InstanzID, string $Rule);`
Löscht eine Regel (UUID oder Name). Bereits geplante Aufnahmen bleiben bestehen.

`boolean TVHR_CreateSeriesRule(integer $InstanzID, string $Title, string $Channel, string $Name);`
Legt eine Serienaufnahme an. `$Title` ist ein regulärer Ausdruck, `$Channel` ein Kanalname oder eine UUID (leer = alle Kanäle), `$Name` die Bezeichnung (leer = der Titel).

## 4. Kachel

Die Instanz erscheint in der Kachel-Visualisierung als Liste der Regeln: ein Schalter je Regel zum Ein- und Ausschalten, ein Papierkorb zum Löschen (mit Rückfrage), Filter "Alle", "Aktiv", "Inaktiv" und eine Suche. Ausgeschaltete Regeln sind abgeblendet. Die maximierte Ansicht der Kachel gibt es ab IP-Symcon 9.1.

## 5. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
