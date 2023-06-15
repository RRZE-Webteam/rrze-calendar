<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\Templates;
use RRZE\Calendar\CPT\CalendarEvent;
use RRZE\Calendar\Events as MainEvents;

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
        $atts = shortcode_atts(
            [
                'categories' => '',  // Multiple categories (slugs) are separated by commas
                'kategorien' => '',  // Multiple categories (slugs) are separated by commas
                'tags' => '',        // Multiple keywords (slugs) are separated by commas
                'schlagworte' => '', // Multiple keywords (slugs) are separated by commas
                'count' => 0,       // Number of events to show. Default value: 0
                'anzahl' => 0,      // Number of events to show. Default value: 0
                'page_link' => 0,    // ID of a target page, e.g. to display further events
                'start' => '',       // Start date of appointment list. Format: "Y-m-d" or use a PHP relative date format
                'end' => ''          // End date of appointment listing. Format: "Y-m-d" or use a PHP relative date format
            ],
            $atts
        );

        // Count settings
        if (!$limit = absint($atts['count'])) {
            $limit = absint($atts['anzahl']);
        }
        if ($limit < 1) {
            $limit = 10;
        }

        // @todo Start Date & Limit Days settings (This is not yet available ;)
        $currentTimestamp = current_time('timestamp');
        $startDateAtt = trim($atts['start']);
        $startDate = $startDateAtt ? strtotime(get_gmt_from_date($startDateAtt)) : $currentTimestamp;
        $endDateAtt = trim($atts['end']);
        $endDate = $endDateAtt ? strtotime(get_gmt_from_date($endDateAtt)) : 0;
        if ($endDate) {
            $limitDays = ($endDate - $startDate) < 7 * DAY_IN_SECONDS ? 7 : round(abs($endDate - $startDate) / DAY_IN_SECONDS);
        } else {
            $limitDays = 365;
        }
        $startDate = date('Ymd', $startDate);

        // Tax settings
        $categories = [];
        $tags = [];
        $taxQuery = [];

        $tax = '';
        if ($atts['categories']) {
            $tax = $atts['categories'];
        } elseif ($atts['kategorien']) {
            $tax = $atts['kategorien'];
        }
        $categories = Utils::strListToArray($tax, 'sanitize_title');

        $tax = '';
        if ($atts['tags']) {
            $tax = $atts['tags'];
        } elseif ($atts['schlagworte']) {
            $tax = $atts['schlagworte'];
        }
        $tags = Utils::strListToArray($tax, 'sanitize_title');

        if ($categories) {
            $taxQuery = [
                [
                    'taxonomy' => CalendarEvent::TAX_CATEGORY,
                    'field'    => 'slug',
                    'terms'    => $categories
                ]
            ];
        }

        if ($tags) {
            $taxQuery = array_merge(
                $taxQuery,
                [
                    [
                        'taxonomy' => CalendarEvent::TAX_TAG,
                        'field'    => 'slug',
                        'terms'    => $tags
                    ]
                ]
            );
        }

        $args = [
            'fields'      => 'ids',
            'numberposts' => -1,
            'post_type'   => CalendarEvent::POST_TYPE,
            'post_status' => 'publish'
        ];

        if (!empty($taxQuery)) {
            $taxQuery = array_merge(['relation' => 'AND'], $taxQuery);
            $args = array_merge($args, ['tax_query' => $taxQuery]);
        }

        $postIds = get_posts($args);

        wp_enqueue_style(apply_filters('rrze-calendar-sc-calendar-style', 'rrze-calendar-sc-calendar'));

        $data = MainEvents::getItemsFromFeedIds($postIds);
        $template = Templates::getShortcodeEventsTpl();

        return self::output($template, $data, $limit);
    }

    protected static function output($template, &$data, $limit)
    {
        ob_start();
        include $template;
        return ob_get_clean();
    }
}
