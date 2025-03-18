<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

/**
 * Templates class
 * @package RRZE\Calendar
 */
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
            'FAU-Events',
            'FAU-Events-UTN',
        ],
        'rrze' => [
            'rrze-2019'
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
     * CPT Template Path
     * @var string
     */
    protected static $cptTplPath = 'templates/cpt';

    /**
     * CPT CalendarEvent Template
     * @var string
     */
    protected static $cptCalendarEventTpl = 'archive-event.php';

    /**
     * CPT CalendarEvent Single Template
     * @var string
     */
    protected static $cptCalendarEventSingleTpl = 'single-event.php';

    /**
     * Get Template Path
     * @var string
     */
    protected static function getTplPath(string $tpl): string
    {
        switch ($tpl) {
            case 'endpoint':
                $tpl = self::$endpointTplPath;
                break;
            case 'cpt':
                $tpl = self::$cptTplPath;
                break;
            case 'shortcode':
            default:
                $tpl = self::$shortcodesTplPath;
        }
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
     * Get endpoint templates path
     * @return string Endpoint templates path
     */
    public static function getEndpointTplPath()
    {
        return self::getTplPath('endpoint');
    }

    /**
     * Get shortcodes templates path
     * @return string Shortcodes templates path
     */
    public static function getShortcodesTplPath()
    {
        return self::getTplPath('shortcodes');
    }

    /**
     * Get CPT templates path
     * @return string Shortcodes templates path
     */
    public static function getCptTplPath()
    {
        return self::getTplPath('cpt');
    }

    /**
     * Get the endpoint events template name
     * @return string Endpoint events template filename
     */
    public static function endpointEventsTpl()
    {
        return self::$endpointEventsTpl;
    }

    /**
     * Get the endpoint single event template name
     * @return string Endpoint single event template filename
     */
    public static function endpointSingleEventsTpl()
    {
        return self::$endpointSingleEventsTpl;
    }

    /**
     * Get the shortcode events template
     * @return string Endpoint events template
     */
    public static function getEndpointEventsTpl()
    {
        return self::getEndpointTplPath() . self::$endpointEventsTpl;
    }

    /**
     * Get the endpoint single event template
     * @return string Endpoint single event template
     */
    public static function getEndpointSingleEventsTpl()
    {
        return self::getEndpointTplPath() . self::$endpointSingleEventsTpl;
    }

    /**
     * Get Shortcode Events Template
     * @return string Shortcode events template
     */
    public static function getShortcodeEventsTpl()
    {
        return self::getShortcodesTplPath() . self::$shortcodeEventsTpl;
    }

    /**
     * Get Shortcode Calendar Template
     * @return string Shortcode calendar template
     */
    public static function getShortcodeCalendarTpl()
    {
        return self::getShortcodesTplPath() . self::$shortcodeCalendarTpl;
    }

    /**
     * Get CPT Events Template
     * @return string Shortcode events template
     */
    public static function getCptCalendarEventTpl()
    {
        return self::getCptTplPath() . self::$cptCalendarEventTpl;
    }

    /**
     * Get CPT Single Event Template
     * @return string Shortcode events template
     */
    public static function getCptCalendarEventSingleTpl()
    {
        return self::getCptTplPath() . self::$cptCalendarEventSingleTpl;
    }

    /**
     * Get FAU Colors
     * @return array FAU Colors
     */
    public static function getFauColors()
    {
        return apply_filters('rrze_calendar_fau_colors', self::$fauColors);
    }

    /**
     * Get Themes
     * @return array Themes
     */
    public static function getThemes()
    {
        return apply_filters('rrze_calendar_themes', self::$themes);
    }
}
