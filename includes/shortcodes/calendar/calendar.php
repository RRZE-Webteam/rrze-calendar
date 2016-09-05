<?php

add_shortcode('rrze-calendar', array('RRZE_Calendar_Shortcode', 'shortcode'));
add_shortcode('rrze-kalender', array('RRZE_Calendar_Shortcode', 'shortcode'));

class RRZE_Calendar_Shortcode {

    public static function shortcode($atts, $content = "") {
        $atts = shortcode_atts(
            array(
                'kategorien' => '',         // Mehrere Kategorien (Titelform) werden durch Komma getrennt.
                'schlagworte' => '',        // Mehrere Schlagworte (Titelform) werden durch Komma getrennt.
                'anzahl' => 10,             // Anzahl der Termine in der Listenansicht. Standardwert: 10.
                'tagesanfang' => '7:00',    // Format: "SS:MM". Standardwert: "07:00".
                'tagesende' => '21:00',     // Format: "SS:MM". Standardwert: "21:00".
                'ansicht' => 'monat',       // "tag", "woche", "monat" oder "liste". Standardwert: "monat".
                'abonnement_link' => ''     // Abonnement-Link anzeigen (1 oder 0).
            ), $atts
        );

        $anzahl = intval($atts['anzahl']);
        if ($anzahl < 1) {
            $anzahl = 10;
        }
        $atts['anzahl'] = $anzahl;
        
        $feed_ids = array();
        
        $terms = explode(',', $atts['kategorien']);
        $terms = array_map('trim', $terms);

        foreach ($terms as $value) {
            $term = RRZE_Calendar::get_category_by('slug', $value);
            if (empty($term)) {
                continue;
            }
            foreach ($term->feed_ids as $feed_id) {
                $feed_ids[$feed_id] = $feed_id;
            }
        }

        $terms = explode(',', $atts['schlagworte']);
        $terms = array_map('trim', $terms);

        foreach ($terms as $value) {
            $term = RRZE_Calendar::get_tag_by('slug', $value);
            if (empty($term)) {
                continue;
            }
            foreach ($term->feed_ids as $feed_id) {
                $feed_ids[$feed_id] = $feed_id;
            }
        }
        
        if (!self::validate_time($atts['tagesanfang'])) {
            $atts['tagesanfang'] = '7:00';
        }

        if (!self::validate_time($atts['tagesende'])) {
            $atts['tagesende'] = '21:00';
        }

        if (!empty($atts['abonnement_link'])) {
            $subscribe_url = RRZE_Calendar::webcal_url(array('feed-ids' => !empty($feed_ids) ? implode(',', $feed_ids) : ''));
            $atts['subscribe_url'] = $subscribe_url;
        }
                
        $atts['filter'] = array(
            'feed_ids' => $feed_ids
        );
                             
        $ansichten = array(
            'tag' => 'Tagesansicht',
            'woche' => 'Wochenansicht',
            'monat' => 'Monatsansicht',
            'liste' => 'Listenansicht'
        );
        
        if (array_key_exists($atts['ansicht'], $ansichten)) {
            $standard_ansicht = $atts['ansicht'];
        } else {
            $standard_ansicht = 'monat';
        }

        $geforderte_ansicht = '';
        $datum = '';

        if (get_query_var('calendar')) {
            $request = explode("_", get_query_var('calendar'));

            $geforderte_ansicht = !empty($request[0]) ? $request[0] : '';
            $datum = !empty($request[1]) ? $request[1] : '';
        }

        if (empty($geforderte_ansicht) || $geforderte_ansicht === '') {
            $geforderte_ansicht = $standard_ansicht;
        }
        if (!array_key_exists($geforderte_ansicht, $ansichten)) {
            $geforderte_ansicht = $standard_ansicht;
        }

        $class_name = "class_" . $ansichten[$geforderte_ansicht] . ".php";
        require_once $class_name;
        

        $ansicht = new $ansichten[$geforderte_ansicht]($atts);
        
        if ($geforderte_ansicht === "liste") {
            $events = $ansicht->suche_events();
        } else {
            $tage = $ansicht->lade_tage($datum);
            $events = array();
            foreach ($tage as $tag) {
                $events[] = $ansicht->suche_events($tag);
            }
            
        }
        
        return $ansicht->rendere_daten($events);

    }

    private static function validate_time($time) {
        $e = explode(':', $time);
        if (count($e) < 2 || count($e) > 2) {
            return false;
        }
        $h = absint($e[0]);
        $m = absint($e[1]);
        if ($h > 24 || $m > 59 || ($h == 24 && $m > 0)) {
            return false;
        }
        
        return true;
    }

}
