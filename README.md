# [![Aktuelle Version](https://img.shields.io/github/package-json/v/rrze-webteam/rrze-calendar/main?label=Version)](https://github.com/RRZE-Webteam/rrze-calendar)
[![Release Version](https://img.shields.io/github/v/release/rrze-webteam/rrze-calendar?label=Release+Version)](https://github.com/rrze-webteam/rrze-calendar/releases/)
[![GitHub License](https://img.shields.io/github/license/rrze-webteam/rrze-calendar)](https://github.com/RRZE-Webteam/rrze-calendar)
[![GitHub issues](https://img.shields.io/github/issues/RRZE-Webteam/rrze-calendar)](https://github.com/RRZE-Webteam/rrze-calendar/issues)

# RRZE Calendar

Verwaltung und Darstellung von Veranstaltungen: 
* Erstellung und Verwaltung von Veranstaltungen direkt in WordPress
* Import und Veröffentlichung öffentlicher Veranstaltungen der FAU sowie anderer Quellen, die das iCalendar-Datenformat unterstützen.
* Einbindung in die Website als Block oder Shortcode



## Contributors

* RRZE-Webteam, http://www.rrze.fau.de 

## Copyright

GNU General Public License (GPL) Version 3


## Feedback

* https://github.com/RRZE-Webteam/rrze-calendar/issues
* webmaster@rrze.fau.de

## Documentation

See documenation at https://www.wp.rrze.fau.de


### Einstellungsmenü

Kalender › Einstellungen

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


## Änderungen in 2.5.12

- Bei redaktionell angelegten Ganztagsterminen genügen im Editor das Startdatum
  und der letzte Veranstaltungstag. Die Uhrzeitfelder werden ausgeblendet.
- Der ICS-Export gibt diese Termine als Kalendertage aus. Ein Termin vom 7. bis
  einschließlich 9. April belegt beim Import genau diese drei Tage. Das gilt
  auch für bereits gespeicherte Termine beim nächsten Download.

## Änderungen in 2.5.11

- Der ICS-Export erhält bei wiederkehrenden Terminen die lokale Zeitzone.
  Beispielsweise bleibt eine Berliner Serie um 10:00 Uhr auch nach der
  Zeitumstellung bei 10:00 Uhr. Die benötigten `VTIMEZONE`-Komponenten werden
  aus der PHP-Zeitzonendatenbank erzeugt.
- Bei importierten Serien wird die ursprüngliche Zeitzone aus den gespeicherten
  Feed-Ereignissen verwendet, einschließlich Windows-Zeitzonen und explizitem UTC.
  Sind diese Quelldaten bei älteren Importen nicht mehr vorhanden, verwendet der
  Export die im Import gespeicherte Anzeigezeitzone, ersatzweise die WordPress-Zeitzone.
- Ausnahmen und Zusatztermine behalten ihre zum jeweiligen Datum passende
  Uhrzeit. Native Datumslisten unterstützen Zeilenumbrüche und ältere Kommalisten;
  importierte Listen unterstützen UTC sowie wiederholte Zeitzonenangaben.
- Version: **2.5.11**. Es werden keine gespeicherten Termine migriert. Import,
  Backend, Shortcodes und Blöcke verwenden weiterhin ihre bisherigen Daten.
  Einzeltermine und bestehende Ganztagsexporte behalten ihre UTC-Darstellung.

Die exportierten Zeitzonendefinitionen enthalten die bekannten Umstellungen
für mindestens die nächsten 100 Jahre und darüber hinaus bis zu einem expliziten
`UNTIL` bzw. Zusatztermin. Bei unbegrenzten Abonnements sollten Kalenderclients den
Feed regelmäßig aktualisieren, auch um spätere gesetzliche Zeitzonenänderungen
zu übernehmen.

### Export-Regressionstests

`composer test` (alternativ `php tests/ics-export.php`) prüft den echten Exporter,
den ICS-Builder und die mitgelieferten Parser ohne zusätzliche Testabhängigkeiten.
WordPress-Metadaten und Textfunktionen werden durch kontrollierte Fixtures ersetzt.
Die Tests benötigen weder Datenbank noch Netzwerk und verändern keine Termine.

Geprüft werden Sommer-/Winterzeit, monatliche/wöchentliche Serien, Quellzeitzonen,
UTC, Windows-Zeitzonen, ältere Importmetadaten, Ausnahmen/Zusatztermine, Einzel- und
mehrtägige Termine, Ganztagsexporte, fehlende Endzeiten, feste UTC-Offsets,
`VTIMEZONE`-Übergänge und der Abgleich mit der Serienberechnung der Kalenderansicht.
Die Tests ersetzen keinen vollständigen WordPress- oder Kalenderclient-Test.
