<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Calendar\ICS\Export;
use RRZE\Calendar\Utils;
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
            'page_link_label' => __('Show All Events', 'rrze-calendar'),
            'abonnement_link' => '',    // Display link to ICS Feed
            'start' => '',       // Start date of appointment list. Format: "Y-m-d" or use a PHP relative date format
            'end' => '',          // End date of appointment listing. Format: "Y-m-d" or use a PHP relative date format
            'include' => '',
            'exclude' => '',
        ];
        $atts = shortcode_atts( $atts_default, $atts );
        $display = $atts['display'] == 'list' ? 'list' : 'teaser';
        $number = absint($atts['number']) + absint($atts['count'])  + absint($atts['anzahl']);
        if ($number < 1) {
            $number = 10;
        }
        $IDs = [];

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

        $include = sanitize_text_field($atts['include']);
        $exclude = sanitize_text_field($atts['exclude']);
        if ($exclude != '' || $include != '') {
            add_filter('posts_where', ['RRZE\Calendar\Utils', 'titleFilter'],10,2);
            if ($include != '') $args['title_filter'] = $include;
            if ($exclude != '') $args['title_filter_exclude'] = $exclude;
            $args['suppress_filters'] = false;
        }

        $events = get_posts($args );

        if ($exclude != '' || $include != '') {
            remove_filter('posts_where', ['RRZE\Calendar\Utils', 'titleFilter']);
        }

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
                
                // Add multiday items
                foreach ($eventsArray as $tsStart => $events) {
                    foreach ($events as $event) {
                        $tsEnd = $event['end'];
                        $startDate = date('Y-m-d', $tsStart);
                        $endDate = date('Y-m-d', $tsEnd);
                        $tsStartCounter = $tsStart + (60 * 60 * 24);
                        if ($endDate != $startDate) {
                            while ($tsStartCounter < $tsEnd) {
                                $eventsArray[$tsStartCounter][] = $event;
                                $tsStartCounter += (60 * 60 * 24);
                            }
                        }
                    }
                }
                ksort($eventsArray);
            }
            $i = 0;
            $output .= '<ul class="' . $ulClass . '">';
            foreach ($eventsArray as $tsCount => $events) {
                if (date('Ymd',$tsCount) < date('Ymd', time())) continue;

                foreach ($events as $event) {
                    $tsEnd = $event['end'];
                    $tsStart = $event['start'];
                    $tsStartUTC = get_gmt_from_date(date('Y-m-d H:i', $tsStart), 'U');
                    $tsEndUTC = get_gmt_from_date(date('Y-m-d H:i', $tsEnd), 'U');
                    $eventTitle = get_the_title($event['id']);
                    $eventURL = get_the_permalink($event['id']);
                    $eventTitle = '<a href="' . $eventURL . '">' . $eventTitle . '</a>';
                    $location = get_post_meta($event['id'], 'location', TRUE);
                    $vc_url = get_post_meta($event['id'], 'vc-url', TRUE);
                    $allDay = get_post_meta($event['id'], 'all-day', TRUE) == 'on';
                    $startDate = date('Y-m-d', $tsStart);
                    $endDate = date('Y-m-d', $tsEnd);

                    $metaStart = '<meta itemprop="startDate" content="'. date('c', $tsStartUTC) . '" />';
                    $metaEnd = '<meta itemprop="endDate" content="'. date('c', $tsEndUTC) . '" />';

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
                        if ($startDate == $endDate) {
                            // single day
                            if ($allDay) {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart);
                                $timeOut = '';
                            } else {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart);
                                $timeOut = date_i18n(get_option('time_format'), $tsStart) . ' &ndash; ' . date_i18n(get_option('time_format'), $tsEnd). '</span>';
                            }
                        } else {
                            // multiday
                            $timeOut = '';
                            if ($allDay) {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart). ' &ndash; ' . date_i18n(get_option('date_format'), $tsEnd);
                            } else {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart) . ', ' . date_i18n(get_option('time_format'), $tsStart) . ' &ndash; ' . date_i18n(get_option('date_format'), $tsEnd) . ', ' . date_i18n(get_option('time_format'), $tsEnd). '</span>';
                            }
                        }
                        $output .= '<span class="dashicons dashicons-calendar"></span><span class="event-date"> ' . $dateOut
                            . ($timeOut != '' ? '<span class="dashicons dashicons-clock"></span>' . $timeOut : '') . '</span><br />'
                            . '<span class="event-title" itemprop="name">' . $eventTitle . '</span>'
                            . $metaStart
                            . $metaEnd
                            . $metaLocation
                            . $metaAttendance;
                        wp_enqueue_style( 'dashicons' );
                    } else {
                        if ($startDate == $endDate) {
                            // single day
                            $dateOut = '';
                            if ($allDay) {
                                $timeOut = '';
                            } else {
                                $timeOut = date_i18n(get_option('time_format'), $tsStart) . ' &ndash; ' . date_i18n(get_option('time_format'), $tsEnd). '</span>';
                            }
                        } else {
                            // multiday
                            $timeOut = '';
                            if ($allDay) {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart). ' &ndash; ' . date_i18n(get_option('date_format'), $tsEnd);
                            } else {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart) . ', ' . date_i18n(get_option('time_format'), $tsStart) . ' &ndash; ' . date_i18n(get_option('date_format'), $tsEnd) . ', ' . date_i18n(get_option('time_format'), $tsEnd). '</span>';
                            }
                        }
                        $bgColor = '';
                        $categoryObjects = wp_get_object_terms($event['id'], 'rrze-calendar-category');
                        if (!is_wp_error($categoryObjects) && !empty($categoryObjects)) {
                            $cat = $categoryObjects[0];
                            $bgColor = get_term_meta($cat->term_id, 'color', true);
                            $color = $bgColor ? Utils::getContrastYIQ($bgColor) : '#222';
                        }
                        $output .= '<div class="event-date" ' . ($bgColor != '' ? ' style="background-color: ' . $bgColor . '; color: ' . $color . ';"' : '') . '>'
                            . '<div class="day-month">'
                            . '<div class="day">' . date('d', $tsCount) . '</div>'
                            . '<div class="month">' . date_i18n('M', $tsCount) . '</div>'
                            . '</div>'
                            //. '<div class="year">' . date('Y', $tsStart) .'</div>'
                            . '</div>'
                            . '<div class="event-info">'
                            . ($dateOut != '' ? '<div class="event-time">' . $dateOut . '</div>' : '')
                            . ($timeOut != '' ? '<div class="event-time">' . $timeOut . '</div>' : '')
                            . '<div class="event-title" itemprop="name">' . $eventTitle . '</div>'
                            . '<div class="event-location">' . $locationOut . '</div>'
                            . $metaStart
                            . $metaEnd
                            . $metaLocation
                            . $metaAttendance
                            . '</div>';
                    }
                    $output .= '</li>';
                    $IDs[] = $event['id'];
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
                if ($atts['abonnement_link'] === '1') {
                    $buttonLabel = __('Add to calendar', 'rrze-calendar');
                    $catIDs = [];
                    $tagIDs = [];
                    if (is_archive()) {
                        $IDs = [];
                        if (isset($categories)) {
                            foreach ($categories as $category) {
                                $term = get_term_by('slug', $category, CalendarEvent::TAX_CATEGORY);
                                $catIDs[] = $term->term_id;
                            }
                            $buttonLabel = __('Add all events of this category to your calendar', 'rrze-calendar');
                        }
                        if (isset($tags)) {
                            foreach ($tags as $tag) {
                                $term = get_term_by('slug', $tag, CalendarEvent::TAX_TAG);
                                $tagIDs[] = $term->term_id;
                            }
                            $buttonLabel = __('Add all events of this tag to your calendar', 'rrze-calendar');
                        }
                    }
                    $icsArgs = [];
                    if (!empty($IDs)) {
                        $IDs = array_unique($IDs);
                        $icsArgs['ids'] = $IDs;
                    }
                    if (!empty($catIDs)) $icsArgs['cats'] = $catIDs;
                    if (!empty($tagIDs)) $icsArgs['tags'] = $tagIDs;
                    $output .= do_shortcode('[button link=' . Export::makeIcsLink($icsArgs) . ']' . $buttonLabel . '[/button]' );
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
