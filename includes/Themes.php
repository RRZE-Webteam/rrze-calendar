<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

class Themes
{
    /**
     * [protected description]
     * @var array
     */
    protected static $allowedStylesheets = [
        'fau' => [
            'FAU-Einrichtungen',
            'FAU-Einrichtungen-BETA',
            'FAU-Medfak',
            'FAU-RWFak',
            'FAU-Philfak',
            'FAU-Techfak',
            'FAU-Natfak'
        ],
        'rrze' => [
            'rrze-2015'
        ],
        'fau-events' => [
            'FAU-Events'
        ]
    ];

    /**
     * [protected description]
     * @var string
     */
    protected static $eventsTemplate = 'events.php';

    /**
     * [protected description]
     * @var string
     */
    protected static $singleEventTemplate = 'single-event.php';

    /**
     * [getStyleDir description]
     * @return string [description]
     */
    public static function getStyleDir() {
        $currentTheme = wp_get_theme();

        $styleDir = '';
        foreach (self::$getAllowedStylesheets as $dir => $style) {
            if (in_array(strtolower($currentTheme->stylesheet), array_map('strtolower', $style))) {
                $styleDir = dirname(RRZE_PLUGIN_FILE) . "/templates/themes/$dir/";
                break;
            }
        }

        return is_dir($styleDir) ? $styleDir : dirname(RRZE_PLUGIN_FILE) . '/templates/';
    }

    /**
     * [getAllowedStylesheets description]
     * @return array [description]
     */
    public static function getAllowedStylesheets() {
        return self::$allowedStylesheets;
    }

    /**
     * [getEventsTemplate description]
     * @return string [description]
     */
    public static function getEventsTemplate() {
        return self::$eventsTemplate;
    }

    /**
     * [getSingleEventTemplate description]
     * @return string [description]
     */
    public static function getSingleEventTemplate() {
        return self::$singleEventTemplate;
    }
}
