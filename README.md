# RRZE Calendar

## Wordpress-Plugin

Verwaltung und Darstellung von Veranstaltungen: 
* Erstellung und Verwaltung von Veranstaltungen direkt in WordPress
* Import und Veröffentlichung öffentlicher Veranstaltungen der FAU sowie anderer Quellen, die das iCalendar-Datenformat unterstützen.
* Einbindung in die Website als Block oder Shortcode

### Einstellungsmenü

Kalender › Einstellungen

## Shortcodes

### Termine-Shortcode

Zeigt eine Listenansicht der Termine an.

Attribute:

<pre>
kategorien   &mdash; Mehrere Kategorien (Titelform) werden durch Komma getrennt
schlagworte  &mdash; Mehrere Schlagworte (Titelform) werden durch Komma getrennt
anzahl       &mdash; Anzahl der Termineausgabe. Standardwert: 10
page_link    &mdash; ID einer Zielseite um z.B. weitere Termine anzuzeigen
</pre>

Beispiele:

<pre>
[termine kategorien="titelform1"]
[termine kategorien="titelform1,titelform2"]
[termine schlagworte="titelform3,titelform4"]
[termine kategorien="titelform1" anzahl=50]
</pre>

### Kalender-Shortcode

Zeigt eine Monatsansicht der Termine an.

Attribute:

<pre>
kategorien   &mdash; Mehrere Kategorien (Titelform) werden durch Komma getrennt.
schlagworte  &mdash; Mehrere Schlagworte (Titelform) werden durch Komma getrennt.
</pre>

Beispiele:

<pre>
[kalender kategorien="titelform1"]
[kalender kategorien="titelform1,titelform2"]
[kalender kategorien="titelform1,titelform2" schlagworte="titelform3,titelform4"]
</pre>

Zu achten ist auf die Schreibweise, Inhalte der Attribute in Anführungszeichen eingeschlossen. Mehrere Inhalte, bspw. Kategorien werden durch Komma getrennt.
Die Titelform einer Kategorie bzw. eines Schlagworts ist im Admin-Bereich unter Einstellungen › Kalendar › Kategorien bzw. Einstellungen › Kalendar › Schlagworte zu finden.
