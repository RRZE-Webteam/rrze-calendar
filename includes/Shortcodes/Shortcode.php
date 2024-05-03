<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Calendar\Shortcodes\Calendar;
use RRZE\Calendar\Shortcodes\Events;

class Shortcode
{
    const ALLOWED_HTML = [
        'p' => [],
        'a' => [
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
        'blockquote' => []
    ];

    public static function init()
    {
        add_action('init', function () {
            Calendar::init();
            Events::init();
        });
    }

    public static function filterContent($content = null)
    {
        return wpautop(trim(wp_kses($content, self::ALLOWED_HTML)));
    }
}
