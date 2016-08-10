<?php

require_once 'class_Ansicht.php';

class Listenansicht extends Ansicht {

    // Gibt den Dateiname des Templates zurueck.
    public function template_name() {
        return "listenansicht";
    }

    // Funktion soll fuer Tagesansicht und Datum die anzuzeigenden Tage als Array zurueckgeben.
    public function lade_tage($datum = '') {
        // Soll alle Termine laden.
        return -1;
    }

    // Rendert das Template mit den uebergebenen Daten.
    // Falls noetig werden die Daten noch formatiert.
    public function rendere_daten($daten = '') {
        $abonnement_url = isset($this->optionen["subscribe_url"]) ? $this->optionen["subscribe_url"] : false;
        $permalink = get_permalink();

        $ansicht_daten = array(
            "tag" => esc_url(add_query_arg('calendar', 'tag', $permalink)),
            "woche" => esc_url(add_query_arg('calendar', 'woche', $permalink)),
            "monat" => esc_url(add_query_arg('calendar', 'monat', $permalink)),
            'termine' => $daten['termine'],
            "abonnement_url" => $abonnement_url
        );

        return $this->rendere_template($ansicht_daten);
    }

    public function datum_vor($datum = '') {
        return NULL;
    }

    public function datum_zurueck($datum = '') {
        return NULL;
    }

}
