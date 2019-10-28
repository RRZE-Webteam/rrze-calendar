<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

class Options
{
    /**
     * [protected description]
     * @var string
     */
    protected static $optionName = 'rrze_calendar';

    /**
     * [protected description]
     * @var string
     */
    protected static $versionOptionName = 'rrze_calendar_version';

    /**
     * [defaultOptions description]
     * @return array [description]
     */
    protected static function defaultOptions()
    {
        $options = [
            'endpoint_slug' => 'events',
            'endpoint_name' => 'Events',
            'schedule_event' => 'hourly'
        ];

        return $options;
    }

    /**
     * [getOptions description]
     * @return object [description]
     */
    public static function getOptions()
    {
        $defaults = self::defaultOptions();

        $options = (array) get_option(self::$optionName);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }

    /**
     * [getOptionName description]
     * @return string [description]
     */
    public static function getOptionName()
    {
        return self::$optionName;
    }

    /**
     * [getVersionOptionName description]
     * @return string [description]
     */
    public static function getVersionOptionName()
    {
        return self::$versionOptionName;
    }

}
