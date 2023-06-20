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
            'categories' => '',  // Multiple categories (slugs) are separated by commas
            'kategorien' => '',  // Multiple categories (slugs) are separated by commas
            'tags' => '',        // Multiple keywords (slugs) are separated by commas
            'schlagworte' => '', // Multiple keywords (slugs) are separated by commas
            'count' => 0,       // Number of events to show. Default value: 0
            'number' => 0,       // Number of events to show. Default value: 0
            'anzahl' => 0,      // Number of events to show. Default value: 0
            'page_link' => '',    // ID of a target page, e.g. to display further events
            'page_link_label' => __('All Events', 'rrze-calendar'),
            'abonnement_link' => '',    // Display link to ICS Feed
            'start' => '',       // Start date of appointment list. Format: "Y-m-d" or use a PHP relative date format
            'end' => '',          // End date of appointment listing. Format: "Y-m-d" or use a PHP relative date format
        ];
        $atts = shortcode_atts( $atts_default, $atts );
        $display = $atts['display'] == 'list' ? 'list' : 'teaser';
        $number = absint($atts['number']) + absint($atts['count'])  + absint($atts['anzahl']);
        if ($number < 1) {
            $number = 10;
        }

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

        if (in_array($atts['featured'], ['true', 'ja', 'yes', '1'])) {
            $args['meta_query'][] = [
                'key' => 'featured',
                'value' => 'on',
            ];
        }

        $categoriesRaw = '';
        if ($atts['categories']) {
            $categoriesRaw = $atts['categories'];
        } elseif ($atts['kategorien']) {
            $categoriesRaw = $atts['kategorien'];
        }
        if ($categoriesRaw != '') {
            $categories = Utils::strListToArray($categoriesRaw, 'sanitize_title');
            $args['tax_query'] = array(
                'relation' => 'AND',
                array(
                    'taxonomy' => CalendarEvent::TAX_CATEGORY,
                    'field' => 'slug',
                    'terms' => $categories,
                )
            );
        }

        $tagsRaw = '';
        if ($atts['tags']) {
            $tagsRaw = $atts['tags'];
        } elseif ($atts['schlagworte']) {
            $tagsRaw = $atts['schlagworte'];
        }
        if ($tagsRaw != '') {
            $tags = Utils::strListToArray($tagsRaw, 'sanitize_title');
            $args['tax_query'] = array(
                'relation' => 'AND',
                array(
                    'taxonomy' => CalendarEvent::TAX_TAG,
                    'field' => 'slug',
                    'terms' => $tags,
                )
            );
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
                $output .= '<ul class="events-list-short">';
                $i = 0;
                foreach ($eventsArray as $timestamp => $events) {
                    if ($timestamp >= time()) {
                        foreach ($events as $event) {
                            $eventEnd = $event['end'];
                            $allDay = get_post_meta($event['id'], 'all-day', true);
                            $eventTitle = get_the_title($event['id']);
                            $eventURL = get_the_permalink($event['id']);
                            $eventTitle = '<a href="' . $eventURL . '">' . $eventTitle . '</a>';
                            $output .= '<li class="event-item">'
                                . '<span class="dashicons dashicons-calendar"></span><span class="event-date"> ' . date_i18n(get_option('date_format'), $timestamp)
                                . '<span class="dashicons dashicons-clock"></span>' . ($allDay == 'on' ? __('All-day', 'rrze-calendar') : date_i18n(get_option('time_format'), $timestamp) . ' &ndash; ' . date_i18n(get_option('time_format'), $eventEnd). '</span>') . '<br />'
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
                            $allDay = get_post_meta($event['id'], 'all-day', true) == 'on';
                            $output .= '<li class="event-item" itemscope itemtype="http://schema.org/Event">'
                                . '<meta itemprop="startDate" content="'. date('c', $timestamp) . '">'
                                . '<meta itemprop="endDate" content="'. date('c', $event['end']) . '">'
                                . '<div class="event-date">'
                                . '<div class="day-month">'
                                . '<div class="day">' . date('d', $timestamp) . '</div>'
                                . '<div class="month">' . date_i18n('M', $timestamp) . '</div>'
                                . '</div>'
                                //. '<div class="year">' . date('Y', $timestamp) .'</div>'
                                . '</div>'
                                . '<div class="event-info">'
                                . '<div class="event-time">' . ($allDay ? __('All-day', 'rrze-calendar') : $timeStart . ' &ndash; ' . $timeEnd) . '</div>'
                                . '<div class="event-title" itemprop="name">' . $eventTitle . '</div>'
                                . ($location != '' ? '<div class="event-location" itemprop="location">' . $location . '</div>' : '')
                                . '</div>'
                                . '</li>';
                            $i++;
                            if ($i >= $number) break 2;
                        }
                        $output .= '</ul>';
                    }
                }
            }
            if (is_numeric($atts['page_link']) && is_string(get_post_status((int)$atts['page_link']))) {
                $label = sanitize_text_field($atts['page_link_label']);
                $output .= do_shortcode('[button link="' . get_permalink((int)$atts['page_link']) . '"]' . $label . '[/button]');
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
