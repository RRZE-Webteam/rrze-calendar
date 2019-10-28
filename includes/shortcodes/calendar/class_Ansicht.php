<?php

use \RRZE\Calendar\Util;

abstract class Ansicht
{
    protected $optionen = null;
    private $tag_start = null;
    private $tag_ende = null;

    // Gibt den Dateiname des Templates zurueck.
    abstract protected function template_name();

    // Funktion soll fuer jede Ansicht und Datum die anzuzeigenden Tage als Array zurueckgeben.
    abstract protected function lade_tage($datum = '');

    // Rendert das Template mit den uebergebenen Daten.
    // Falls noetig werden die Daten fuer die jeweilige Anischt noch formatiert.
    abstract protected function rendere_daten($daten = '');

    abstract protected function datum_vor($datum = '');

    abstract protected function datum_zurueck($datum = '');

    public function __construct($optionen = null)
    {
        if ($optionen) {
            $this->optionen = $optionen;
        } else {
            return;
        }
    }

    public function suche_events($tag = null)
    {
        if (empty($tag)) {
            $tag = date('Y-m-d');
        }

        $this->tag_start = strtotime($tag);
        $this->tag_ende = strtotime($tag . ' + 1 day');
        $events = RRZE_Calendar::getEventsBetween(date('Y-m-d H:i:s', $this->tag_start), date('Y-m-d H:i:s', $this->tag_ende), $this->optionen["filter"]);

        $events = Util::getCalendarDates($events);

        $events_data = [];

        foreach ($events as $e) {
            foreach ($e as $event) {
                $events_data[] = $this->event($event);
            }
        }

        $ts = strtotime($tag);
        $datum = date('j', $ts);

        $monat = date_i18n('F', $ts);

        $wochentag = str_split(date_i18n('l', $ts), 2);
        $wochentag_anfang = $wochentag[0];
        unset($wochentag[0]);
        $wochentag_ende = implode("", $wochentag);

        // Tageslaenge in Minuten
        $tag_laenge = ($this->tag_ende - $this->tag_start) / 60;
        $tag_anfang = date('H:i', $this->tag_start);

        return [
            "termine" => $events_data,
            "datum" => $tag,
            "monat" => $monat,
            "datum_kurz" => $datum,
            "wochentag_anfang" => $wochentag_anfang,
            "wochentag_ende" => $wochentag_ende,
            "heute" => $this->ist_heute($tag),
            "wochenende" => $this->ist_wochenende($tag),
            "sonntag" => $this->ist_sonntag($tag),
            "tag_laenge" => $tag_laenge,
            "tag_anfang" => $tag_anfang,
        ];
    }

    private function event($event)
    {
        $event_data = array();

        $event_data["id"] = $event->id;
        $event_data["slug"] = $event->slug;
        $event_data["summary"] = $event->summary;
        $event_data["location"] = $event->location;

        $start = Util::gmtToLocal(strtotime($event->start));
        $ende = Util::gmtToLocal(strtotime($event->end));

        $event_data["datum_start"] = date(__('d.m.Y', 'rrze-calendar'), $start);
        $event_data["datum_ende"] = date(__('d.m.Y', 'rrze-calendar'), $ende);

        // Zeitanzeige am Termin
        $event_data["time_start"] = date(__('H:i', 'rrze-calendar'), $start);
        $event_data["time_ende"] = date(__('H:i', 'rrze-calendar'), $ende);
        $event_data["time"] = sprintf("%s - %s", $event_data["time_start"], $event_data["time_ende"]);

        $event_data["datum"] = date(__('d.m.Y', 'rrze-calendar'), $start);
        $event_data["start_timestamp"] = $start;

        // Ganztagige Events rausfiltern
        if ($event->allday) {
            $event_data["ganztagig"] = true;
            $event_data["datum"] = date(__('d.m.Y', 'rrze-calendar'), $start);
        } else {
            $event_data["nicht_ganztagig"] = true;
        }
        
        // Farbmarkierung
        $event_data["farbe"] = isset($event->category->color) ? $event->category->color : 'grey';

        $event_data["allday"] = $event->allday;
        $event_data["multiday"] = $event->is_multiday();
        $event_data["long_start_date"] = $event->long_start_date;
        $event_data["long_end_date"] = $event->long_end_date;
        $event_data["short_start_time"] = $event->short_start_time;
        $event_data["short_end_time"] = $event->short_end_time;
        $event_data["short_start_time"] = $event->short_start_time;
        $event_data["short_end_time"] = $event->short_end_time;
        $event_data["long_start_date"] = $event->long_start_date;
        $event_data["long_end_date"] = $event->long_end_date;
        
        return $event_data;
    }

    protected function ist_heute($datum = '')
    {
        if ($datum == "") {
            return false;
        }

        return (date("Y-m-d") === $datum);
    }

    protected function ist_wochenende($datum = '')
    {
        if ($datum == "") {
            return false;
        }

        return (date("N", strtotime($datum)) >= 6);
    }

    protected function ist_sonntag($datum = '')
    {
        if ($datum == "") {
            return false;
        }

        return (date("N", strtotime($datum)) == 7);
    }

    protected function ist_im_monat($datum = '', $monat = '')
    {
        if ($datum == "") {
            return false;
        }

        return (date("m", strtotime($datum)) === $monat);
    }

    protected function datum_aktuell($datum = '')
    {
        // Datum kann ein Tag in der aktuellen Woche.
        if (!$datum || $datum === '') {
            $datum = date("Ymd");
        }

        return date("Y-m-d", strtotime($datum));
    }

    protected function rendere_template($daten)
    {
        wp_enqueue_style('rrze-calendar-shortcode-calendar');
        wp_enqueue_style('rrze-calendar-shortcode-calendar-titip');
        wp_enqueue_script('rrze-calendar-' . $this->template_name());

        // Aktuellen Dateinamen fuer korrekte Verlinkungen mit in Template Daten einfuegen
        $daten["dateiname"] = '';

        ob_start();
        include plugin_dir_path(__FILE__) . $this->template_name() . '.php';
        $template = ob_get_contents();
        @ob_end_clean();

        $template = preg_replace('/([\r\n\t])/', ' ', $template);
        $template = trim(preg_replace('/\s+/', ' ', $template));

        return $template;
    }
}
