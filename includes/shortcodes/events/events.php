<?php

add_shortcode('rrze-events', array('RRZE_Calendar_Events_Shortcode', 'shortcode'));
add_shortcode('rrze-termine', array('RRZE_Calendar_Events_Shortcode', 'shortcode'));

class RRZE_Calendar_Events_Shortcode {
    
    public static function shortcode($atts, $content = "") {
        global $rrze_calendar_data, $rrze_calendar_endpoint_url, $rrze_calendar_subscribe_url;
        
        $atts = shortcode_atts(
            array(
                'kategorien' => '',     // Mehrere Kategorien (Titelform) werden durch Komma getrennt.
                'schlagworte' => '',    // Mehrere Schlagworte (Titelform) werden durch Komma getrennt.
                'anzahl' => 10,         // Anzahl der Termineausgabe. Standardwert: 10.
                'abonnement_link' => 0  // Abonnement-Link anzeigen (1 oder 0).
            ), $atts
        );

        $anzahl = intval($atts['anzahl']);
        if ($anzahl < 1) {
            $anzahl = 10;
        }

        $terms = explode(',', $atts['kategorien']);
        $terms = array_map('trim', $terms);

        $feed_ids = array();
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

        $event_tag_ids = array();
        foreach ($terms as $value) {
            $term = RRZE_Calendar::get_tag_by('slug', $value);
            if (empty($term)) {
                continue;
            }
            foreach ($term->feed_ids as $feed_id) {
                $feed_ids[$feed_id] = $feed_id;
            }
        }

        $subscribe_url = '';
        if (!empty($atts['abonnement_link'])) {
            $subscribe_url = RRZE_Calendar::webcal_url(array('feed-ids' => !empty($feed_ids) ? implode(',', $feed_ids) : ''));
        }

        $atts['filter'] = array(
            'feed_ids' => $feed_ids
        );
        
        $timestamp = RRZE_Calendar_Functions::gmt_to_local(time());
        $events_result = RRZE_Calendar::get_events_relative_to($timestamp, $anzahl, 0, $atts);
        
        $rrze_calendar_data = RRZE_Calendar_Functions::get_calendar_dates($events_result['events']);
        $rrze_calendar_endpoint_url = RRZE_Calendar::endpoint_url();
        $rrze_calendar_subscribe_url = $subscribe_url;
        
        $template = locate_template('rrze-calendar-events-shortcode.php');
                
        if (!$template) {
            wp_enqueue_style('rrze-calendar');
            $template = dirname(__FILE__) . '/template.php';
        }
        
        ob_start();
        require_once($template);
        return ob_get_clean();        
    }
    
}
