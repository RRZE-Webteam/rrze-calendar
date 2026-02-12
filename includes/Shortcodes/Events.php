<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Calendar\ICS\Export;
use RRZE\Calendar\Utils;
use RRZE\Calendar\CPT\CalendarEvent;
use function RRZE\Calendar\plugin;

/**
 * Events shortcode handler class.
 *
 * Notes:
 * - Assumes event meta 'start', 'end', 'repeat-lastdate' are stored as UNIX timestamps (int).
 * - All “day” logic is calculated in the WordPress site timezone (wp_timezone()) and is DST-safe.
 * @package RRZE\Calendar\Shortcodes
 */
class Events
{
    /**
     * Initialize shortcode hooks.
     *
     * @return void
     */
    public static function init()
    {
        add_shortcode('rrze-events', [__CLASS__, 'shortcode']);
        add_shortcode('rrze-termine', [__CLASS__, 'shortcode']);
        add_shortcode('events', [__CLASS__, 'shortcode']);
        add_shortcode('termine', [__CLASS__, 'shortcode']);

        add_action('wp_enqueue_scripts', [__CLASS__, 'wpEnqueueScripts']);
    }

    public static function wpEnqueueScripts()
    {
        $assetFile = include plugin()->getPath('build') . 'calendar.asset.php';

        // Register the stylesheet (not enqueued by default)
        wp_register_style(
            'rrze-calendar-sc-events',
            plugins_url('build/events.style.css', plugin()->getBasename()),
            [],
            $assetFile['version']
        );
    }

    /**
     * Shortcode handler.
     *
     * @param array  $atts
     * @param string $content
     * @return string
     */
    public static function shortcode($atts, $content = '')
    {
        wp_enqueue_style('rrze-calendar-sc-events');

        $tz              = wp_timezone();
        $nowTs           = current_time('timestamp'); // WP-consistent "now"
        $todayYmd        = wp_date('Ymd', null, $tz);
        $todayYmdHyphen  = wp_date('Y-m-d', null, $tz);

        $siteDateFormat  = (string) get_option('date_format');
        $siteTimeFormat  = (string) get_option('time_format');

        // Always format in WP timezone (DST-safe)
        $fmt = static function (string $format, int $ts) use ($tz): string {
            return wp_date($format, $ts, $tz);
        };

        // Default attributes (kept for backward compatibility with aliases)
        $attsDefault = [
            'featured'          => 'false',
            'display'           => '',
            'layout'            => '',   // alias to "display"
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
            'start'             => '',
            'end'               => '',
            'include'           => '',
            'exclude'           => '',
        ];
        $atts = shortcode_atts($attsDefault, (array) $atts);

        // Back-compat: allow "layout" to alias "display"
        if ($atts['layout'] !== '') {
            $atts['display'] = $atts['layout'];
        }

        // Render mode
        $display = ($atts['display'] === 'list') ? 'list' : 'teaser';

        // How many to show (default 10)
        $number = absint($atts['number']) ?: absint($atts['count']) ?: absint($atts['anzahl']);
        if ($number < 1) {
            $number = 10;
        }

        // Normalize boolean-ish inputs
        $truthy = ['true', '1', 'yes', 'ja', 'on'];
        $featuredOnly = in_array(strtolower((string) $atts['featured']), $truthy, true);
        $aboLink      = in_array(strtolower((string) $atts['abonnement_link']), $truthy, true);

        // Tax filters (slugs)
        $categoriesRaw   = $atts['categories'] ?: $atts['kategorien'];
        $categoriesSlugs = !empty($categoriesRaw) ? Utils::strListToArray($categoriesRaw, 'sanitize_title') : [];

        $tagsRaw   = $atts['tags'] ?: $atts['schlagworte'];
        $tagsSlugs = !empty($tagsRaw) ? Utils::strListToArray($tagsRaw, 'sanitize_title') : [];

        $taxQuery = ['relation' => 'AND'];
        if (!empty($categoriesSlugs)) {
            $taxQuery[] = [
                'taxonomy' => CalendarEvent::TAX_CATEGORY,
                'field'    => 'slug',
                'terms'    => $categoriesSlugs,
            ];
        }
        if (!empty($tagsSlugs)) {
            $taxQuery[] = [
                'taxonomy' => CalendarEvent::TAX_TAG,
                'field'    => 'slug',
                'terms'    => $tagsSlugs,
            ];
        }

        // Title include/exclude
        $include = sanitize_text_field((string) $atts['include']);
        $exclude = sanitize_text_field((string) $atts['exclude']);

        // Base query args
        $args = [
            'post_type'              => CalendarEvent::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,

            // perf
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,

            // Only events that aren't "finished": repeat-lastdate missing or >= now
            'meta_query' => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key'     => 'repeat-lastdate',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => 'repeat-lastdate',
                        'value'   => $nowTs,
                        'compare' => '>=',
                        'type'    => 'NUMERIC',
                    ],
                ],
            ],

            // Sort by 'start' timestamp ascending (next first)
            'meta_key'   => 'start',
            'orderby'    => 'meta_value_num',
            'meta_type'  => 'NUMERIC',
            'order'      => 'ASC',
        ];

        if ($featuredOnly) {
            $args['meta_query'][] = [
                'key'   => 'featured',
                'value' => 'on',
            ];
        }

        if (count($taxQuery) > 1) {
            $args['tax_query'] = $taxQuery;
        }

        if ($exclude !== '' || $include !== '') {
            add_filter('posts_where', ['RRZE\Calendar\Utils', 'titleFilter'], 10, 2);
            if ($include !== '') {
                $args['title_filter'] = $include;
            }
            if ($exclude !== '') {
                $args['title_filter_exclude'] = $exclude;
            }
            $args['suppress_filters'] = false;
        }

        $events = get_posts($args);

        if ($exclude !== '' || $include !== '') {
            remove_filter('posts_where', ['RRZE\Calendar\Utils', 'titleFilter']);
        }

        $output = '<div class="rrze-calendar">';

        if (empty($events)) {
            $output .= '<p>' . esc_html__('No events scheduled.', 'rrze-calendar') . '</p>';
            $output .= '</div>';
            return $output;
        }

        // Build range: today..+1 year in WP TZ
        $oneYearLaterYmd = (new \DateTimeImmutable('now', $tz))->modify('+1 year')->format('Y-m-d');

        $eventsArray = Utils::buildEventsArray($events, $todayYmdHyphen, $oneYearLaterYmd);
        $eventsArray = is_array($eventsArray) ? $eventsArray : [];
        if (!empty($eventsArray)) {
            ksort($eventsArray);
        }

        if (empty($eventsArray)) {
            $output .= '<p>' . esc_html__('No events scheduled.', 'rrze-calendar') . '</p>';
            $output .= '</div>';
            return $output;
        }

        // List vs teaser
        if ($display === 'list') {
            $ulClass = 'events-list-short';
            wp_enqueue_style('dashicons');
        } else {
            $ulClass = 'events-list';
        }

        // Teaser: expand multi-day events across local calendar days (DST-safe)
        if ($display !== 'list') {
            $expanded = [];

            foreach ($eventsArray as $tsStartKey => $dayEvents) {
                foreach ($dayEvents as $event) {
                    $tsStart = (int) ($event['start'] ?? $tsStartKey);
                    $tsEnd   = (int) ($event['end'] ?? 0);

                    if ($tsStart <= 0 || $tsEnd <= 0) {
                        continue;
                    }

                    $startDay = new \DateTimeImmutable($fmt('Y-m-d', $tsStart) . ' 00:00:00', $tz);
                    $endDay   = new \DateTimeImmutable($fmt('Y-m-d', $tsEnd)   . ' 00:00:00', $tz);

                    for ($d = $startDay; $d <= $endDay; $d = $d->modify('+1 day')) {
                        $expanded[$d->getTimestamp()][] = $event;
                    }
                }
            }

            ksort($expanded);
            $eventsArray = $expanded;
        }

        if (empty($eventsArray)) {
            $output .= '<p>' . esc_html__('No events scheduled.', 'rrze-calendar') . '</p>';
            $output .= '</div>';
            return $output;
        }

        // Render list
        $renderedCount = 0;
        $idsForIcs = [];

        $output .= '<ul class="' . esc_attr($ulClass) . '">';

        foreach ($eventsArray as $tsCount => $eventsOfDay) {
            $tsCount = (int) $tsCount;

            // Teaser: skip past days (WP TZ)
            if ($display === 'teaser' && $fmt('Ymd', $tsCount) < $todayYmd) {
                continue;
            }

            foreach ($eventsOfDay as $event) {
                $eventId = (int) ($event['id'] ?? 0);
                if ($eventId <= 0) {
                    continue;
                }

                $tsStart = (int) ($event['start'] ?? $tsCount);
                $tsEnd   = (int) ($event['end'] ?? 0);
                if ($tsStart <= 0 || $tsEnd <= 0) {
                    continue;
                }

                // List: hide events that ended before today (WP TZ)
                if ($display === 'list' && $fmt('Ymd', $tsEnd) < $todayYmd) {
                    continue;
                }

                $eventTitleRaw = (string) get_the_title($eventId);
                $eventURL      = (string) get_the_permalink($eventId);
                $eventTitle    = '<a href="' . esc_url($eventURL) . '">' . esc_html($eventTitleRaw) . '</a>';

                // Meta
                $location = (string) get_post_meta($eventId, 'location', true);
                $vcUrl    = (string) get_post_meta($eventId, 'vc-url', true);
                $allDay   = (get_post_meta($eventId, 'all-day', true) === 'on');

                // Optional filterContent if Shortcode class exists
                if (class_exists(__NAMESPACE__ . '\\Shortcode')) {
                    $location = Shortcode::filterContent($location);
                }

                $startDate = $fmt('Y-m-d', $tsStart);
                $endDate   = $fmt('Y-m-d', $tsEnd);

                // Microdata: UTC ISO (epoch -> UTC) is correct
                $metaStart = '<meta itemprop="startDate" content="' . esc_attr(gmdate('c', $tsStart)) . '" />';
                $metaEnd   = '<meta itemprop="endDate" content="' . esc_attr(gmdate('c', $tsEnd)) . '" />';

                // Attendance & location meta
                $metaAttendance = '';
                $metaLocation   = '';
                $locationOut    = '';

                if ($location !== '' && $vcUrl === '') {
                    // Offline
                    $metaAttendance = '<meta itemprop="eventAttendanceMode" content="https://schema.org/OfflineEventAttendanceMode" />';
                    $metaLocation   = '<meta itemprop="location" content="' . esc_attr(wp_strip_all_tags($location)) . '" />';
                    $locationOut    = $location;
                } elseif ($location === '' && $vcUrl !== '') {
                    // Online
                    $metaAttendance = '<meta itemprop="eventAttendanceMode" content="https://schema.org/OnlineEventAttendanceMode" />';
                    $metaLocation   = '<span itemprop="location" itemscope itemtype="https://schema.org/VirtualLocation"><meta itemprop="url" content="' . esc_url($vcUrl) . '" /></span>';
                    $locationOut    = esc_html__('Online', 'rrze-calendar');
                } elseif ($location !== '' && $vcUrl !== '') {
                    // Hybrid
                    $metaAttendance = '<meta itemprop="eventAttendanceMode" content="https://schema.org/MixedEventAttendanceMode" />';
                    $metaLocation   = '<meta itemprop="location" content="' . esc_attr(wp_strip_all_tags($location)) . '" />'
                        . '<span itemprop="location" itemscope itemtype="https://schema.org/VirtualLocation"><meta itemprop="url" content="' . esc_url($vcUrl) . '" /></span>';
                    $locationOut    = '<p>' . esc_html(wp_strip_all_tags($location)) . ' / ' . esc_html__('Online', 'rrze-calendar') . '</p>';
                }

                $output .= '<li class="event-item" itemscope itemtype="https://schema.org/Event">';

                if ($display === 'list') {
                    // LIST layout
                    $dateOut = '';
                    $timeOut = '';

                    if ($startDate === $endDate) {
                        // single-day
                        if ($allDay) {
                            $dateOut = $fmt($siteDateFormat, $tsStart);
                        } else {
                            $dateOut = $fmt($siteDateFormat, $tsStart);
                            $timeOut = $fmt($siteTimeFormat, $tsStart) . ' &ndash; ' . $fmt($siteTimeFormat, $tsEnd);
                        }
                    } else {
                        // multi-day
                        if ($allDay) {
                            $dateOut = $fmt($siteDateFormat, $tsStart) . ' &ndash; ' . $fmt($siteDateFormat, $tsEnd);
                        } else {
                            $dateOut = $fmt($siteDateFormat, $tsStart) . ', ' . $fmt($siteTimeFormat, $tsStart)
                                . ' &ndash; '
                                . $fmt($siteDateFormat, $tsEnd) . ', ' . $fmt($siteTimeFormat, $tsEnd);
                        }
                    }

                    $output .= '<span class="dashicons dashicons-calendar"></span>'
                        . '<span class="event-date"> ' . esc_html($dateOut);

                    if ($timeOut !== '') {
                        $output .= ' <span class="dashicons dashicons-clock"></span>' . esc_html($timeOut);
                    }

                    $output .= '</span><br />'
                        . '<span class="event-title" itemprop="name">' . $eventTitle . '</span>'
                        . $metaStart . $metaEnd . $metaLocation . $metaAttendance;
                } else {
                    // TEASER layout
                    $dateOut = '';
                    $timeOut = '';

                    if ($startDate === $endDate) {
                        // single-day
                        if (!$allDay) {
                            $timeOut = $fmt($siteTimeFormat, $tsStart) . ' &ndash; ' . $fmt($siteTimeFormat, $tsEnd);
                        }
                    } else {
                        // multi-day
                        if ($allDay) {
                            $dateOut = $fmt($siteDateFormat, $tsStart) . ' &ndash; ' . $fmt($siteDateFormat, $tsEnd);
                        } else {
                            $dateOut = $fmt($siteDateFormat, $tsStart) . ', ' . $fmt($siteTimeFormat, $tsStart)
                                . ' &ndash; '
                                . $fmt($siteDateFormat, $tsEnd) . ', ' . $fmt($siteTimeFormat, $tsEnd);
                        }
                    }

                    // Category color chip (first term only)
                    $bgColor = '';
                    $color   = '#222';
                    $categoryObjects = wp_get_object_terms($eventId, CalendarEvent::TAX_CATEGORY);
                    if (!is_wp_error($categoryObjects) && !empty($categoryObjects)) {
                        $cat = $categoryObjects[0];
                        $bgColor = (string) get_term_meta($cat->term_id, 'color', true);
                        if ($bgColor !== '') {
                            $color = Utils::getContrastYIQ($bgColor);
                        }
                    }

                    $styleAttr = '';
                    if ($bgColor !== '') {
                        $styleAttr = ' style="background-color:' . esc_attr($bgColor) . '; color:' . esc_attr($color) . ';"';
                    }

                    $output .= '<div class="event-date"' . $styleAttr . '>'
                        .   '<div class="day-month">'
                        .     '<div class="day">' . esc_html($fmt('d', $tsCount)) . '</div>'
                        .     '<div class="month">' . esc_html($fmt('M', $tsCount)) . '</div>'
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

                // Track IDs for ICS button
                $idsForIcs[] = $eventId;

                $renderedCount++;
                if ($renderedCount >= $number) {
                    break 2;
                }
            }
        }

        $output .= '</ul>';

        if ($renderedCount < 1) {
            $output .= '<p>' . esc_html__('No events scheduled.', 'rrze-calendar') . '</p>';
            $output .= '</div>';
            return $output;
        }

        // Optional "Show all events" button
        $pageLinkId = absint($atts['page_link']);
        if ($pageLinkId > 0 && is_string(get_post_status($pageLinkId))) {
            $label = sanitize_text_field((string) $atts['page_link_label']);
            $output .= do_shortcode('[button link="' . esc_url(get_permalink($pageLinkId)) . '"]' . esc_html($label) . '[/button]');
        }

        // Optional ICS subscribe button
        if ($aboLink) {
            $buttonLabel = __('Add to calendar', 'rrze-calendar');

            $catIDs = [];
            $tagIDs = [];

            if (!empty($categoriesSlugs)) {
                foreach ($categoriesSlugs as $categorySlug) {
                    $term = get_term_by('slug', $categorySlug, CalendarEvent::TAX_CATEGORY);
                    if ($term && !is_wp_error($term)) {
                        $catIDs[] = (int) $term->term_id;
                    }
                }
            }

            if (!empty($tagsSlugs)) {
                foreach ($tagsSlugs as $tagSlug) {
                    $term = get_term_by('slug', $tagSlug, CalendarEvent::TAX_TAG);
                    if ($term && !is_wp_error($term)) {
                        $tagIDs[] = (int) $term->term_id;
                    }
                }
            }

            $icsArgs = [];

            // If filtering by cats/tags, prefer those filters (not individual IDs)
            if (!empty($catIDs) || !empty($tagIDs)) {
                if (!empty($catIDs)) {
                    $icsArgs['cats'] = array_values(array_unique($catIDs));
                }
                if (!empty($tagIDs)) {
                    $icsArgs['tags'] = array_values(array_unique($tagIDs));
                }
            } else {
                $idsForIcs = array_values(array_unique(array_map('intval', $idsForIcs)));
                if (!empty($idsForIcs)) {
                    $icsArgs['ids'] = $idsForIcs;
                }
            }

            $icsLink = Export::makeIcsLink($icsArgs);
            $output .= do_shortcode('[button link="' . esc_url($icsLink) . '"]' . esc_html($buttonLabel) . '[/button]');
        }

        $output .= '</div>';

        return $output;
    }
}
