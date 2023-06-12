<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

class Templates
{
    /**
     * FAU & RRZE Themes
     * @var array
     */
    protected static $themes = [
        'fau' => [
            'FAU-Einrichtungen',
            'FAU-Einrichtungen-BETA',
            'FAU-Medfak',
            'FAU-RWFak',
            'FAU-Philfak',
            'FAU-Techfak',
            'FAU-Natfak'
        ],
        'fau-events' => [
            'FAU-Events'
        ],
        'rrze' => [
            'rrze-2015'
        ],
        'vendor' => [
            'Francesca-Child'
        ]
    ];

    /**
     * FAU Colors
     * @var array
     */
    protected static $fauColors = [
        '#041E42' => 'default', // FAU
        '#963B2F' => 'phil',    // Phil
        '#662938' => 'rw',      // RW
        '#003E61' => 'med',     // Med
        '#14462D' => 'nat',     // Nat
        '#204251' => 'tf'       // TF
    ];

    /**
     * Endpoint Template Path
     * @var string
     */
    protected static $endpointTplPath = 'templates/endpoint';

    /**
     * Endpoint Events Template
     * @var string
     */
    protected static $endpointEventsTpl = 'events.php';

    /**
     * Single Event Template
     * @var string
     */
    protected static $endpointSingleEventsTpl = 'single-event.php';

    /**
     * Shortcodes Template Path
     * @var string
     */
    protected static $shortcodesTplPath = 'templates/shortcodes';

    /**
     * Shortcode Events Template
     * @var string
     */
    protected static $shortcodeEventsTpl = 'events.php';

    /**
     * Shortcode Calendar Template
     * @var string
     */
    protected static $shortcodeCalendarTpl = 'calendar.php';

    /**
     * Get Template Path
     * @var string
     */
    protected static function getTplPath(string $tpl): string
    {
        $tpl = $tpl != 'endpoint' ? self::$shortcodesTplPath : self::$endpointTplPath;
        $currentTheme = wp_get_theme();
        $tplPath = '';
        foreach (self::getThemes() as $path => $theme) {
            if (in_array(strtolower($currentTheme->stylesheet), array_map('strtolower', $theme))) {
                $tplPath = plugin()->getPath($tpl . '/themes/' . $path);
                break;
            }
        }
        return is_dir($tplPath) ? $tplPath : plugin()->getPath($tpl);
    }

    /**
     * getEndpointPath
     * @return string Endpoint templates path
     */
    public static function getEndpointTplPath()
    {
        return self::getTplPath('endpoint');
    }

    /**
     * getShortcodesTplPath
     * @return string Shortcodes templates path
     */
    public static function getShortcodesTplPath()
    {
        return self::getTplPath('shortcodes');
    }

    /**
     * endpointEventsTemplate
     * @return string Endpoint events template filename
     */
    public static function endpointEventsTpl()
    {
        return self::$endpointEventsTpl;
    }

    /**
     * endpointSingleEventTemplate
     * @return string Endpoint single event template filename
     */
    public static function endpointSingleEventsTpl()
    {
        return self::$endpointSingleEventsTpl;
    }

    /**
     * getEndpointEventsTemplate
     * @return string Endpoint events template
     */
    public static function getEndpointEventsTpl()
    {
        return self::getEndpointTplPath() . self::$endpointEventsTpl;
    }

    /**
     * getEndpointSingleEventsTemplate
     * @return string Endpoint single event template
     */
    public static function getEndpointSingleEventsTpl()
    {
        return self::getEndpointTplPath() . self::$endpointSingleEventsTpl;
    }

    /**
     * getShortcodeEventsTemplate
     * @return string Shortcode events template
     */
    public static function getShortcodeEventsTpl()
    {
        return self::getShortcodesTplPath() . self::$shortcodeEventsTpl;
    }

    /**
     * getShortcodeCalendarTemplate
     * @return string Shortcode calendar template
     */
    public static function getShortcodeCalendarTpl()
    {
        return self::getShortcodesTplPath() . self::$shortcodeCalendarTpl;
    }

    /**
     * getFauColors
     * @return array FAU Colors
     */
    public static function getFauColors()
    {
        return apply_filters('rrze_calendar_fau_colors', self::$fauColors);
    }

    /**
     * getThemes
     * @return array Themes
     */
    public static function getThemes()
    {
        return apply_filters('rrze_calendar_themes', self::$themes);
    }
}
