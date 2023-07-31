<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\Templates;
use RRZE\Calendar\CPT\CalendarEvent;
use RRZE\Projects\Util\Util;

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
            'post_type' => CalendarEvent::POST_TYPE,
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
            $eventsArray = Utils::buildEventsArray($events, date('Y-m-d', time()), date('Y-m-d', strtotime('+1 year')));
            if ($eventsArray) {
                ksort($eventsArray);
            }
            if ($display == 'list') {
                $ulClass = 'events-list-short';
                $iconDate = '<span class="dashicons dashicons-calendar"></span>';
                $iconTime = '<span class="dashicons dashicons-clock"></span>';
            } else {
                $ulClass = 'events-list';
                $iconDate = '';
                $iconTime = '';
            }
            $i = 0;
            $output .= '<ul class="' . $ulClass . '">';
            foreach ($eventsArray as $timestamp => $events) {
                if ($timestamp < time()) continue;

                foreach ($events as $event) {
                    $eventEnd = $event['end'];
                    $offset = Utils::getTimezoneOffset('seconds');
                    $tsStartLocal = $timestamp + $offset;
                    $tsEndLocal = $eventEnd + $offset;
                    $eventTitle = get_the_title($event['id']);
                    $eventURL = get_the_permalink($event['id']);
                    $eventTitle = '<a href="' . $eventURL . '">' . $eventTitle . '</a>';
                    $location = get_post_meta($event['id'], 'location', TRUE);
                    $vc_url = get_post_meta($event['id'], 'vc-url', TRUE);
                    $allDay = get_post_meta($event['id'], 'all-day', TRUE) == 'on';
                    $isImport = get_post_meta($event['id'], 'ics_feed_id', TRUE) != '';

                    $metaStart = '<meta itemprop="startDate" content="'. date('c', $timestamp) . '" />';
                    $metaEnd = '<meta itemprop="endDate" content="'. date('c', $eventEnd) . '" />';

                    $timeOut = ($allDay == 'on' ? __('All-day', 'rrze-calendar') : date_i18n(get_option('time_format'), $tsStartLocal) . ' &ndash; ' . date_i18n(get_option('time_format'), $tsEndLocal). '</span>');
                    if ($location != '' && $vc_url == '') {
                        // Offline Event
                        $metaAttendance = '<meta itemprop="eventAttendanceMode" content="https://schema.org/OfflineEventAttendanceMode" />';
                        $metaLocation = '<meta itemprop="location" content="' . $location . '>';
                        $locationOut = $location;
                    } elseif ($location == '' && $vc_url != '') {
                        // Online Event
                        $metaAttendance = '<meta itemprop="eventAttendanceMode" content="https://schema.org/OnlineEventAttendanceMode" />';
                        $metaLocation = '<span itemprop="location" itemscope itemtype="https://schema.org/VirtualLocation"><meta itemprop="url" content="' . $vc_url . '" /></span>';
                        $locationOut = __('Online', 'rrze-calendar');
                    } elseif ($location != '' && $vc_url != '') {
                        // Hybrid Event
                        $metaAttendance = '<meta itemprop="eventAttendanceMode" content="https://schema.org/MixedEventAttendanceMode" />';
                        $metaLocation = '<meta itemprop="location" content="' . $location . '">'
                            . '<span itemprop="location" itemscope itemtype="https://schema.org/VirtualLocation"><meta itemprop="url" content="' . $vc_url . '" /></span>';
                        $locationOut = $location . ' / ' . __('Online', 'rrze-calendar');
                    } else {
                        $metaLocation = '';
                        $metaAttendance = '';
                        $locationOut = '';
                    }

                    $output .= '<li class="event-item" itemscope itemtype="https://schema.org/Event">';
                    if ($display == 'list') {
                        $output .= '<span class="dashicons dashicons-calendar"></span><span class="event-date"> ' . date_i18n(get_option('date_format'), $timestamp)
                            . '<span class="dashicons dashicons-clock"></span>' . $timeOut . '<br />'
                            . '<span class="event-title" itemprop="name">' . $eventTitle . '</span>'
                            . $metaStart
                            . $metaEnd
                            . $metaLocation
                            . $metaAttendance;
                        wp_enqueue_style( 'dashicons' );
                    } else {
                        $bgColor = '';
                        $categoryObjects = wp_get_object_terms($event['id'], 'rrze-calendar-category');
                        if (!is_wp_error($categoryObjects) && !empty($categoryObjects)) {
                            $cat = $categoryObjects[0];
                            $bgColor = get_term_meta($cat->term_id, 'color', true);
                            $color = $bgColor ? Utils::getContrastYIQ($bgColor) : '#222';
                        }
                        $output .= '<div class="event-date" ' . ($bgColor != '' ? ' style="background-color: ' . $bgColor . '; color: ' . $color . ';"' : '') . '>'
                            . '<div class="day-month">'
                            . '<div class="day">' . date('d', $timestamp) . '</div>'
                            . '<div class="month">' . date_i18n('M', $timestamp) . '</div>'
                            . '</div>'
                            //. '<div class="year">' . date('Y', $timestamp) .'</div>'
                            . '</div>'
                            . '<div class="event-info">'
                            . '<div class="event-time">' . $timeOut . '</div>'
                            . '<div class="event-title" itemprop="name">' . $eventTitle . '</div>'
                            . '<div class="event-location">' . $locationOut . '</div>'
                            . $metaStart
                            . $metaEnd
                            . $metaLocation
                            . $metaAttendance
                            . '</div>';
                    }
                    $output .= '</li>';
                    $i++;
                    if ($i >= $number) break 2;
                }
            }
            $output .= '</ul>';

            if (strlen($output) < 100) {
                $output .= '<p>' . __('No events scheduled.', 'rrze-calendar') . '</p>';
            } else {
                if (is_numeric($atts['page_link']) && is_string(get_post_status((int)$atts['page_link']))) {
                    $label = sanitize_text_field($atts['page_link_label']);
                    $output .= do_shortcode('[button link="' . get_permalink((int)$atts['page_link']) . '"]' . $label . '[/button]');
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
