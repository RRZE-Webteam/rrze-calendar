<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Calendar\Shortcodes\Calendar;
use RRZE\Calendar\Shortcodes\Events;

/**
 * Shortcode class
 * @package RRZE\Calendar\Shortcodes
 */
class Shortcode
{
    // Allowed HTML tags
    const ALLOWED_HTML = [
        'p' => [],
        'a' => [
            'itemprop' => [],
            'href' => [],
            'rel' => [],
        ],
        'em' => [],
        'strong' => [],
        'br' => [],
        'ol' => [],
        'ul' => [],
        'li' => [],
        'ins' => [],
        'blockquote' => [],
        'span'  => [
            'itemprop' => [],
            'content'  => [],
        ],
        'meta'  => [
            'itemprop' => [],
            'content'  => [],
        ]
    ];

    /**
     * Initialize the class, registering WordPress hooks
     * @return void
     */
    public static function init()
    {
        add_action('init', function () {
            Calendar::init();
            Events::init();
        });
    }

    /**
     * Filter content
     * @param string $content
     * @return string
     */
    public static function filterContent($content = '')
    {
        return wpautop(trim(wp_kses($content, self::ALLOWED_HTML)));
    }
}
