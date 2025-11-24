<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Calendar\ICS\Export;
use RRZE\Calendar\Utils;
use RRZE\Calendar\CPT\CalendarEvent;

/**
 * Events class
 * @package RRZE\Calendar\Shortcodes
 */
class Events
{
    /**
     * Initialize the class, registering WordPress hooks
     * @return void
     */
    public static function init()
    {
        // Register `rrze-events` shortcode
        add_shortcode('rrze-events', [__CLASS__, 'shortcode']);

        // Register `rrze-termine` shortcode
        add_shortcode('rrze-termine', [__CLASS__, 'shortcode']);

        // Register `events` shortcode
        add_shortcode('events', [__CLASS__, 'shortcode']);

        // Register `termine` shortcode
        add_shortcode('termine', [__CLASS__, 'shortcode']);
    }

    /**
     * Shortcode handler
     * @param array  $atts
     * @param string $content
     * @return string
     */
    public static function shortcode($atts, $content = "")
    {
        // Default attributes (kept for backward compatibility with aliases)
        $attsDefault = [
            'featured'          => 'false',
            'display'           => '',
            'layout'            => '',   // Compatibility with calendar shortcode and block
            'categories'        => '',   // CSV of slugs
            'kategorien'        => '',   // CSV of slugs (DE)
            'tags'              => '',   // CSV of slugs
            'schlagworte'       => '',   // CSV of slugs (DE)
            'count'             => '0',
            'number'            => '0',
            'anzahl'            => '0',
            'page_link'         => '',   // target page ID for “more” button
            'page_link_label'   => __('Show All Events', 'rrze-calendar'),
            'abonnement_link'   => '',   // whether to show ICS/subscribe button
            'start'             => '',   // unused here (Utils::buildEventsArray manages range)
            'end'               => '',
            'include'           => '',
            'exclude'           => '',
        ];
        $atts = shortcode_atts($attsDefault, $atts);

        // Back-compat: allow "layout" to alias "display"
        if ($atts['layout'] !== '') {
            $atts['display'] = $atts['layout'];
        }

        // Render mode
        $display = ($atts['display'] === 'list') ? 'list' : 'teaser';

        // How many to show. Fallback to 10; archive view shows more.
        $number = absint($atts['number']) ?: absint($atts['count']) ?: absint($atts['anzahl']);
        if ($number < 1) {
            $number = 10;
        }

        $IDs = [];

        // Base WP_Query args
        $args = [
            'post_type'              => CalendarEvent::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            // Performance flags (we don't need totals/caches for a shortcode list)
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,

            // Only events that are not finished yet (or have no repeat end)
            'meta_query' => [
                'relation' => 'AND',

                // repeat-lastdate is either empty, or in the future
                [
                    'relation' => 'OR',
                    [
                        'key'     => 'repeat-lastdate',
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key'     => 'repeat-lastdate',
                        'value'   => time(),
                        'compare' => '>='
                    ],
                ],
            ],

            // Sort by meta_key "start" (earliest first)
            'orderby'  => 'meta_key',
            'meta_key' => 'start',
            'order'    => 'DESC',
        ];

        // Featured filter (normalize truthy inputs)
        $featuredTruthy = ['true', '1', 'yes', 'ja', 'on'];
        if (in_array(strtolower((string) $atts['featured']), $featuredTruthy, true)) {
            $args['meta_query'][] = [
                'key'   => 'featured',
                'value' => 'on',
            ];
        }

        // Build a unified tax_query so categories/tags do not overwrite each other
        $taxQuery = ['relation' => 'AND'];

        // Categories (CSV of slugs)
        $categoriesRaw = $atts['categories'] ?: $atts['kategorien'];
        if (!empty($categoriesRaw)) {
            $categories = Utils::strListToArray($categoriesRaw, 'sanitize_title');
            if (!empty($categories)) {
                $taxQuery[] = [
                    'taxonomy' => CalendarEvent::TAX_CATEGORY,
                    'field'    => 'slug',
                    'terms'    => $categories,
                ];
            }
        }

        // Tags (CSV of slugs)
        $tagsRaw = $atts['tags'] ?: $atts['schlagworte'];
        if (!empty($tagsRaw)) {
            $tags = Utils::strListToArray($tagsRaw, 'sanitize_title');
            if (!empty($tags)) {
                $taxQuery[] = [
                    'taxonomy' => CalendarEvent::TAX_TAG,
                    'field'    => 'slug',
                    'terms'    => $tags,
                ];
            }
        }

        if (count($taxQuery) > 1) { // at least one real condition besides 'relation'
            $args['tax_query'] = $taxQuery;
        }

        // Title include/exclude (via WHERE filter)
        $include = sanitize_text_field($atts['include']);
        $exclude = sanitize_text_field($atts['exclude']);
        if ($exclude !== '' || $include !== '') {
            add_filter('posts_where', ['RRZE\Calendar\Utils', 'titleFilter'], 10, 2);
            if ($include !== '') $args['title_filter'] = $include;
            if ($exclude !== '') $args['title_filter_exclude'] = $exclude;
            $args['suppress_filters'] = false;
        }

        // Fetch events
        $events = get_posts($args);

        if ($exclude !== '' || $include !== '') {
            remove_filter('posts_where', ['RRZE\Calendar\Utils', 'titleFilter']);
        }

        $output = '<div class="rrze-calendar">';

        if (!empty($events)) {
            // Build day-indexed structure (today .. +1 year). Ensure array and sort by day.
            $tz = wp_timezone();
            $today        = wp_date('Y-m-d');
            $oneYearLater = (new \DateTime('now', $tz))->modify('+1 year')->format('Y-m-d');

            $eventsArray = Utils::buildEventsArray($events, $today, $oneYearLater);
            $eventsArray = is_array($eventsArray) ? $eventsArray : [];
            if (!empty($eventsArray)) {
                ksort($eventsArray);
            }

            // List vs Teaser configuration (icons only used in 'list')
            if ($display === 'list') {
                $ulClass  = 'events-list-short';
                wp_enqueue_style('dashicons');
            } else {
                $ulClass = 'events-list';
            }

            // In teaser mode, expand multi-day events into all intermediate days
            if ($display !== 'list' && !empty($eventsArray)) {
                $expanded = [];
                foreach ($eventsArray as $tsStart => $dayEvents) {
                    foreach ($dayEvents as $event) {

                        // Add event to its start day
                        $expanded[$tsStart][] = $event;

                        $tsEnd = (int) $event['end'];

                        // Compare start/end dates in the SITE timezone (important for DST)
                        $startYmd = wp_date('Y-m-d', $tsStart);
                        $endYmd   = wp_date('Y-m-d', $tsEnd);

                        // If the event spans multiple days, expand it over each day
                        if ($endYmd !== $startYmd) {

                            // Cursor at next local midnight relative to event start
                            $cursor = $tsStart + DAY_IN_SECONDS;

                            while ($cursor < $tsEnd) {
                                $expanded[$cursor][] = $event;
                                $cursor += DAY_IN_SECONDS;
                            }
                        }
                    }
                }
                ksort($expanded);
                $eventsArray = $expanded;
            }

            // If nothing remains, bail gracefully
            if (empty($eventsArray)) {
                $output .= '<p>' . __('No events scheduled.', 'rrze-calendar') . '</p>';
                $output .= '</div>';
                wp_reset_postdata();
                wp_enqueue_style('rrze-calendar-sc-events');
                return $output;
            }

            // Render list
            $i = 0;
            $output .= '<ul class="' . esc_attr($ulClass) . '">';

            foreach ($eventsArray as $tsCount => $eventsOfDay) {
                // In teaser mode, skip past days
                if ($display === 'teaser' && wp_date('Ymd', $tsCount) < wp_date('Ymd')) {
                    continue;
                }

                foreach ($eventsOfDay as $event) {
                    // In list mode, hide events that already ended
                    if ($display === 'list' && wp_date('Ymd', $event['end']) < wp_date('Ymd')) {
                        continue;
                    }

                    $tsEnd        = (int) $event['end'];
                    $tsStart      = (int) $event['start'];
                    $tsStartUTC = (new \DateTime('@' . $tsStart, wp_timezone()))
                        ->setTimezone(new \DateTimeZone('UTC'))
                        ->getTimestamp();
                    $tsEndUTC = (new \DateTime('@' . $tsEnd, wp_timezone()))
                        ->setTimezone(new \DateTimeZone('UTC'))
                        ->getTimestamp();
                    $eventTitle   = get_the_title($event['id']);
                    $eventURL     = get_the_permalink($event['id']);
                    $eventTitle   = '<a href="' . esc_url($eventURL) . '">' . esc_html($eventTitle) . '</a>';

                    // Location & VC URL
                    $location     = (string) get_post_meta($event['id'], 'location', true);
                    if (class_exists(__NAMESPACE__ . '\\Shortcode')) {
                        // If filterContent is inside this namespace; otherwise import the correct Shortcode FQCN.
                        $location = Shortcode::filterContent($location);
                    }
                    $vcUrl       = (string) get_post_meta($event['id'], 'vc-url', true);
                    $allDay       = (get_post_meta($event['id'], 'all-day', true) === 'on');

                    $startDate = wp_date('Y-m-d', $tsStart);
                    $endDate   = wp_date('Y-m-d', $tsEnd);

                    // Microdata (schema.org/Event)
                    $metaStart = '<meta itemprop="startDate" content="'
                        . esc_attr(wp_date('c', (int) $tsStartUTC, new \DateTimeZone('UTC')))
                        . '" />';

                    $metaEnd = '<meta itemprop="endDate" content="'
                        . esc_attr(wp_date('c', (int) $tsEndUTC, new \DateTimeZone('UTC')))
                        . '" />';

                    // Attendance & location meta
                    if ($location !== '' && $vcUrl === '') {
                        // Offline
                        $metaAttendance = '<meta itemprop="eventAttendanceMode" content="https://schema.org/OfflineEventAttendanceMode" />';
                        $metaLocation   = '<meta itemprop="location" content="' . esc_attr(wp_strip_all_tags($location)) . '">';
                        $locationOut    = $location;
                    } elseif ($location === '' && $vcUrl !== '') {
                        // Online
                        $metaAttendance = '<meta itemprop="eventAttendanceMode" content="https://schema.org/OnlineEventAttendanceMode" />';
                        $metaLocation   = '<span itemprop="location" itemscope itemtype="https://schema.org/VirtualLocation"><meta itemprop="url" content="' . esc_url($vcUrl) . '" /></span>';
                        $locationOut    = esc_html__('Online', 'rrze-calendar');
                    } elseif ($location !== '' && $vcUrl !== '') {
                        // Hybrid
                        $metaAttendance = '<meta itemprop="eventAttendanceMode" content="https://schema.org/MixedEventAttendanceMode" />';
                        $metaLocation   = '<meta itemprop="location" content="' . esc_attr(wp_strip_all_tags($location)) . '">'
                            . '<span itemprop="location" itemscope itemtype="https://schema.org/VirtualLocation"><meta itemprop="url" content="' . esc_url($vcUrl) . '" /></span>';
                        $locationOut    = '<p>' . esc_html(wp_strip_all_tags($location)) . ' / ' . esc_html__('Online', 'rrze-calendar') . '</p>';
                    } else {
                        $metaLocation = '';
                        $metaAttendance = '';
                        $locationOut = '';
                    }

                    // Start rendering
                    $output .= '<li class="event-item" itemscope itemtype="https://schema.org/Event">';

                    if ($display === 'list') {
                        // Date/time formatting for LIST layout
                        if ($startDate === $endDate) {
                            // single-day
                            if ($allDay) {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart);
                                $timeOut = '';
                            } else {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart);
                                $timeOut = date_i18n(get_option('time_format'), $tsStart) . ' &ndash; ' . date_i18n(get_option('time_format'), $tsEnd);
                            }
                        } else {
                            // multi-day
                            $timeOut = '';
                            if ($allDay) {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart) . ' &ndash; ' . date_i18n(get_option('date_format'), $tsEnd);
                            } else {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart) . ', ' . date_i18n(get_option('time_format'), $tsStart)
                                    . ' &ndash; ' . date_i18n(get_option('date_format'), $tsEnd) . ', ' . date_i18n(get_option('time_format'), $tsEnd);
                            }
                        }

                        $output .= '<span class="dashicons dashicons-calendar"></span>'
                            . '<span class="event-date"> ' . esc_html($dateOut)
                            . ($timeOut !== '' ? ' <span class="dashicons dashicons-clock"></span>' . esc_html($timeOut) : '')
                            . '</span><br />'
                            . '<span class="event-title" itemprop="name">' . $eventTitle . '</span>'
                            . $metaStart . $metaEnd . $metaLocation . $metaAttendance;
                    } else {
                        // TEASER layout
                        $dateOut = '';
                        if ($startDate === $endDate) {
                            // single-day
                            $timeOut = $allDay ? '' : (date_i18n(get_option('time_format'), $tsStart) . ' &ndash; ' . date_i18n(get_option('time_format'), $tsEnd));
                        } else {
                            // multi-day
                            $timeOut = '';
                            if ($allDay) {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart) . ' &ndash; ' . date_i18n(get_option('date_format'), $tsEnd);
                            } else {
                                $dateOut = date_i18n(get_option('date_format'), $tsStart) . ', ' . date_i18n(get_option('time_format'), $tsStart)
                                    . ' &ndash; ' . date_i18n(get_option('date_format'), $tsEnd) . ', ' . date_i18n(get_option('time_format'), $tsEnd);
                            }
                        }

                        // Category color chip (first term only)
                        $bgColor = '';
                        $color   = '#222';
                        $categoryObjects = wp_get_object_terms($event['id'], CalendarEvent::TAX_CATEGORY);
                        if (!is_wp_error($categoryObjects) && !empty($categoryObjects)) {
                            $cat = $categoryObjects[0];
                            $bgColor = (string) get_term_meta($cat->term_id, 'color', true);
                            if (!empty($bgColor)) {
                                $color = Utils::getContrastYIQ($bgColor);
                            }
                        }

                        $styleAttr = '';
                        if (!empty($bgColor)) {
                            $styleAttr = ' style="background-color:' . esc_attr($bgColor) . '; color:' . esc_attr($color) . ';"';
                        }

                        $output .= '<div class="event-date"' . $styleAttr . '>'
                            .   '<div class="day-month">'
                            .     '<div class="day">' . esc_html(wp_date('d', $tsCount)) . '</div>'
                            .     '<div class="month">' . esc_html(wp_date('M', $tsCount)) . '</div>'
                            .   '</div>'
                            . '</div>'
                            . '<div class="event-info">'
                            .   ($dateOut !== '' ? '<div class="event-time">' . esc_html($dateOut) . '</div>' : '')
                            .   ($timeOut !== '' ? '<div class="event-time">' . esc_html($timeOut) . '</div>' : '')
                            .   '<div class="event-title" itemprop="name">' . $eventTitle . '</div>'
                            .   '<div class="event-location">' . $locationOut . '</div>'
                            .   $metaStart . $metaEnd . $metaLocation . $metaAttendance
                            . '</div>';
                    }

                    $output .= '</li>';

                    // Track included IDs (for ICS button)
                    $IDs[] = (int) $event['id'];

                    // Respect $number cap
                    $i++;
                    if ($i >= $number) {
                        break 2; // break both foreach loops
                    }
                }
            }

            $output .= '</ul>';

            // If very little output, it likely means nothing matched after filtering
            if (strlen($output) < 100) {
                $output .= '<p>' . __('No events scheduled.', 'rrze-calendar') . '</p>';
            } else {
                // Optional "Show all events" button
                if (is_numeric($atts['page_link']) && is_string(get_post_status((int) $atts['page_link']))) {
                    $label = sanitize_text_field($atts['page_link_label']);
                    $output .= do_shortcode('[button link="' . esc_url(get_permalink((int) $atts['page_link'])) . '"]' . esc_html($label) . '[/button]');
                }

                // Optional ICS subscribe button
                if (!empty($atts['abonnement_link'])) {
                    $buttonLabel = __('Add to calendar', 'rrze-calendar');
                    $catIDs = [];
                    $tagIDs = [];

                    if (!empty($categories)) {
                        $IDs = [];
                        foreach ($categories as $categorySlug) {
                            $term = get_term_by('slug', $categorySlug, CalendarEvent::TAX_CATEGORY);
                            if ($term && !is_wp_error($term)) {
                                $catIDs[] = (int) $term->term_id;
                            }
                        }
                    }

                    if (!empty($tags)) {
                        $IDs = [];
                        foreach ($tags as $tagSlug) {
                            $term = get_term_by('slug', $tagSlug, CalendarEvent::TAX_TAG);
                            if ($term && !is_wp_error($term)) {
                                $tagIDs[] = (int) $term->term_id;
                            }
                        }
                    }

                    $icsArgs = [];
                    if (!empty($IDs)) {
                        $icsArgs['ids'] = array_values(array_unique(array_map('intval', $IDs)));
                    }
                    if (!empty($catIDs)) {
                        $icsArgs['cats'] = $catIDs;
                    }
                    if (!empty($tagIDs)) {
                        $icsArgs['tags'] = $tagIDs;
                    }

                    $icsLink = Export::makeIcsLink($icsArgs);
                    $output .= do_shortcode('[button link="' . esc_url($icsLink) . '"]' . esc_html($buttonLabel) . '[/button]');
                }
            }
        } else {
            $output .= '<p>' . __('No events scheduled.', 'rrze-calendar') . '</p>';
        }

        $output .= '</div>';

        // Cleanup + styles
        wp_reset_postdata();
        wp_enqueue_style('rrze-calendar-sc-events');

        return $output;
    }
}
