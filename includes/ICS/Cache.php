<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

/**
 * Cache class
 * @package RRZE\Calendar\ICS
 */
class Cache
{
    /**
     * Set iCal cache
     * @param string $url
     * @param string $ical
     * @return void
     */
    public static function setIcalCache(string $url, string $ical)
    {
        $prefix = parse_url($url, PHP_URL_SCHEME);
        $key = (strpos($url, $prefix) === 0) ? substr($url, strlen($prefix)) : $url;
        $cacheOption = 'ical_' . md5($key);
        $ttl = HOUR_IN_SECONDS;
        if (is_multisite()) {
            set_site_transient($cacheOption, $ical, $ttl);
        } else {
            set_transient($cacheOption, $ical, $ttl);
        }
    }

    /**
     * Get iCal cache
     * @param string $url
     * @return mixed
     */
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

    /**
     * Delete iCal cache
     * @param string $url
     * @return bool
     */
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
}
