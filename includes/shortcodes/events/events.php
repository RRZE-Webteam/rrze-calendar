<?php

add_shortcode('rrze-events', array('RRZE_Calendar_Events_Shortcode', 'shortcode'));
add_shortcode('rrze-termine', array('RRZE_Calendar_Events_Shortcode', 'shortcode'));

add_shortcode('events', array('RRZE_Calendar_Events_Shortcode', 'shortcode'));
add_shortcode('termine', array('RRZE_Calendar_Events_Shortcode', 'shortcode'));

class RRZE_Calendar_Events_Shortcode {
    
    public static function shortcode($atts, $content = "") {
        global $rrze_calendar_data, $rrze_calendar_page_url, $rrze_calendar_subscribe_url;
        
        $atts = shortcode_atts(
            array(
                'kategorien' => '',     // Mehrere Kategorien (Titelform) werden durch Komma getrennt.
                'schlagworte' => '',    // Mehrere Schlagworte (Titelform) werden durch Komma getrennt.
                'anzahl' => 10,         // Anzahl der Termineausgabe. Standardwert: 10.
                'page_link' => 0,       // ID einer Zielseite um z.B. weitere Termine anzuzeigen.
                'abonnement_link' => 0  // Abonnement-Link anzeigen (1 oder 0).
            ), $atts
        );

        $anzahl = intval($atts['anzahl']);
        if ($anzahl < 1) {
            $anzahl = 10;
        }

        $taxonomy_not_found = FALSE;
        $feed_ids = array();
        
        $terms = $atts['kategorien'] ? array_map('trim', explode(',', $atts['kategorien'])) : array();

        foreach ($terms as $value) {
            $term = RRZE_Calendar::get_category_by('slug', $value);
            if (empty($term)) {
                $taxonomy_not_found = TRUE;
                continue;
            }
            foreach ($term->feed_ids as $feed_id) {
                $feed_ids[$feed_id] = $feed_id;
            }
        }

        $terms = $atts['kategorien'] ? array_map('trim', explode(',', $atts['schlagworte'])) : array();

        foreach ($terms as $value) {
            $term = RRZE_Calendar::get_tag_by('slug', $value);
            if (empty($term)) {
                $taxonomy_not_found = TRUE;
                continue;
            }
            foreach ($term->feed_ids as $feed_id) {
                $feed_ids[$feed_id] = $feed_id;
            }
        }

        $page_url = '';
        $post_id = absint($atts['page_link']);
        if($post_id) {            
            $post_type = get_post_type($post_id);
            if ($post_type === 'page') {
                $page_url = get_permalink($post_id);
            }
        }
        
        $subscribe_url = $atts['abonnement_link'] ? RRZE_Calendar::webcal_url(array('feed-ids' => !empty($feed_ids) ? implode(',', $feed_ids) : '')) : '';
        
        $filter = array(
            'feed_ids' => $feed_ids
        );
        
        $rrze_calendar_data = array();
        
        if ($feed_ids OR (!$feed_ids && !$taxonomy_not_found)) {
            $timestamp = RRZE_Calendar_Functions::gmt_to_local(time());
            $events_result = RRZE_Calendar::get_events_relative_to($timestamp, $anzahl, 0, $filter);

            $rrze_calendar_data = RRZE_Calendar_Functions::get_calendar_dates($events_result['events']);
        }
        
        $rrze_calendar_page_url = $page_url;
        $rrze_calendar_subscribe_url = $subscribe_url;
        
        $template = locate_template('rrze-calendar-events-shortcode.php');
                
        if (!$template) {
            wp_enqueue_style('rrze-calendar');
            $template = dirname(__FILE__) . '/template.php';
        }
        
        ob_start();
        include $template;
        return ob_get_clean();        
    }
    
}
