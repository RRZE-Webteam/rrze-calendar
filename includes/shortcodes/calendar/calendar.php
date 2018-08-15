<?php

add_shortcode('rrze-calendar', array('RRZE_Calendar_Shortcode', 'shortcode'));
add_shortcode('rrze-kalender', array('RRZE_Calendar_Shortcode', 'shortcode'));

add_shortcode('calendar', array('RRZE_Calendar_Shortcode', 'shortcode'));
add_shortcode('kalender', array('RRZE_Calendar_Shortcode', 'shortcode'));

class RRZE_Calendar_Shortcode
{
    public static function shortcode($atts, $content = "")
    {
        $atts = shortcode_atts(
            array(
                'kategorien' => '',         // Mehrere Kategorien (Titelform) werden durch Komma getrennt.
                'schlagworte' => '',        // Mehrere Schlagworte (Titelform) werden durch Komma getrennt.
                'abonnement_link' => ''     // Abonnement-Link anzeigen (1 oder 0).
            ),
            $atts
        );
        
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

        if (!empty($atts['abonnement_link'])) {
            $subscribe_url = RRZE_Calendar::webcal_url(array('feed-ids' => !empty($feed_ids) ? implode(',', $feed_ids) : ''));
            $atts['subscribe_url'] = $subscribe_url;
        }
                
        $atts['filter'] = array(
            'feed_ids' => $feed_ids
        );

        $datum = '';

        if (get_query_var('calendar')) {
            $request = explode("_", get_query_var('calendar'));

            $geforderte_ansicht = !empty($request[0]) ? $request[0] : '';
            $datum = !empty($request[1]) ? $request[1] : '';
        }

        require_once "class_Monatsansicht.php";
        

        $ansicht = new Monatsansicht($atts);
        
        $tage = $ansicht->lade_tage($datum);
        $events = array();
        foreach ($tage as $tag) {
            $events[] = $ansicht->suche_events($tag);
        }
            
        
        return $ansicht->rendere_daten($events);
    }
}
