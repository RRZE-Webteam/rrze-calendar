<?php

require_once 'class_Ansicht.php';
require_once 'platzierung.php';

class Tagesansicht extends Ansicht {

    // Gibt den Dateiname des Templates zurueck.
    public function template_name() {
        return "tagesansicht";
    }

    // Funktion soll fuer Tagesansicht und Datum die anzuzeigenden Tage als Array zurueckgeben.
    public function lade_tage($datum = '') {
        // Falls kein Tag gewaehlt wurde, waehle heute.
        if (!$datum || $datum === '')
            $datum = date("Ymd");
        else
            $datum = date("Ymd", strtotime($datum));

        return array($datum);
    }

    // Rendert das Template mit den uebergebenen Daten.
    // Falls noetig werden die Daten noch formatiert.
    public function rendere_daten($daten = '') {
        $datum_aktuell = $daten[0]["datum"];
        $ts = strtotime($datum_aktuell);
        $tag = $daten[0];

        $stunden = array();
        $stunde_start = date("G", strtotime($tag["tag_anfang"]));
        for ($i = 0; $i < $tag["tag_laenge"] / 60; $i++) {
            $stunden[] = array("stunde" => $stunde_start + $i, "stunde_abstand" => $i * 60);
        }

        $tag = $this->markiere_termine($daten);

        $abonnement_url = isset($this->optionen["subscribe_url"]) ? $this->optionen["subscribe_url"] : false;
        $permalink = get_permalink();

        $ansicht_daten = array(
            "tag_datum_aktuell" => esc_url(add_query_arg('calendar', 'tag', $permalink)),
            "tag_datum_vor" => esc_url(add_query_arg('calendar', 'tag_' . $this->datum_vor($datum_aktuell), $permalink)),
            "tag_datum_zurueck" => esc_url(add_query_arg('calendar', 'tag_' . $this->datum_zurueck($datum_aktuell), $permalink)),            
            "monat_datum" => esc_url(add_query_arg('calendar', 'monat_' . $this->datum_aktuell($datum_aktuell), $permalink)),
            "woche_datum" => esc_url(add_query_arg('calendar', 'woche_' . $this->datum_aktuell($datum_aktuell), $permalink)),
            "liste" => esc_url(add_query_arg('calendar', 'liste', $permalink)),
            "tag" => $tag[0],
            "monat" => date_i18n(__('F Y', 'rrze-calendar'), $ts),
            "stunden" => $stunden,
            "abonnement_url" => $abonnement_url,
            "hoehe" => $this->optionen["hoehe"]
        );

        return $this->rendere_template($ansicht_daten);
    }

    private function markiere_termine($termine = NULL) {
        if (is_null($termine) || !is_array($termine)) {
            return -1;
        }
        
        $tag = new Tag($termine[0]["termine"]);
        $tag->markiere_termin_spalten();
        $tag->markiere_termin_indizes();
        $termine[0]["termine"] = $tag->neue_termine();
        return $termine;
    }

    public function datum_vor($datum = '') {
        if (!$datum || $datum === '') {
            $datum = date("Ymd");
        }

        return date("Y-m-d", strtotime(date("Y-m-d", strtotime($datum)) . " +1 day"));
    }

    public function datum_zurueck($datum = '') {
        if (!$datum || $datum === '') {
            $datum = date("Ymd");
        }

        return date("Y-m-d", strtotime(date("Y-m-d", strtotime($datum)) . " -1 day"));
    }

}
