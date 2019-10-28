<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use DateTimeZone;
use DateTime;
use Exception;

class Util {

    public static function gmtToLocal($timestamp)
    {
        $offset = get_option('gmt_offset');
        $tz = get_option('timezone_string');

        $offset = self::getTimezoneOffset('UTC', $tz, $timestamp);

        if (!$offset) {
            $offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
        }

        return $timestamp + $offset;
    }

    public static function getTimezoneOffset($remote_tz, $origin_tz = null, $timestamp = false)
    {
        if ($timestamp == false) {
            $timestamp = time();
        }

        if ($origin_tz === null) {
            if (!is_string($origin_tz = date_default_timezone_get())) {
                return false;
            }
        }

        try {
            $origin_dtz = new DateTimeZone($origin_tz);
            $remote_dtz = new DateTimeZone($remote_tz);

            if ($origin_dtz == false || $remote_dtz == false) {
                throw new Exception('DateTimeZone Error');
            }

            $origin_dt = new DateTime(gmdate('Y-m-d H:i:s', $timestamp), $origin_dtz);
            $remote_dt = new DateTime(gmdate('Y-m-d H:i:s', $timestamp), $remote_dtz);

            if ($origin_dt == false || $remote_dt == false) {
                throw new Exception('DateTime Error');
            }

            $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
        } catch (Exception $e) {
            return false;
        }

        return $offset;
    }

    public static function getGmDate($timestamp = null)
    {
        if (!$timestamp) {
            $timestamp = time();
        }

        $bits = explode(',', gmdate('s,i,G,j,w,n,Y,z,l,F,U', $timestamp));
        $bits = array_combine(
            array('seconds', 'minutes', 'hours', 'mday', 'wday', 'mon', 'year', 'yday', 'weekday', 'month', 0),
            $bits
        );

        return $bits;
    }

    public static function getShortTime($timestamp, $convertFromGmt = true)
    {
        $timeFormat = get_option('time_format', 'H:i');
        if ($convertFromGmt) {
            $timestamp = self::gmtToLocal($timestamp);
        }
        return date_i18n($timeFormat, $timestamp, true);
    }

    public static function getShortDate($timestamp, $convertFromGmt = true)
    {
        if ($convertFromGmt) {
            $timestamp = self::gmtToLocal($timestamp);
        }
        return date_i18n('j. M', $timestamp, true);
    }

    public static function getYearDate($timestamp, $convertFromGmt = true)
    {
        if ($convertFromGmt) {
            $timestamp = self::gmtToLocal($timestamp);
        }
        return date_i18n('Y', $timestamp, true);
    }

    public static function getMonthDate($timestamp, $convertFromGmt = true)
    {
        if ($convertFromGmt) {
            $timestamp = self::gmtToLocal($timestamp);
        }
        return date_i18n('M', $timestamp, true);
    }

    public static function getDayDate($timestamp, $convertFromGmt = true)
    {
        if ($convertFromGmt) {
            $timestamp = self::gmtToLocal($timestamp);
        }
        return date_i18n('d', $timestamp, true);
    }

    public static function getMediumTime($timestamp, $convertFromGmt = true)
    {
        $timeFormat = get_option('time_format', 'H:i');
        if ($convertFromGmt) {
            $timestamp = self::gmtToLocal($timestamp);
        }
        return date_i18n($timeFormat, $timestamp, true);
    }

    public static function getLongTime($timestamp, $convertFromGmt = true)
    {
        $dateFormat = get_option('date_format', 'l, j. F Y');
        $timeFormat = get_option('time_format', 'H:i');
        if ($convertFromGmt) {
            $timestamp = self::gmtToLocal($timestamp);
        }
        return date_i18n($dateFormat, $timestamp, true) . ' &minus; ' . date_i18n($timeFormat, $timestamp, true);
    }

    public static function getLongDate($timestamp, $convertFromGmt = true)
    {
        $dateFormat = get_option('date_format', 'l, j. F Y');
        if ($convertFromGmt) {
            $timestamp = self::gmtToLocal($timestamp);
        }
        return date_i18n($dateFormat, $timestamp, true);
    }

    public static function getCalendarDates($events)
    {
        $dates = [];

        foreach ($events as $event) {
            $ts = strtotime($event->start);
            if (! $event->allday) {
                $ts = self::gmtToLocal($ts);
            }
            $date = self::getGmDate($ts);
            $timestamp = gmmktime(0, 0, 0, $date['mon'], $date['mday'], $date['year']);
            $dates[$timestamp][] = $event;
        }

        return $dates;
    }

    public static function strToTime($dt, $tz = 'UTC', $noTime = false)
    {
        $dt = new DateTime($dt);
        $dt->setTimeZone(new DateTimezone($tz));

        if (!$noTime) {
            $format = 'Y-m-d H:i:s';
        } else {
            $dt->setTime(0, 0, 0);
            $format = 'Y-m-d';
        }

        return strtotime($dt->format($format));
    }

    public static function daysDiff($tstart, $tend)
    {
        $start_date = new DateTime();
        $start_date->setTimestamp($tstart);
        $start_date->setTime(0, 0, 0);

        $end_date = new DateTime();
        $end_date->setTimestamp($tend);
        $end_date->setTime(0, 0, 0);

        $diff = $start_date->diff($end_date);
        return $diff->days;
    }

    public static function webCalUrl($atts = [])
    {
        $atts = array_merge(
            [
                'plugin' => 'rrze-calendar',
                'action' => 'export',
                'feed-ids' => '',
                'event-ids' => '',
                'cb' => rand()
            ],
            $atts
        );
        return add_query_arg($atts, site_url('/'));
    }    
}