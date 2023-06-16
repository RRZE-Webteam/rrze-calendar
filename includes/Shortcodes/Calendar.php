<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\Templates;
use RRZE\Calendar\CPT\CalendarEvent;

class Calendar
{
    public static function init()
    {
        add_shortcode('rrze-calendar', [__CLASS__, 'shortcode']);
        add_shortcode('rrze-kalender', [__CLASS__, 'shortcode']);
        add_shortcode('calendar', [__CLASS__, 'shortcode']);
        add_shortcode('kalender', [__CLASS__, 'shortcode']);
    }

    public static function shortcode($atts, $content = "")
    {
        $atts = shortcode_atts(
            [
                'categories' => '',  // Multiple categories (slugs) are separated by commas.
                'kategorien' => '',  // Multiple categories (slugs) are separated by commas.
                'tags' => '',        // Multiple keywords (slugs) are separated by commas.
                'schlagworte' => '', // Multiple keywords (slugs) are separated by commas.
            ],
            $atts
        );

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

        if (!empty($categories)) {
            $taxQuery = [
                [
                    'taxonomy' => CalendarEvent::TAX_CATEGORY,
                    'field'    => 'slug',
                    'terms'    => $categories
                ]
            ];
        }

        if (!empty($tags)) {
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

        if ($taxQuery) {
            $taxQuery = array_merge(['relation' => 'AND'], $taxQuery);
            $args = array_merge($args, ['tax_query' => $taxQuery]);
        }

        $postIds = get_posts($args);
        $data = []; // @todo Data must be obtained using the $postIds
        $template = Templates::getShortcodeCalendarTpl();

        wp_enqueue_style(apply_filters('rrze-calendar-sc-calendar-style', 'rrze-calendar-sc-calendar'));
        wp_enqueue_script('rrze-calendar-sc-calendar');

        return self::output($data, $template);
    }

    protected static function output(&$data, $template)
    {
        ob_start();
        include $template;
        return ob_get_clean();
    }
}
