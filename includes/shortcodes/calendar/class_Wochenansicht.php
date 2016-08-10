<?php

require_once 'class_Ansicht.php';
require_once 'platzierung.php';

class Wochenansicht extends Ansicht {

    // Gibt den Dateiname des Templates zurueck.
    public function template_name() {
        return "wochenansicht";
    }

    // Funktion soll fuer Tagesansicht und Datum die anzuzeigenden Tage als Array zurueckgeben.
    public function lade_tage($datum = '') {
        // Datum kann ein Tag in der aktuellen Woche.
        if (!$datum || $datum === '') {
            $datum = date("Y-m-d");
        }

        $ts = strtotime($datum);

        $jahr = date('o', $ts);
        $woche = date('W', $ts);

        $tage_in_woche = array();

        for ($i = 1; $i <= 7; $i++) {

            $ts = strtotime($jahr . 'W' . $woche . $i);
            $tag = date("Y-m-d", $ts);
            array_push($tage_in_woche, $tag);
        }
        return $tage_in_woche;
    }

    // Rendert das Template mit den uebergebenen Daten.
    // Falls noetig werden die Daten noch formatiert.
    public function rendere_daten($daten = '') {
        $tag = $daten[2];
        $ts = strtotime($tag["datum"]);
        $monat = date_i18n(__('F Y', 'rrze-calendar'), $ts);
        $tage = $this->markiere_termine($daten);
        //$tage = $this->platzierung($daten);

        $stunden = array();
        $stunde_start = date("G", strtotime($tag["tag_anfang"]));
        for ($i = 0; $i < $tag["tag_laenge"] / 60; $i++) {
            $stunden[] = array("stunde" => $stunde_start + $i, "stunde_abstand" => $i * 60);
        }

        $abonnement_url = isset($this->optionen["subscribe_url"]) ? $this->optionen["subscribe_url"] : false;
        $permalink = get_permalink();

        $ansicht_daten = array(
            "woche_datum_aktuell" => esc_url(add_query_arg('calendar', 'woche', $permalink)),
            "woche_datum_vor" => esc_url(add_query_arg('calendar', 'woche_' . $this->datum_vor($tag["datum"]), $permalink)),
            "woche_datum_zurueck" => esc_url(add_query_arg('calendar', 'woche_' . $this->datum_zurueck($tag["datum"]), $permalink)),            
            "tag_datum" => esc_url(add_query_arg('calendar', 'tag_' . $this->datum_aktuell($tag["datum"]), $permalink)),
            "monat_datum" => esc_url(add_query_arg('calendar', 'monat_' . $this->datum_aktuell($tag["datum"]), $permalink)),
            "liste" => esc_url(add_query_arg('calendar', 'liste', $permalink)),
            "monat" => $monat,
            "tage" => $tage,
            "stunden" => $stunden,
            "abonnement_url" => $abonnement_url
        );
        return $this->rendere_template($ansicht_daten);
    }

    private function markiere_termine($tage = NULL) {
        if (is_null($tage) || !is_array($tage)) {
            return -1;
        }

        for ($t = 0; $t < count($tage); $t++) {
            $tag = new Tag($tage[$t]["termine"]);
            $tag->markiere_termin_spalten();
            $tag->markiere_termin_indizes();
            $tage[$t]["termine"] = $tag->neue_termine();
        }

        return $tage;
    }
    
    public function datum_vor($datum = '') {
        // Datum kann ein Tag in der aktuellen Woche.
        if (!$datum || $datum === '') {
            $datum = date("Y-m-d");
        }

        return date("d-m-Y", strtotime(date("Y-m-d", strtotime($datum)) . " +1 week"));
    }

    public function datum_zurueck($datum = '') {
        // Datum kann ein Tag in der aktuellen Woche.
        if (!$datum || $datum === '') {
            $datum = date("Y-m-d");
        }

        return date("d-m-Y", strtotime(date("Y-m-d", strtotime($datum)) . " -1 week"));
    }

}
