<?php

use \RRZE\Calendar\Util;

add_shortcode('rrze-events', array('Events_Shortcode', 'shortcode'));
add_shortcode('rrze-termine', array('Events_Shortcode', 'shortcode'));

add_shortcode('events', array('Events_Shortcode', 'shortcode'));
add_shortcode('termine', array('Events_Shortcode', 'shortcode'));

class Events_Shortcode
{
    public static function shortcode($atts, $content = "")
    {
        $atts = shortcode_atts(
            array(
                'kategorien' => '',     // mehrere Kategorien (Titelform) werden durch Komma getrennt.
                'schlagworte' => '',    // mehrere Schlagworte (Titelform) werden durch Komma getrennt.
                'anzahl' => 10,         // Anzahl der Termineausgabe. Standardwert: 10.
                'page_link' => 0,       // ID einer Zielseite um z.B. weitere Termine anzuzeigen.
                'abonnement_link' => 0, // Abonnement-Link anzeigen (1 oder 0).
                'location' => 0,        // Der Ort des Termins anzeigen  (1 oder 0).
                'description' => 0,     // Die Beschreibung des Termins anzeigen (1 oder 0).
                'start' => '',
                'end' => ''
            ),
            $atts
        );

        $abonnement_link = empty($atts['abonnement_link']) ? 0 : 1;
        $location = empty($atts['anzahl']) ? 0 : 1;
        $description = empty($atts['description']) ? 0 : 1;

        $anzahl = absint($atts['anzahl']);
        if ($anzahl < 1) {
            $anzahl = 10;
        }

        $start_date = strtotime(trim($atts['start'])) !== false ? strtotime('midnight', strtotime(trim($atts['start']))) : '';
        $end_date = strtotime(trim($atts['end'])) !== false ? strtotime('tomorrow', strtotime('midnight', strtotime(trim($atts['end'])))) - 1 : '';

        $taxonomy_empty = false;
        $feed_ids = array();

        $terms = $atts['kategorien'] ? array_map('trim', explode(',', $atts['kategorien'])) : array();

        foreach ($terms as $value) {
            $term = RRZE_Calendar::get_category_by('slug', $value);
            if (empty($term) || empty($term->feed_ids)) {
                $taxonomy_empty = true;
                continue;
            }
            foreach ($term->feed_ids as $feed_id) {
                $feed_ids[$feed_id] = $feed_id;
            }
        }

        $terms = $atts['schlagworte'] ? array_map('trim', explode(',', $atts['schlagworte'])) : array();

        foreach ($terms as $value) {
            $term = RRZE_Calendar::get_tag_by('slug', $value);
            if (empty($term) || empty($term->feed_ids)) {
                $taxonomy_empty = true;
                continue;
            }
            foreach ($term->feed_ids as $feed_id) {
                $feed_ids[$feed_id] = $feed_id;
            }
        }

        $page_url = '';
        $post_id = absint($atts['page_link']);
        if ($post_id) {
            $post_type = get_post_type($post_id);
            if ($post_type === 'page') {
                $page_url = get_permalink($post_id);
            }
        }

        $subscribe_url = $abonnement_link ? Util::webCalUrl(['feed-ids' => !empty($feed_ids) ? implode(',', $feed_ids) : '']) : '';

        $filter = array(
            'feed_ids' => $feed_ids
        );

        $events_data = array();

        if ($feed_ids or (!$feed_ids && !$taxonomy_empty)) {
            $today = date('Y-m-d 00:00:00', time());
            $events_result = RRZE_Calendar::getEventsRelativeTo($today, 0, $filter);
            $events_data = Util::getCalendarDates($events_result);
        }

        $calendar_page_url = $page_url;
        $calendar_subscribe_url = $subscribe_url;

        $current_theme = wp_get_theme();

        $template = '';
        foreach (RRZE_Calendar::$allowed_stylesheets as $dir => $style) {
            if (in_array(strtolower($current_theme->stylesheet), array_map('strtolower', $style))) {
                $template = dirname(__FILE__) . "/themes/$dir/template.php";
                break;
            }
        }

        if (!file_exists($template)) {
            wp_enqueue_style('rrze-calendar');
            $template = dirname(__FILE__) . '/template.php';
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }
}
