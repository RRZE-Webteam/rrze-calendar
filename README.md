RRZE Calendar
=============

Wordpress-Plugin
----------------

Import und Ausgabe der öffentlicher ICS-Veranstaltungen der FAU.

### Einstellungsmenü

Einstellungen › Kalender

### Shortcodes

Termine-Shortcode
------------------
Erzeugt eine Listeansicht der Termine.

Attribute:
<pre>
kategorien          Mehrere Kategorien (Titelform) werden durch Komma getrennt.
schlagworte         Mehrere Schlagworte (Titelform) werden durch Komma getrennt.
anzahl              Anzahl der Termineausgabe. Standardwert: 10.
abonnement_link     Abonnement-Link anzeigen (1 oder 0).
</pre>

Beispiele:
<pre>
[rrze-termine kategorien="titelform1"]
[rrze-termine kategorien="titelform1, titelform2"]
[rrze-termine kategorien="titelform1, titelform2" schlagworte="titelform3, titelform4"]
[rrze-termine kategorien="titelform1" anzahl=50 abonnement_link=1]
</pre>

Kalender-Shortcode
------------------
Kalenderdarstellung der Termine.

Attribute: 
<pre>
kategorien          Mehrere Kategorien (Titelform) werden durch Komma getrennt.
schlagworte         Mehrere Schlagworte (Titelform) werden durch Komma getrennt.
anzahl              Anzahl der Termine in der Listenansicht. Standardwert: 10.
tagesanfang         Format: "SS:MM". Standardwert: "07:00".
tagesende           Format: "SS:MM". Standardwert: "21:00".
ansicht             "tag", "woche", "monat" oder "liste". Standardwert: "monat".
abonnement_link     Abonnement-Link anzeigen (1 oder 0).
</pre>

Beispiele:
<pre>
[rrze-kalender kategorien="titelform1"]
[rrze-kalender kategorien="titelform1, titelform2"]
[rrze-kalender kategorien="titelform1, titelform2" schlagworte="titelform3, titelform4"]
[rrze-kalender kategorien="titelform1" tagesanfang="08:00" tagesende="18:00" abonnement_link=1 ansicht="liste"]
</pre>

Zu achten ist auf die Schreibweise, Inhalte der Attribute in Anführungszeichen eingeschlossen. Mehrere Inhalte, bspw. Kategorien werden durch Komma getrennt.
Die Titelform einer Kategorie bzw. eines Schlagworts ist im Admin-Bereich unter Einstellungen/Kalendar/Kategorien bzw. Einstellungen/Kalendar/Schlagworte zu finden.