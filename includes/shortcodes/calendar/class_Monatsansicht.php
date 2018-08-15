<?php

require_once 'class_Ansicht.php';

class Monatsansicht extends Ansicht
{

    // Gibt den Dateiname des Templates zurueck.
    public function template_name()
    {
        return "monatsansicht";
    }

    public function lade_tage($datum = '')
    {
        // Datum kann ein Tag im aktuellen Monat sein.
        if (!$datum || $datum === '') {
            $datum = date("Ymd");
        }
        
        $ts = strtotime($datum);


        $jahr = date('Y', $ts);
        $monat = date('m', $ts);


        $erster_im_monat = strtotime($jahr . "-" . $monat . "-1");
        $letzter_im_monat = strtotime($jahr . "-" . $monat . "-" . cal_days_in_month(CAL_GREGORIAN, $monat, $jahr));
        $erste_woche_im_monat = abs(date("W", $erster_im_monat));
        $letzte_woche_im_monat = abs(date("W", $letzter_im_monat));
        
        $zeige_erste_woche_im_jahr = false;
        if ($letzte_woche_im_monat == 1) {
            $letzte_woche_im_monat = 52;
            $zeige_erste_woche_im_jahr = true;
        }
        
        $tage_in_monat = array();

        if ($erste_woche_im_monat > $letzte_woche_im_monat) {
            for ($i = 1; $i <= 7; $i++) {
                $ts = strtotime(($jahr - 1) . 'W' . $erste_woche_im_monat . $i);
                $tag = date("Y-m-d", $ts);
                array_push($tage_in_monat, $tag);
            }
        }
        
        for ($woche = ($erste_woche_im_monat > $letzte_woche_im_monat ? 1 : $erste_woche_im_monat); $woche <= $letzte_woche_im_monat; $woche++) {
            if ($woche < 10) {
                $woche = "0" . $woche;
            }
            
            for ($i = 1; $i <= 7; $i++) {
                $ts = strtotime($jahr . 'W' . $woche . $i);
                $tag = date("Y-m-d", $ts);
                array_push($tage_in_monat, $tag);
            }
        }
        
        if ($zeige_erste_woche_im_jahr) {
            for ($i = 1; $i <= 7; $i++) {
                $ts = strtotime(($jahr + 1) . 'W01' . $i);
                $tag = date("Y-m-d", $ts);
                array_push($tage_in_monat, $tag);
            }
        }
        
        return $tage_in_monat;
    }

    // Rendert das Template mit den uebergebenen Daten.
    // Falls noetig werden die Daten noch formatiert.
    public function rendere_daten($daten = '')
    {
        $tag = $daten[10]["datum"];
        $ts = strtotime($tag);
        // Tag gleich ersten im Monat waehlen
        $tag = date("Y-m-", $ts) . "01";

        // Ueberhang Tage markieren.
        $monat = date("m", strtotime($tag));
        for ($i = 0; $i < count($daten); $i++) {
            $im_monat = (date("m", strtotime($daten[$i]["datum"])) === $monat);
            $daten[$i]["nicht_im_monat"] = !$im_monat;
        }

        // Nach Wochen gruppieren
        $wochen_anzahl = count($daten) / 7;
        $wochen = array();
        for ($w = 0; $w < $wochen_anzahl; $w++) {
            $tage = [];
            $itemCount = 0;
            for ($t = 0; $t < 7; $t++) {
                $event = $daten[7 * $w + $t];
                $tage[] = $event;
                $count = count($event['termine']);
                $itemCount = $itemCount < $count ? $count : $itemCount;
            }
            $wochen[$w] = ['tage' => $tage, 'itemcount' => $itemCount];
        }

        $abonnement_url = isset($this->optionen["subscribe_url"]) ? $this->optionen["subscribe_url"] : false;
        $permalink = get_permalink();
        
        $ansicht_daten = array(
            "monat_datum_aktuell" => esc_url(add_query_arg('calendar', 'monat', $permalink)),
            "monat_datum_vor" => esc_url(add_query_arg('calendar', 'monat_' . $this->datum_vor($tag), $permalink)),
            "monat_datum_zurueck" => esc_url(add_query_arg('calendar', 'monat_' . $this->datum_zurueck($tag), $permalink)),
            "tag_datum" => esc_url(add_query_arg('calendar', 'tag_' . $this->datum_aktuell($tag), $permalink)),
            "woche_datum" => esc_url(add_query_arg('calendar', 'woche_' . $this->datum_aktuell($tag), $permalink)),
            "liste" => esc_url(add_query_arg('calendar', 'liste', $permalink)),
            "monat" => date_i18n(__('F Y', 'rrze-calendar'), $ts),
            "wochen" => $wochen,
            "abonnement_url" => $abonnement_url
        );

        return $this->rendere_template($ansicht_daten);
    }

    public function datum_vor($datum = '')
    {
        if (!$datum || $datum === '') {
            $datum = date("Ymd");
        }

        return date("Y-m-d", strtotime(date("Y-m-d", strtotime($datum)) . " +1 month"));
    }

    public function datum_zurueck($datum = '')
    {
        if (!$datum || $datum === '') {
            $datum = date("Ymd");
        }

        return date("Y-m-d", strtotime(date("Y-m-d", strtotime($datum)) . " -1 month"));
    }
}
