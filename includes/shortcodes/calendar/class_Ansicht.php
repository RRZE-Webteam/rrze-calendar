<?php

abstract class Ansicht {

    protected $optionen = NULL;
    private $tag_start = NULL;
    private $tag_ende = NULL;

    // Gibt den Dateiname des Templates zurueck.
    abstract protected function template_name();

    // Funktion soll fuer jede Ansicht und Datum die anzuzeigenden Tage als Array zurueckgeben.
    abstract protected function lade_tage($datum = '');

    // Rendert das Template mit den uebergebenen Daten.
    // Falls noetig werden die Daten fuer die jeweilige Anischt noch formatiert.
    abstract protected function rendere_daten($daten = '');

    abstract protected function datum_vor($datum = '');

    abstract protected function datum_zurueck($datum = '');

    public function __construct($optionen = NULL) {

        if ($optionen) {
            $this->optionen = $optionen;
        } else {
            return;
        }
    }

    public function suche_events($tag = NULL) {


        if (empty($tag)) {
            // Kein Tag angegeben (zB. bei Listenansicht) -> Suche nach allen Events.
            $events = RRZE_Calendar::get_events_relative_to(current_time('timestamp'), $this->optionen["anzahl"], 0, $this->optionen["filter"]);


            $events = RRZE_Calendar_Functions::get_calendar_dates($events['events']);
        } else {
            $start_time = strtotime($tag . '-00:00');
            $end_time = strtotime($tag . '-01:00 + 1 day');
            $events = RRZE_Calendar::get_events_between($start_time, $end_time, $this->optionen["filter"]);
            $events = RRZE_Calendar_Functions::get_calendar_dates($events);
        }

        $this->tag_start = $start_time;
        $this->tag_ende = $end_time;
        $events_data = array();

        foreach ($events as $e) {

            foreach ($e as $event) {


                $events_data[] = $this->event($event);
            }
        }


        $ts = strtotime($tag);
        $datum = date("j", $ts);

        $monat = date_i18n("F", $ts);

        $wochentag = str_split(date_i18n("l", $ts), 2);
        $wochentag_anfang = $wochentag[0];
        unset($wochentag[0]);
        $wochentag_ende = implode("", $wochentag);

        // Tageslaenge in Minuten        
        $tag_laenge = ($this->tag_ende - $this->tag_start) / 60;
        $tag_anfang = date('H:i', $this->tag_start);

        return array(
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
        );
    }

    private function event($event) {
        $event_data = array();

        $event_data["id"] = $event->id;
        $event_data["slug"] = $event->slug;
        $event_data["summary"] = $event->summary;
        $event_data["location"] = $event->location;
        if (isset($event->multi_day_event))
            $event_data["multi_day_event"] = $event->multi_day_event;

        $event_data["datum_start"] = date(__('d.m.Y', 'rrze-calendar'), $event->start);
        $event_data["datum_ende"] = date(__('d.m.Y', 'rrze-calendar'), $event->end);

        if ($event->allday) {
            $start = strtotime('today 00:00:00', $event->start);
            $ende = strtotime('tomorrow 00:00:00', $event->start);
        } else {
            $start = RRZE_Calendar_Functions::gmt_to_local($event->start);
            $ende = RRZE_Calendar_Functions::gmt_to_local($event->end);
        }

        if ($ende > strtotime('tomorrow 00:00:00', $start)) {
            $ende = strtotime('tomorrow 00:00:00', $start);
        }

        // Dauer in Minuten
        $tag_laenge = ($this->tag_ende - $this->tag_start) / 60;
        $duration = floor(($ende - $start) / 60);
            
        $event_data["start"] = (date('G', $start) - date('G', $this->tag_start)) * 60 + date('i', $start) - date('i', $this->tag_ende);

        $event_data["duration"] = $duration < $tag_laenge - $event_data["start"] ? $duration : $tag_laenge - $event_data["start"];

        // Ende (Pro Minute ein Pixel hoehe)
         $event_data["ende"] = (date('G', $start) - date('G', $this->tag_start)) * 60 + $event_data["duration"];

        // Zeitanzeige am Termin
        $event_data["time_start"] = $event->start_time;
        $event_data["time_ende"] = $event->end_time;
        $event_data["time"] = sprintf("%s - %s", $event_data["time_start"], $event_data["time_ende"]);

        // Bei Wiederholenden Events die Regeln zusammenfassen.
        $regel = '';
        $wochentag = date_i18n("l", $start);

        $interval = $event->recurrence_dates ? $event->recurrence_dates : NULL;
        $freq = $event->recurrence_rules ? $event->recurrence_rules : -1;

        switch ($freq) {
            case 'weekly':
                if ($interval == 1) {
                    $regel = sprintf(__('Jeden %s', 'rrze-calendar'), $wochentag);
                } else {
                    $regel = sprintf(__('Jeden %s. %s', 'rrze-calendar'), $interval, $wochentag);
                }
                break;

            default:
                $regel = date(__('d.m.Y', 'rrze-calendar'), $start);
                break;
        }

        $event_data["datum"] = $regel;
        $event_data["start_timestamp"] = $start;

        // Ganztagige Events rausfiltern
        if ($event->allday) {
            $event_data["ganztagig"] = true;
            $datum = array(date("d.m.Y", $start));
            if (abs($ende - $start) / (24 * 60 * 60) > 1) {
                $datum[] = date(__('d.m.Y', 'rrze-calendar'), abs($ende - $start) - 1);
            }
            $event_data["datum"] = implode(" - ", $datum);
        } else {
            $event_data["nicht_ganztagig"] = true;
        }

        // Farbmarkierung
        $event_data["farbe"] = isset($event->category->color) ? $event->category->color : 'grey';

        return $event_data;
    }

    protected function ist_heute($datum = '') {
        if ($datum == "")
            return false;

        return (date("Y-m-d") === $datum);
    }

    protected function ist_wochenende($datum = '') {
        if ($datum == "")
            return false;

        return (date("N", strtotime($datum)) >= 6);
    }

    protected function ist_sonntag($datum = '') {
        if ($datum == "")
            return false;

        return (date("N", strtotime($datum)) == 7);
    }

    protected function ist_im_monat($datum = '', $monat = '') {
        if ($datum == "")
            return false;

        return (date("m", strtotime($datum)) === $monat);
    }

    protected function datum_aktuell($datum = '') {
        // Datum kann ein Tag in der aktuellen Woche.
        if (!$datum || $datum === '') {
            $datum = date("Ymd");
        }

        return date("Y-m-d", strtotime($datum));
    }

    protected function rendere_template($daten) {
        wp_enqueue_style('rrze-calendar-shortcode');
        wp_enqueue_style('rrze-calendar-hint');
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
