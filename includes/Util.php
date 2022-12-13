<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use DateTimeZone;
use DateTime;
use Exception;

class Util
{

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
            if (!$event->allday) {
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

    public static function getParam($param, $default = '')
    {
        if (isset($_POST[$param])) {
            return $_POST[$param];
        }

        if (isset($_GET[$param])) {
            return $_GET[$param];
        }

        return $default;
    }

    public static function flashAdminNotice($message, $class = '')
    {
        if (!($currentUser = get_current_user_id())) {
            return;
        }
        $defaultAllowedClasses = array('error', 'updated');
        $allowedClasses = apply_filters('admin_notices_allowed_classes', $defaultAllowedClasses);
        $defaultClass = apply_filters('admin_notices_default_class', 'updated');

        if (!in_array($class, $allowedClasses)) {
            $class = $defaultClass;
        }

        $transient = sprintf('rrze_calendar_flash_admin_notices_%s', $currentUser);
        $transientValue = get_transient($transient);
        $notices = maybe_unserialize($transientValue ? $transientValue : []);
        $notices[$class][] = $message;

        set_transient($transient, $notices, 60);
    }

    public static function showFlashAdminNotices()
    {
        if (!($currentUser = get_current_user_id())) {
            return;
        }
        $transient = sprintf('rrze_calendar_flash_admin_notices_%s', $currentUser);
        $transientValue = get_transient($transient);
        $notices = maybe_unserialize($transientValue ? $transientValue : '');

        if (is_array($notices)) {
            foreach ($notices as $class => $messages) {
                foreach ($messages as $message) {
                    printf('<div class="%1$s">%2$s', $class, PHP_EOL);
                    printf('<p>%1$s</p>%2$s', $message, PHP_EOL);
                    echo '</div>', PHP_EOL;
                }
            }
        }

        delete_transient($transient);
    }

    /**
     * Limiting or truncating a string.
     *
     * @param string $string
     * @param integer $limit
     * @param string $more
     * @param boolean $split
     * @return string
     */
    public static function truncateStr(string $string, int $limit, bool $split = false, string $more = '...'): string
    {
        if (strlen($string) <= $limit) {
            return $string;
        } else {
            $str = substr($string, 0, $limit) . $more;
            $string = $split ? $str . substr($string, -$limit) : $str;
        }
        return $string;
    }

    /**
     * Sanitize hexadecimal color value
     *
     * @param string $color
     * @return string
     */
    public static function sanitizeHexColor(string $color): string
    {
        if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color)) {
            return $color;
        } else {
            return '';
        }
    }

    /**
     * Timezone id validation.
     *
     * @param string $tzid
     * @return boolean
     */
    public static function isValidTimezoneID(string $tzid): bool
    {
        if (empty($tzid)) {
            return false;
        }
        foreach (timezone_abbreviations_list() as $zone) {
            foreach ($zone as $item) {
                if ($item['timezone_id'] == $tzid) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get formated day of a week.
     *
     * @param string $format
     * @return array
     */
    public static function getDaysOfWeek(string $format = ''): array
    {
        $daysOfWeek = self::daysOfWeek($format);

        // Shift sequence of days based on site configuration
        $startOfWeek = get_option('start_of_week', 0);
        for ($i = 0; $i < $startOfWeek; $i++) {
            $day = $daysOfWeek[$i];
            unset($daysOfWeek[$i]);
            $daysOfWeek[$i] = $day;
        }

        return $daysOfWeek;
    }

    /**
     * Formated day of a week.
     *
     * @param string $format
     * @return array
     */
    public static function daysOfWeek(string $format = ''): array
    {
        global $wp_locale;

        $daysOfWeek = [];

        switch ($format) {
            case 'min':
                $daysOfWeek = [
                    0 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(0)),
                    1 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(1)),
                    2 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(2)),
                    3 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(3)),
                    4 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(4)),
                    5 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(5)),
                    6 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(6)),
                ];
                break;
            case 'short':
                $daysOfWeek = [
                    0 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(0)),
                    1 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(1)),
                    2 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(2)),
                    3 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(3)),
                    4 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(4)),
                    5 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(5)),
                    6 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(6)),
                ];
                break;
            case 'full':
            default:
                $daysOfWeek = [
                    0 => $wp_locale->get_weekday(0),
                    1 => $wp_locale->get_weekday(1),
                    2 => $wp_locale->get_weekday(2),
                    3 => $wp_locale->get_weekday(3),
                    4 => $wp_locale->get_weekday(4),
                    5 => $wp_locale->get_weekday(5),
                    6 => $wp_locale->get_weekday(6),
                ];
                break;
        }
        return $daysOfWeek;
    }

    /**
     * Get first day of current week/month/year.
     *
     * @param string $interval
     * @return integer|boolean
     */
    public static function firstDayOfCurrent(string $interval)
    {
        $firstDay = false;
        switch ($interval) {
            case 'year':
                $firstDay = gmmktime(0, 0, 0, 1, 1, date('Y'));
                break;
            case 'week':
                $startOfWeek = get_option('start_of_week', 0);
                $currentDay = date('w');
                $daysOffset = $currentDay - $startOfWeek;
                if ($daysOffset < 0) {
                    $daysOffset = $daysOffset + 7;
                }
                $firstDay = gmmktime(0, 0, 0, date('n'), date('j') - $daysOffset, date('Y'));
                break;
            case 'month':
            default:
                $firstDay = gmmktime(0, 0, 0, date('n'), 1, date('Y'));
                break;
        }
        return $firstDay;
    }

    /**
     * Time formatter that will take a basic time string and convert it to a time in the desired format.
     *
     * @param string $time_string
     * @param string $format
     * @return string
     */
    public static function timeFormat($time_string, $format = '')
    {
        $output = '';
        // Get time format from WP settings if not passed in
        if (empty($format)) {
            $format = get_option('time_format');
        }
        // Strip unsupported format elements from string (a temporary workaround until these can be supported)
        $format = trim(preg_replace('/[BsueOPTZ]/', '', $format));
        // Get digits from time string.
        $time_digits = preg_replace('/[^0-9]+/', '', $time_string);
        // Get am/pm from time string.
        $time_ampm = preg_replace('/[^amp]+/', '', strtolower($time_string));
        if ($time_ampm != 'am' && $time_ampm != 'pm') {
            $time_ampm = '';
        }
        // Prepend zero to digits if length is odd
        if (strlen($time_digits) % 2 == 1) {
            $time_digits = '0' . $time_digits;
        }
        // Get hour, minutes and seconds from time digits
        $time_h = substr($time_digits, 0, 2);
        $time_m = substr($time_digits, 2, 2);
        $time_s = strlen($time_digits) == 6 ? substr($time_digits, 4, 2) : '';
        // Convert hour to correct 24-hour value if needed
        if ($time_ampm == 'pm') {
            $time_h = (int)$time_h + 12;
        }
        if ($time_ampm == 'am' && $time_h == '12') {
            $time_h = '00';
        }
        // Determine am/pm if not passed in
        if (empty($time_ampm)) {
            $time_ampm = (int)$time_h >= 12 ? 'pm' : 'am';
        }
        // Get 12-hour version of hour
        $time_h12 = (int)$time_h % 12;
        if ($time_h12 == 0) {
            $time_h12 = 12;
        }
        if ($time_h12 < 10) {
            $time_h12 = '0' . (string)$time_h12;
        }
        // Convert am/pm abbreviations for Greek language
        if (get_locale() == 'el') {
            $time_ampm = ($time_ampm == 'am') ? 'πμ' : 'μμ';
        }
        // Format output
        switch ($format) {
                // 12-hour formats without seconds
            case 'g:i a':
                $output = intval($time_h12) . ':' . $time_m . '&nbsp;' . $time_ampm;
                break;
            case 'g:ia':
                $output = intval($time_h12) . ':' . $time_m . $time_ampm;
                break;
            case 'g:i A':
                $output = intval($time_h12) . ':' . $time_m . '&nbsp;' . strtoupper($time_ampm);
                break;
            case 'g:iA':
                $output = intval($time_h12) . ':' . $time_m . strtoupper($time_ampm);
                break;
            case 'h:i a':
                $output = $time_h12 . ':' . $time_m . '&nbsp;' . $time_ampm;
                break;
            case 'h:ia':
                $output = $time_h12 . ':' . $time_m . $time_ampm;
                break;
            case 'h:i A':
                $output = $time_h12 . ':' . $time_m . '&nbsp;' . strtoupper($time_ampm);
                break;
            case 'h:iA':
                $output = $time_h12 . ':' . $time_m . strtoupper($time_ampm);
                break;
                // 24-hour formats without seconds
            case 'G:i':
                $output = intval($time_h) . ':' . $time_m;
                break;
            case 'Gi':
                $output = intval($time_h) . $time_m;
                break;
                // case 'H:i': is the default, below
            case 'Hi':
                $output = $time_h . $time_m;
                break;
                // 24-hour formats without seconds, using h and m or min
            case 'G \h i \m\i\n':
                $output = intval($time_h) . '&nbsp;h&nbsp;' . $time_m . '&nbsp;min';
                break;
            case 'G\h i\m\i\n':
                $output = intval($time_h) . 'h&nbsp;' . $time_m . 'min';
                break;
            case 'G\hi\m\i\n':
                $output = intval($time_h) . 'h' . $time_m . 'min';
                break;
            case 'G \h i \m':
                $output = intval($time_h) . '&nbsp;h&nbsp;' . $time_m . '&nbsp;m';
                break;
            case 'G\h i\m':
                $output = intval($time_h) . 'h&nbsp;' . $time_m . 'm';
                break;
            case 'G\hi\m':
                $output = intval($time_h) . 'h' . $time_m . 'm';
                break;
            case 'H \h i \m\i\n':
                $output = $time_h . '&nbsp;h&nbsp;' . $time_m . '&nbsp;min';
                break;
            case 'H\h i\m\i\n':
                $output = $time_h . 'h&nbsp;' . $time_m . 'min';
                break;
            case 'H\hi\m\i\n':
                $output = $time_h . 'h' . $time_m . 'min';
                break;
            case 'H \h i \m':
                $output = $time_h . '&nbsp;h&nbsp;' . $time_m . '&nbsp;m';
                break;
            case 'H\h i\m':
                $output = $time_h . 'h&nbsp;' . $time_m . 'm';
                break;
            case 'H\hi\m':
                $output = $time_h . 'h' . $time_m . 'm';
                break;
                // 12-hour formats with seconds
            case 'g:i:s a':
                $output = intval($time_h12) . ':' . $time_m . ':' . $time_s . '&nbsp;' . $time_ampm;
                break;
            case 'g:i:sa':
                $output = intval($time_h12) . ':' . $time_m . ':' . $time_s . $time_ampm;
                break;
            case 'g:i:s A':
                $output = intval($time_h12) . ':' . $time_m . ':' . $time_s . '&nbsp;' . strtoupper($time_ampm);
                break;
            case 'g:i:sA':
                $output = intval($time_h12) . ':' . $time_m . ':' . $time_s . strtoupper($time_ampm);
                break;
            case 'h:i:s a':
                $output = $time_h12 . ':' . $time_m . ':' . $time_s . '&nbsp;' . $time_ampm;
                break;
            case 'h:i:sa':
                $output = $time_h12 . ':' . $time_m . ':' . $time_s . $time_ampm;
                break;
            case 'h:i:s A':
                $output = $time_h12 . ':' . $time_m . ':' . $time_s . '&nbsp;' . strtoupper($time_ampm);
                break;
            case 'h:i:sA':
                $output = $time_h12 . ':' . $time_m . ':' . $time_s . strtoupper($time_ampm);
                break;
                // 24-hour formats with seconds
            case 'G:i:s':
                $output = intval($time_h) . ':' . $time_m . ':' . $time_s;
                break;
            case 'H:i:s':
                $output = $time_h . ':' . $time_m . ':' . $time_s;
                break;
            case 'His':
                $output = $time_h . $time_m . $time_s;
                break;
                // Default
            case 'H:i':
            default:
                $output = $time_h . ':' . $time_m;
                break;
        }
        // Return output
        return $output;
    }

    /**
     * Fix where lines are not properly folded.
     * 
     * @link https://icalendar.org/iCalendar-RFC-5545/3-1-content-lines.html
     * @param string $content
     * @return string
     */
    public static function fixLineBreak($content)
    {
        $lines = explode("\r\n", $content);
        $replaceContent = false;
        $prev = null;
        foreach ((array)$lines as $key => $line) {
            preg_match('/([A-Z]+[:;])/', $line, $matches, PREG_OFFSET_CAPTURE);
            if (!isset($matches[1][1]) || $matches[1][1] !== 0) {
                $lines[$key] = ' ' . trim($line);
                if (!empty($prev)) {
                    $prevAry = null;
                    if (strpos(trim($prev), ' ') !== false) {
                        $prevAry = explode(' ', trim($prev));
                    } elseif (strpos(trim($prev), "\\n") !== false) {
                        $prevAry = explode("\\n", trim($prev));
                    }
                    if (!empty($prevAry)) {
                        $prevCount = count($prevAry);
                        if ($prevCount > 1 && strpos($prevAry[$prevCount - 1], 'http') === false) {
                            $lines[$key] = ' ' . $lines[$key];
                        }
                    }
                }
                $replaceContent = true;
            }
            $prev = $line;
        }
        if ($replaceContent) {
            $content = implode("\r\n", $lines);
        }
        return $content;
    }

    public static function getContrastYIQ(string $hexcolor): string
    {
        $hexcolor = preg_replace('/[^a-f0-9]/i', '', $hexcolor);
        $r = hexdec(substr($hexcolor, 0, 2));
        $g = hexdec(substr($hexcolor, 2, 2));
        $b = hexdec(substr($hexcolor, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return ($yiq >= 128) ? 'black' : 'white';
    }

    public static function debug($input, string $level = 'i')
    {
        if (!WP_DEBUG) {
            return;
        }
        if (in_array(strtolower((string) WP_DEBUG_LOG), ['true', '1'], true)) {
            $logPath = WP_CONTENT_DIR . '/debug.log';
        } elseif (is_string(WP_DEBUG_LOG)) {
            $logPath = WP_DEBUG_LOG;
        } else {
            return;
        }
        if (is_array($input) || is_object($input)) {
            $input = print_r($input, true);
        }
        switch (strtolower($level)) {
            case 'e':
            case 'error':
                $level = 'Error';
                break;
            case 'i':
            case 'info':
                $level = 'Info';
                break;
            case 'd':
            case 'debug':
                $level = 'Debug';
                break;
            default:
                $level = 'Info';
        }
        error_log(
            date("[d-M-Y H:i:s \U\T\C]")
                . " WP $level: "
                . basename(__FILE__) . ' '
                . $input
                . PHP_EOL,
            3,
            $logPath
        );
    }
}
