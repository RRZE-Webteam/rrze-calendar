<?php

class RRZE_Calendar_Functions
{
    private static $windows_timezones = [
        "Africa/Bangui" => "W. Central Africa Standard Time",
        "Africa/Cairo" => "Egypt Standard Time",
        "Africa/Casablanca" => "Morocco Standard Time",
        "Africa/Harare" => "South Africa Standard Time",
        "Africa/Johannesburg" => "South Africa Standard Time",
        "Africa/Lagos" => "W. Central Africa Standard Time",
        "Africa/Monrovia" => "Greenwich Standard Time",
        "Africa/Nairobi" => "E. Africa Standard Time",
        "Africa/Windhoek" => "Namibia Standard Time",
        "America/Anchorage" => "Alaskan Standard Time",
        "America/Argentina/San_Juan" => "Argentina Standard Time",
        "America/Asuncion" => "Paraguay Standard Time",
        "America/Bahia" => "Bahia Standard Time",
        "America/Bogota" => "SA Pacific Standard Time",
        "America/Buenos_Aires" => "Argentina Standard Time",
        "America/Caracas" => "Venezuela Standard Time",
        "America/Cayenne" => "SA Eastern Standard Time",
        "America/Chicago" => "Central Standard Time",
        "America/Chihuahua" => "Mountain Standard Time (Mexico)",
        "America/Cuiaba" => "Central Brazilian Standard Time",
        "America/Denver" => "Mountain Standard Time",
        "America/Fortaleza" => "SA Eastern Standard Time",
        "America/Godthab" => "Greenland Standard Time",
        "America/Guatemala" => "Central America Standard Time",
        "America/Halifax" => "Atlantic Standard Time",
        "America/Indianapolis" => "US Eastern Standard Time",
        "America/La_Paz" => "SA Western Standard Time",
        "America/Los_Angeles" => "Pacific Standard Time",
        "America/Mexico_City" => "Mexico Standard Time",
        "America/Montevideo" => "Montevideo Standard Time",
        "America/New_York" => "Eastern Standard Time",
        "America/Noronha" => "UTC-02",
        "America/Phoenix" => "US Mountain Standard Time",
        "America/Regina" => "Canada Central Standard Time",
        "America/Santa_Isabel" => "Pacific Standard Time (Mexico)",
        "America/Santiago" => "Pacific SA Standard Time",
        "America/Sao_Paulo" => "E. South America Standard Time",
        "America/St_Johns" => "Newfoundland Standard Time",
        "America/Tijuana" => "Pacific Standard Time",
        "Antarctica/McMurdo" => "New Zealand Standard Time",
        "Atlantic/South_Georgia" => "UTC-02",
        "Asia/Almaty" => "Central Asia Standard Time",
        "Asia/Amman" => "Jordan Standard Time",
        "Asia/Baghdad" => "Arabic Standard Time",
        "Asia/Baku" => "Azerbaijan Standard Time",
        "Asia/Bangkok" => "SE Asia Standard Time",
        "Asia/Beirut" => "Middle East Standard Time",
        "Asia/Calcutta" => "India Standard Time",
        "Asia/Colombo" => "Sri Lanka Standard Time",
        "Asia/Damascus" => "Syria Standard Time",
        "Asia/Dhaka" => "Bangladesh Standard Time",
        "Asia/Dubai" => "Arabian Standard Time",
        "Asia/Irkutsk" => "North Asia East Standard Time",
        "Asia/Jerusalem" => "Israel Standard Time",
        "Asia/Kabul" => "Afghanistan Standard Time",
        "Asia/Kamchatka" => "Kamchatka Standard Time",
        "Asia/Karachi" => "Pakistan Standard Time",
        "Asia/Katmandu" => "Nepal Standard Time",
        "Asia/Kolkata" => "India Standard Time",
        "Asia/Krasnoyarsk" => "North Asia Standard Time",
        "Asia/Kuala_Lumpur" => "Singapore Standard Time",
        "Asia/Kuwait" => "Arab Standard Time",
        "Asia/Magadan" => "Magadan Standard Time",
        "Asia/Muscat" => "Arabian Standard Time",
        "Asia/Novosibirsk" => "N. Central Asia Standard Time",
        "Asia/Oral" => "West Asia Standard Time",
        "Asia/Rangoon" => "Myanmar Standard Time",
        "Asia/Riyadh" => "Arab Standard Time",
        "Asia/Seoul" => "Korea Standard Time",
        "Asia/Shanghai" => "China Standard Time",
        "Asia/Singapore" => "Singapore Standard Time",
        "Asia/Taipei" => "Taipei Standard Time",
        "Asia/Tashkent" => "West Asia Standard Time",
        "Asia/Tbilisi" => "Georgian Standard Time",
        "Asia/Tehran" => "Iran Standard Time",
        "Asia/Tokyo" => "Tokyo Standard Time",
        "Asia/Ulaanbaatar" => "Ulaanbaatar Standard Time",
        "Asia/Vladivostok" => "Vladivostok Standard Time",
        "Asia/Yakutsk" => "Yakutsk Standard Time",
        "Asia/Yekaterinburg" => "Ekaterinburg Standard Time",
        "Asia/Yerevan" => "Armenian Standard Time",
        "Atlantic/Azores" => "Azores Standard Time",
        "Atlantic/Cape_Verde" => "Cape Verde Standard Time",
        "Atlantic/Reykjavik" => "Greenwich Standard Time",
        "Australia/Adelaide" => "Cen. Australia Standard Time",
        "Australia/Brisbane" => "E. Australia Standard Time",
        "Australia/Darwin" => "AUS Central Standard Time",
        "Australia/Hobart" => "Tasmania Standard Time",
        "Australia/Perth" => "W. Australia Standard Time",
        "Australia/Sydney" => "AUS Eastern Standard Time",
        "Etc/GMT" => "UTC",
        "Etc/GMT+11" => "UTC-11",
        "Etc/GMT+12" => "Dateline Standard Time",
        "Etc/GMT+2" => "UTC-02",
        "Etc/GMT-12" => "UTC+12",
        "Europe/Amsterdam" => "W. Europe Standard Time",
        "Europe/Athens" => "GTB Standard Time",
        "Europe/Belgrade" => "Central Europe Standard Time",
        "Europe/Berlin" => "W. Europe Standard Time",
        "Europe/Berlin" => "(UTC+01:00) Amsterdam, Berlin, Bern, Rom, Stockholm, Wien",
        "Europe/Brussels" => "Romance Standard Time",
        "Europe/Budapest" => "Central Europe Standard Time",
        "Europe/Dublin" => "GMT Standard Time",
        "Europe/Helsinki" => "FLE Standard Time",
        "Europe/Istanbul" => "GTB Standard Time",
        "Europe/Kiev" => "FLE Standard Time",
        "Europe/London" => "GMT Standard Time",
        "Europe/Minsk" => "E. Europe Standard Time",
        "Europe/Moscow" => "Russian Standard Time",
        "Europe/Paris" => "Romance Standard Time",
        "Europe/Sarajevo" => "Central European Standard Time",
        "Europe/Warsaw" => "Central European Standard Time",
        "Indian/Mauritius" => "Mauritius Standard Time",
        "Pacific/Apia" => "Samoa Standard Time",
        "Pacific/Auckland" => "New Zealand Standard Time",
        "Pacific/Fiji" => "Fiji Standard Time",
        "Pacific/Guadalcanal" => "Central Pacific Standard Time",
        "Pacific/Guam" => "West Pacific Standard Time",
        "Pacific/Honolulu" => "Hawaiian Standard Time",
        "Pacific/Pago_Pago" => "UTC-11",
        "Pacific/Port_Moresby" => "West Pacific Standard Time",
        "Pacific/Tongatapu" => "Tonga Standard Time"
    ];

    public static function time_array_to_timestamp($t, $def_timezone)
    {
        $date_str = sprintf('%s-%s-%s', $t['value']['year'], $t['value']['month'], $t['value']['day']);

        if (isset($t['value']['hour'])) {
            $date_str .= sprintf(' %s:%s:%s', $t['value']['hour'], $t['value']['min'], $t['value']['sec']);
        }

        $timezone = '';
        if (isset($t['value']['tz']) && $t['value']['tz'] == 'Z') {
            $timezone = 'Z';
        }
        /*
        elseif (isset($t['params']['TZID'])) {
            $key = array_search($t['params']['TZID'], self::$windows_timezones);

            if ($key !== false) {
                $timezone = $key;
            } else {
                $timezone = $t['params']['TZID'];
            }
        }
        */
        if (empty($timezone)) {
            $timezone = $def_timezone;
        }

        if ($timezone) {
            $date_str .= ' ' . $timezone;
        }

        return strtotime($date_str);
    }

    public static function gmt_to_local($timestamp)
    {
        $offset = get_option('gmt_offset');
        $tz = get_option('timezone_string');

        $offset = self::get_timezone_offset('UTC', $tz, $timestamp);

        if (!$offset) {
            $offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
        }

        return $timestamp + $offset;
    }

    public static function gmgetdate($timestamp = null)
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

    public static function local_to_gmt($timestamp)
    {
        $offset = get_option('gmt_offset');
        $tz = get_option('timezone_string', 'Europe/Berlin');

        $offset = self::get_timezone_offset('UTC', $tz, $timestamp);

        if (!$offset) {
            $offset = get_option('gmt_offset') * 3600;
        }

        return $timestamp - $offset;
    }

    public static function get_timezone_offset($remote_tz, $origin_tz = null, $timestamp = false)
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

    public static function get_short_time($timestamp, $convert_from_gmt = true)
    {
        $time_format = get_option('time_format', 'H:i');
        if ($convert_from_gmt) {
            $timestamp = self::gmt_to_local($timestamp);
        }
        return date_i18n($time_format, $timestamp, true);
    }

    public static function get_short_date($timestamp, $convert_from_gmt = true)
    {
        if ($convert_from_gmt) {
            $timestamp = self::gmt_to_local($timestamp);
        }
        return date_i18n('j. M', $timestamp, true);
    }

    public static function get_year_date($timestamp, $convert_from_gmt = true)
    {
        if ($convert_from_gmt) {
            $timestamp = self::gmt_to_local($timestamp);
        }
        return date_i18n('Y', $timestamp, true);
    }

    public static function get_month_date($timestamp, $convert_from_gmt = true)
    {
        if ($convert_from_gmt) {
            $timestamp = self::gmt_to_local($timestamp);
        }
        return date_i18n('M', $timestamp, true);
    }

    public static function get_day_date($timestamp, $convert_from_gmt = true)
    {
        if ($convert_from_gmt) {
            $timestamp = self::gmt_to_local($timestamp);
        }
        return date_i18n('d', $timestamp, true);
    }

    public static function get_medium_time($timestamp, $convert_from_gmt = true)
    {
        $time_format = get_option('time_format', 'H:i');
        if ($convert_from_gmt) {
            $timestamp = self::gmt_to_local($timestamp);
        }
        return date_i18n($time_format, $timestamp, true);
    }

    public static function get_long_time($timestamp, $convert_from_gmt = true)
    {
        $date_format = get_option('date_format', 'l, j. F Y');
        $time_format = get_option('time_format', 'H:i');
        if ($convert_from_gmt) {
            $timestamp = self::gmt_to_local($timestamp);
        }
        return date_i18n($date_format, $timestamp, true) . ' &minus; ' . date_i18n($time_format, $timestamp, true);
    }

    public static function get_long_date($timestamp, $convert_from_gmt = true)
    {
        $date_format = get_option('date_format', 'l, j. F Y');
        if ($convert_from_gmt) {
            $timestamp = self::gmt_to_local($timestamp);
        }
        return date_i18n($date_format, $timestamp, true);
    }

    public static function get_calendar_dates($events)
    {
        $dates = array();

        foreach ($events as $event) {
            $date = self::gmt_to_local($event->start);
            $date = self::gmgetdate($date);
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

    public static function days_diff($tstart, $tend)
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
}
