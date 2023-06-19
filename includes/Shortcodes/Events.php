<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\Templates;
use RRZE\Calendar\CPT\CalendarEvent;

class Events
{
    public static function init()
    {
        add_shortcode('rrze-events', [__CLASS__, 'shortcode']);
        add_shortcode('rrze-termine', [__CLASS__, 'shortcode']);
        add_shortcode('events', [__CLASS__, 'shortcode']);
        add_shortcode('termine', [__CLASS__, 'shortcode']);
    }

    public static function shortcode($atts, $content = "")
    {
        $atts_default = [
            'featured' => 'false',
            'display' => '',
            'number' => '99',
            'category' => '',
            'tag' => '',

        ];
        $atts = shortcode_atts( $atts_default, $atts );
        $featured = in_array($atts['featured'], ['true', 'ja', 'yes', '1']);
        $display = $atts['display'] == 'list' ? 'list' : 'teaser';
        $number = (int)$atts['number'];
        $category = sanitize_text_field($atts['category']);
        $tag = sanitize_text_field($atts['tag']);
        $args = [
            'post_type' => 'calendar_event',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key' => 'repeat-lastdate',
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key' => 'repeat-lastdate',
                        'value' => time(),
                        'compare' => '>='
                    ],
                ],
            ],
            'orderby' => 'meta_key',
            'meta_key' => 'start',
        ];
        if ($featured) {
            $args['meta_query'][] = [
                'key' => 'featured',
                'value' => 'on',
            ];
        }

        if ($category != '') {
            $categories = array_map('trim', explode(",", $category));
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'rrze-calendar-category',
                    'field' => 'name',
                    'terms' => $categories,
                )
            );
        }

        // TODO
        if ($tag != '') {
            $t_id = [];
            $tags = array_map('trim', explode(",", $tag));
            foreach ($tags as $_t) {
                if ($term_id = get_term_by('name', $_t, 'post_tag')->term_id) {
                    $t_id[] = $term_id;
                }
            }
        }

        $events = get_posts($args );

        $output = '<div class="rrze-calendar">';

        if (!empty($events)) {
            $i = 0;
            foreach ($events as $event) {
                $eventItems = get_post_meta($event->ID, 'event-items', true);
                if (!empty($eventItems)) {
                    foreach ($eventItems as $TSstart_ID => $TSend) {
                        $start = explode('#', $TSstart_ID)[0];
                        $eventsArray[$start][$i]['id'] = $event->ID;
                        $eventsArray[$start][$i]['end'] = $TSend;
                        $i++;
                    }
                }
            }
            ksort($eventsArray);
            if ($display == 'list') {
                // TODO: Styling
                $output .= '<ul class="events-list">';
                $i = 0;
                foreach ($eventsArray as $timestamp => $events) {
                    if ($timestamp >= time()) {
                        foreach ($events as $event) {
                            $eventTitle = get_the_title($event['id']);
                            $eventURL = get_the_permalink($event['id']);
                            $eventTitle = '<a href="' . $eventURL . '">' . $eventTitle . '</a>';
                            $output .= '<li><span class="event-date"> ' . date_i18n('D, d.m.Y', $timestamp) . '<span class="dashicons dashicons-clock"></span>' . date_i18n('H:i', $timestamp) . '</span><br />'
                                . '<span class="event-title">' . $eventTitle . '</span></li>';
                            $i++;
                            if ($i >= $number) break 2;
                        }
                    }
                }
                $output .= '</ul>';
            } else {
                $i = 0;
                foreach ($eventsArray as $timestamp => $events) {
                    if ($timestamp >= time()) {
                        $output .= '<ul class="events-list">';
                        foreach ($events as $event) {
                            $eventTitle = get_the_title($event['id']);
                            $eventURL = get_the_permalink($event['id']);
                            $eventTitle = '<a href="' . $eventURL . '">' . $eventTitle . '</a>';
                            $timeStart = date(get_option('time_format'), $timestamp);
                            $timeEnd = date(get_option('time_format'), $event['end']);
                            $location = get_post_meta($event['id'], 'location', true);
                            $allDay = get_post_meta($event['id'], 'all-day', true) == 'on' ? true : false;
                            $output .= '<li class="event">'
                                . '<div class="event-date">'
                                . '<div class="day-month">'
                                . '<div class="day">' . date('d', $timestamp) . '</div>'
                                . '<div class="month">' . date_i18n('M', $timestamp) . '</div>'
                                . '</div>'
                                //. '<div class="year">' . date('Y', $timestamp) .'</div>'
                                . '</div>'
                                . '<div class="event-info">'
                                . '<div class="event-time">' . ($allDay ? __('All-day', 'rrze-calendar') : $timeStart . ' &ndash; ' . $timeEnd) . '</div>'
                                . '<div class="event-title">' . $eventTitle . '</div>'
                                . '<div class="event-location">' . $location . '</div>'
                                . '</div>'
                                . '</li>';
                            $i++;
                            if ($i >= $number) break 2;
                        }
                        $output .= '</ul>';
                    }
                }
            }
        } else {
            $output .= '<p>' . __('No events scheduled.', 'rrze-calendar') . '</p>';
        }
        $output .= '</div>';

        wp_reset_postdata();
        wp_enqueue_style('rrze-calendar-sc-events');

        return $output;
    }
}
