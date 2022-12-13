<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

class Transients
{
    public static function setIcalCache(string $url, string $ical)
    {
        $prefix = parse_url($url, PHP_URL_SCHEME);
        $key = (strpos($url, $prefix) === 0) ? substr($url, strlen($prefix)) : $url;
        $cacheOption = 'ical_' . md5($key);
        $ttl = self::getTtl();
        if (is_multisite()) {
            set_site_transient($cacheOption, $ical, $ttl);
        } else {
            set_transient($cacheOption, $ical, $ttl);
        }
    }

    public static function getIcalCache(string $url)
    {
        $prefix = parse_url($url, PHP_URL_SCHEME);
        $key = (strpos($url, $prefix) === 0) ? substr($url, strlen($prefix)) : $url;
        $cacheOption = 'ical_' . md5($key);
        if (is_multisite()) {
            $ical = get_site_transient($cacheOption);
        } else {
            $ical = get_transient($cacheOption);
        }
        return $ical;
    }

    public static function deleteIcalCache(string $url): bool
    {
        $prefix = parse_url($url, PHP_URL_SCHEME);
        $key = (strpos($url, $prefix) === 0) ? substr($url, strlen($prefix)) : $url;
        $cacheOption = 'ical_' . md5($key);
        if (is_multisite()) {
            $return = delete_site_transient($cacheOption);
        } else {
            $return = delete_transient($cacheOption);
        }
        return $return;
    }

    protected static function getTtl()
    {
        $today = getdate(current_time('timestamp'));
        $weekday = $today['wday']; // 0 - 6 (0=sunday, 6=saturday)
        $hour = $today['hours']; // 0 - 23
        if ($weekday > 0 && $weekday < 6) {
            $m = in_array($hour, [2, 20]) ? 6 : 3;
        } else {
            $m = 6;
        }
        return $m * HOUR_IN_SECONDS;
    }
}
