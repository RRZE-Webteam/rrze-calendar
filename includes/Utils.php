<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use DateTime;
use DateTimeZone;
use DateTimeImmutable;
use RRule\RRule;
use RRule\RSet;

/**
 * Utils class
 * @package RRZE\Calendar
 */
class Utils
{
    /**
     * Validate URL.
     * @param string $input
     * @return string
     */
    public static function validateUrl(string $input): string
    {
        $url = filter_var($input, FILTER_SANITIZE_URL);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        return '';
    }

    /**
     * Key Sort Multi.
     * @param array $arr
     * @return void
     */
    public static function ksortMulti(&$arr = [])
    {
        ksort($arr);

        foreach (array_keys($arr) as $key) {
            if (is_array($arr[$key])) {
                self::ksortMulti($arr[$key]);
            }
        }
    }

    /**
     * String List to Array.
     * @param string $list
     * @param string $callback
     * @return array
     */
    public static function strListToArray(string $list, string $callback = 'trim'): array
    {
        return array_unique(
            array_filter(
                array_map(
                    $callback,
                    explode(',', $list)
                )
            )
        );
    }

    /**
     * Set date format.
     * @param string $format
     * @param mixed $dtStr
     * @param mixed $tz
     * @param mixed $offset
     * @return string
     */
    public static function date($format, $dtStr = null, $tz = null, $offset = null)
    {
        global $wp_locale;
        $date = null;
        // Safely catch Unix timestamps
        if (strlen($dtStr) >= 10 && is_numeric($dtStr)) {
            $dtStr = '@' . $dtStr;
        }
        // Convert $tz to DateTimeZone object if applicable
        if (!empty($tz) && is_string($tz)) {
            $tz = new DateTimeZone($tz);
        }
        // Set default timezone if null
        if (empty($tz)) {
            $tz = new DateTimeZone(get_option('timezone_string') ? get_option('timezone_string') : 'UTC');
        }
        // Fix signs in offset
        $offset = $offset ? str_replace('--', '+', str_replace('+-', '-', $offset)) : '';
        // Create new datetime from date string
        $dt = new DateTime(trim($dtStr . ' ' . $offset), $tz);
        // Localize (code from wp_date() in a more compact format)
        if (empty($wp_locale->month) || empty($wp_locale->weekday)) {
            $date = $dt->format($format);
        } else {
            $format = preg_replace('/(?<!\\\\)r/', DATE_RFC2822, $format);
            $newFormat = '';
            $formatLength = strlen($format);
            $month = $wp_locale->get_month($dt->format('m'));
            $weekday = $wp_locale->get_weekday($dt->format('w'));
            for ($i = 0; $i < $formatLength; $i++) {
                switch ($format[$i]) {
                    case 'D':
                        $newFormat .= addcslashes($wp_locale->get_weekday_abbrev($weekday), '\\A..Za..z');
                        break;
                    case 'F':
                        $newFormat .= addcslashes($month, '\\A..Za..z');
                        break;
                    case 'l':
                        $newFormat .= addcslashes($weekday, '\\A..Za..z');
                        break;
                    case 'M':
                        $newFormat .= addcslashes($wp_locale->get_month_abbrev($month), '\\A..Za..z');
                        break;
                    case 'a':
                        $newFormat .= addcslashes($wp_locale->get_meridiem($dt->format('a')), '\\A..Za..z');
                        break;
                    case 'A':
                        $newFormat .= addcslashes($wp_locale->get_meridiem($dt->format('A')), '\\A..Za..z');
                        break;
                    case '\\':
                        $newFormat .= $format[$i];
                        if ($i < $formatLength) {
                            $newFormat .= $format[++$i];
                        }
                        break;
                    default:
                        $newFormat .= $format[$i];
                        break;
                }
            }
            $date = $dt->format($newFormat);
            $date = wp_maybe_decline_date($date, $format);
        }
        return $date;
    }

    /**
     * Format a datetime string or timestamp using WordPress locale rules.
     *
     * - If $dtStr is numeric → interpreted as UTC timestamp.
     * - If $dtStr includes timezone info → respected.
     * - Otherwise → interpreted in WordPress timezone.
     * - $offset supports "+2 days", "-1 month", etc.
     *
     * @param string $format
     * @param mixed $dtStr Date string or timestamp.
     * @param mixed $tz Timezone string or DateTimeZone object.
     * @param string $offset
     * @return string|null
     */
    public static function dateFormat(string $format, $dtStr = '', $tz = null, string $offset = ''): ?string
    {
        global $wp_locale;

        // Normalize timezone
        if (!($tz instanceof \DateTimeZone)) {
            $tz = empty($tz)
                ? wp_timezone()
                : new \DateTimeZone((string) $tz);
        }

        // Detect numeric timestamp
        if (is_numeric($dtStr) && strlen((string) $dtStr) >= 10) {
            $dtStr = '@' . $dtStr; // UTC timestamp
        }

        // Normalize weird offset "--" or "+-"
        if (!empty($offset)) {
            $offset = trim(str_replace(['--', '+-'], ['+', '-'], $offset));
        }

        // Build DateTime safely
        try {
            $dt = new \DateTime(trim($dtStr . ' ' . $offset), $tz);
        } catch (\Throwable $e) {
            return null;
        }

        // If locale is unavailable → simple format
        if (empty($wp_locale->month) || empty($wp_locale->weekday)) {
            return $dt->format($format);
        }

        // Handle unescaped "r"
        $format = preg_replace('/(?<!\\\\)r/', DATE_RFC2822, $format);

        $new = '';
        $len = strlen($format);

        $month   = $wp_locale->get_month($dt->format('m'));
        $weekday = $wp_locale->get_weekday($dt->format('w'));

        for ($i = 0; $i < $len; $i++) {
            switch ($format[$i]) {
                case 'D':
                    $new .= addcslashes($wp_locale->get_weekday_abbrev($weekday), '\\A..Za..z');
                    break;
                case 'F':
                    $new .= addcslashes($month, '\\A..Za..z');
                    break;
                case 'l':
                    $new .= addcslashes($weekday, '\\A..Za..z');
                    break;
                case 'M':
                    $new .= addcslashes($wp_locale->get_month_abbrev($month), '\\A..Za..z');
                    break;
                case 'a':
                    $new .= addcslashes($wp_locale->get_meridiem($dt->format('a')), '\\A..Za..z');
                    break;
                case 'A':
                    $new .= addcslashes($wp_locale->get_meridiem($dt->format('A')), '\\A..Za..z');
                    break;

                case '\\': // escaped character
                    $new .= $format[$i];
                    if ($i + 1 < $len) {
                        $new .= $format[++$i];
                    }
                    break;

                default:
                    $new .= $format[$i];
            }
        }

        $out = $dt->format($new);
        return wp_maybe_decline_date($out, $format);
    }

    /**
     * Format a time string (HHMMSS, HH:MM, 9pm, 09:30, etc.)
     *
     * Always returns a valid formatted time or empty string.
     *
     * @param string $timeString
     * @param string $format Default "H:i:s"
     */
    public static function timeFormat(string $timeString, string $format = 'H:i:s'): string
    {
        if ($timeString === '') {
            return '';
        }

        // Fallback to WP time_format if empty
        if ($format === '') {
            $format = get_option('time_format') ?: 'H:i';
        }

        // Remove unsupported tokens
        $format = trim(preg_replace('/[BsueOPTZ]/', '', $format));

        // Digits only → HHMMSS or HHMM
        $digits = preg_replace('/[^0-9]/', '', $timeString);
        if ($digits === '') {
            return '';
        }

        // Odd length → pad left
        if (strlen($digits) % 2 === 1) {
            $digits = '0' . $digits;
        }

        $H = substr($digits, 0, 2);
        $M = substr($digits, 2, 2) ?: '00';
        $S = substr($digits, 4, 2) ?: '00';

        // Detect AM/PM
        $ampm = strtolower(preg_replace('/[^amp]/', '', strtolower($timeString)));
        $ampm = ($ampm === 'am' || $ampm === 'pm') ? $ampm : null;

        // Convert to 24h format
        if ($ampm === 'pm' && (int)$H < 12) {
            $H = (string)((int)$H + 12);
        } elseif ($ampm === 'am' && $H === '12') {
            $H = '00';
        }

        // Compute 12h version
        $H12 = (int)$H % 12;
        if ($H12 === 0) {
            $H12 = 12;
        }
        $H12 = str_pad($H12, 2, '0', STR_PAD_LEFT);

        // Greek AM/PM adjustment
        if (get_locale() === 'el' && $ampm !== null) {
            $ampm = ($ampm === 'am') ? 'πμ' : 'μμ';
        }

        // Output switch (compact)
        switch ($format) {
            case 'g:i a':
                return intval($H12) . ':' . $M . ' ' . $ampm;
            case 'g:ia':
                return intval($H12) . ':' . $M . $ampm;
            case 'g:i A':
                return intval($H12) . ':' . $M . ' ' . strtoupper($ampm);
            case 'g:iA':
                return intval($H12) . ':' . $M . strtoupper($ampm);
            case 'H:i:s':
                return "$H:$M:$S";
            case 'H:i':
                return "$H:$M";
            case 'His':
                return "$H$M$S";
            case 'G:i':
                return intval($H) . ':' . $M;
            case 'Gi':
                return intval($H) . $M;
            default:
                return "$H:$M";
        }
    }

    /**
     * Get the days of the week according to the start_of_week setting.
     *
     * @param string $format 'full'|'short'|'min'|'rrule'
     * @return array Indexed 0..6 in display order.
     */
    public static function getDaysOfWeek($format = '')
    {
        $daysOfWeek  = self::daysOfWeek($format);
        $startOfWeek = (int) get_option('start_of_week', 0);

        $ordered = [];
        for ($i = 0; $i < 7; $i++) {
            $weekDay   = ($i + $startOfWeek) % 7;
            $ordered[] = $daysOfWeek[$weekDay];
        }

        return $ordered;
    }

    /**
     * Get the days of the week (base order, Sunday=0).
     *
     * @param string $format 'full'|'short'|'min'|'rrule'
     * @return array
     */
    public static function daysOfWeek($format = '')
    {
        global $wp_locale;
        $daysOfWeek = [];

        switch ($format) {
            case 'rrule':
                $daysOfWeek = [
                    'monday'    => 'MO',
                    'tuesday'   => 'TU',
                    'wednesday' => 'WE',
                    'thursday'  => 'TH',
                    'friday'    => 'FR',
                    'saturday'  => 'SA',
                    'sunday'    => 'SU',
                ];
                break;

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
     * Get the month names.
     *
     * @param string $format 'full'|'short'|'rrule'
     * @return array
     */
    public static function getMonthNames($format = '')
    {
        global $wp_locale;
        $monthNames = [];

        switch ($format) {
            case 'rrule':
                $monthNames = [
                    'jan' => 1,
                    'feb' => 2,
                    'mar' => 3,
                    'apr' => 4,
                    'may' => 5,
                    'jun' => 6,
                    'jul' => 7,
                    'aug' => 8,
                    'sep' => 9,
                    'oct' => 10,
                    'nov' => 11,
                    'dec' => 12,
                ];
                break;

            case 'short':
                $monthNames = [
                    0  => $wp_locale->get_month_abbrev($wp_locale->get_month('01')),
                    1  => $wp_locale->get_month_abbrev($wp_locale->get_month('02')),
                    2  => $wp_locale->get_month_abbrev($wp_locale->get_month('03')),
                    3  => $wp_locale->get_month_abbrev($wp_locale->get_month('04')),
                    4  => $wp_locale->get_month_abbrev($wp_locale->get_month('05')),
                    5  => $wp_locale->get_month_abbrev($wp_locale->get_month('06')),
                    6  => $wp_locale->get_month_abbrev($wp_locale->get_month('07')),
                    7  => $wp_locale->get_month_abbrev($wp_locale->get_month('08')),
                    8  => $wp_locale->get_month_abbrev($wp_locale->get_month('09')),
                    9  => $wp_locale->get_month_abbrev($wp_locale->get_month('10')),
                    10 => $wp_locale->get_month_abbrev($wp_locale->get_month('11')),
                    11 => $wp_locale->get_month_abbrev($wp_locale->get_month('12')),
                ];
                break;

            case 'full':
            default:
                $monthNames = [
                    0  => $wp_locale->get_month('01'),
                    1  => $wp_locale->get_month('02'),
                    2  => $wp_locale->get_month('03'),
                    3  => $wp_locale->get_month('04'),
                    4  => $wp_locale->get_month('05'),
                    5  => $wp_locale->get_month('06'),
                    6  => $wp_locale->get_month('07'),
                    7  => $wp_locale->get_month('08'),
                    8  => $wp_locale->get_month('09'),
                    9  => $wp_locale->get_month('10'),
                    10 => $wp_locale->get_month('11'),
                    11 => $wp_locale->get_month('12'),
                ];
                break;
        }

        return $monthNames;
    }

    /**
     * Get the weekday (0–6, Sunday=0) of the first day of the month.
     *
     * @param string|int $date Date string or timestamp.
     * @return int 0 (Sunday) .. 6 (Saturday)
     */
    public static function firstDow($date = '')
    {
        // Base date string or "now"
        $base = $date ?: 'now';

        // "Ym" in WP timezone
        $ym = self::dateFormat('Ym', $base);
        // First of that month
        $w  = self::dateFormat('w', $ym . '01');

        return (int) $w;
    }

    /**
     * Get CSS classes for a calendar day.
     *
     * @param array $args {
     *     @type string $date  Day in Ymd.
     *     @type string $today Today in Ymd.
     *     @type int    $count Number of events.
     *     @type bool   $filler If this is a "filler" day.
     *     @type bool   $flat  If true, return string; if false, return array.
     * }
     * @return string|array
     */
    public static function dayClasses(array $args)
    {
        $defaults = [
            'date'   => self::dateFormat('Ymd', 'now'),
            'today'  => self::dateFormat('Ymd', 'now'),
            'count'  => 0,
            'filler' => false,
            'flat'   => true,
        ];

        $args = array_merge($defaults, $args);
        $date   = $args['date'];
        $today  = $args['today'];
        $count  = (int) $args['count'];
        $filler = !empty($args['filler']);
        $flat   = !empty($args['flat']);

        $dayClasses = [];

        if ($date < $today) {
            $dayClasses[] = 'past';
        } elseif ($date === $today) {
            $dayClasses[] = 'today';
        } else {
            $dayClasses[] = 'future';
        }

        if ($count === 0) {
            $dayClasses[] = 'empty';
        } elseif ($count === 1 && $filler) {
            $dayClasses[] = 'available';
        } else {
            $dayClasses[] = 'has_events';
        }

        return $flat ? implode(' ', $dayClasses) : $dayClasses;
    }

    /**
     * Recursively ksort an array by keys.
     *
     * @param array $array
     * @return bool
     */
    public static function recurKsort(array &$array): bool
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::recurKsort($value);
            }
        }
        return ksort($array);
    }

    /**
     * Generates a random id.
     * @param integer $length
     * @return string
     */
    public static function createId(int $length = 8): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generates a random UUID based on the post ID and website host.
     * @param integer $postId
     * @return string
     */
    public static function createUuid(int $postId = 0)
    {
        $host = parse_url(site_url(), PHP_URL_HOST);
        $uid = vsprintf('%s-%s-%s', str_split(self::createId(), 4));
        return sprintf('%s-%s@%s', $uid, $postId, $host);
    }

    /**
     * Sanitize Hexcolor.
     * @param string $hexcolor
     * @return string
     */
    public static function sanitizeHexColor($hexcolor)
    {
        if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $hexcolor)) {
            return $hexcolor;
        } else {
            return '';
        }
    }

    /**
     * Calculates color contrast using the YIQ color space.
     * @link https://24ways.org/2010/calculating-color-contrast/
     * @param string $hexacolor
     * @return string
     */
    public static function getContrastYIQ(string $hexcolor): string
    {
        $hexcolor = preg_replace('/[^a-f0-9]/i', '', $hexcolor);
        $r = hexdec(substr($hexcolor, 0, 2));
        $g = hexdec(substr($hexcolor, 2, 2));
        $b = hexdec(substr($hexcolor, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return ($yiq >= 128) ? 'black' : 'white';
    }

    /**
     * Build Events Array.
     * @param array $events
     * @param string $start
     * @param string $end
     * @return array
     */
    public static function buildEventsArray($events, $start = '', $end = '')
    {
        $eventsArray = [];
        $i = 0;
        $identifiers = [];
        $removeDuplicates = settings()->getOption('remove_duplicates') === '1';

        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(date_default_timezone_get());

        // Helper: parse "Y-m-d" or "Y-m-d H:i" or "Y-m-d H:i:s" as WP local time
        $parseWpLocal = static function (string $value) use ($tz): ?\DateTimeImmutable {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            $value = str_replace('T', ' ', $value);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $value .= ' 00:00:00';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $value)) {
                $value .= ':00';
            }

            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);
            return $dt ?: null;
        };

        foreach ($events as $event) {
            if (empty($event)) {
                continue;
            }

            $meta = get_post_meta($event->ID);
            if (empty($meta)) {
                continue;
            }

            $eventTitle = get_the_title($event->ID);
            $location   = get_post_meta($event->ID, 'location', true);

            $isImport = get_post_meta($event->ID, 'ics_feed_id', true);

            if ($isImport && ($occurrences = get_post_meta($event->ID, 'ics_event_ocurrences', true))) {

                // Stored as UNIX timestamps, created from WP local time (wp_timezone)
                $startTStmp = absint(Utils::getMeta($meta, 'start'));
                $endTStmp   = absint(Utils::getMeta($meta, 'end'));

                // Extract local time-of-day from stored timestamps
                $startTimeLocal = $startTStmp ? wp_date('H:i:s', $startTStmp, $tz) : '00:00:00';
                $endTimeLocal   = $endTStmp   ? wp_date('H:i:s', $endTStmp,   $tz) : '00:00:00';

                foreach ($occurrences as $startDt) {
                    // $startDt is expected to be Y-m-d (date of occurrence). If it ever includes time, we keep the date part.
                    $occDate = trim((string) $startDt);
                    if ($occDate === '') {
                        continue;
                    }

                    // If it comes as "Y-m-d ..." keep only date
                    $occDate = substr($occDate, 0, 10);

                    $startDT = $parseWpLocal($occDate . ' ' . $startTimeLocal);
                    $endDT   = $parseWpLocal($occDate . ' ' . $endTimeLocal);

                    if (!$startDT || !$endDT) {
                        continue;
                    }

                    $startTS = $startDT->getTimestamp();
                    $endTS   = $endDT->getTimestamp();

                    $key = $startTS . $endTS . $eventTitle . $location;
                    if ($removeDuplicates && in_array($key, $identifiers, true)) {
                        continue;
                    }

                    $eventsArray[$startTS][$i] = [
                        'id'    => $event->ID,
                        'start' => $startTS,
                        'end'   => $endTS,
                    ];
                    $identifiers[] = $key;
                }
            } elseif ('on' == Utils::getMeta($meta, 'repeat')) {

                $occurrences = Utils::makeRRuleSet($event->ID, $start, $end);

                // Stored as UNIX timestamp, created from WP local time (wp_timezone)
                $endTStmp = absint(Utils::getMeta($meta, 'end'));
                $endTimeLocal = $endTStmp ? wp_date('H:i:s', $endTStmp, $tz) : '00:00:00';

                foreach ($occurrences as $occurrence) {
                    // $occurrence->getTimestamp() gives an absolute timestamp.
                    // We must format the DATE in WP timezone (Berlín), not UTC.
                    $startTS = $occurrence->getTimestamp();

                    $startDateLocal = wp_date('Y-m-d', $startTS, $tz);

                    $endDT = $parseWpLocal($startDateLocal . ' ' . $endTimeLocal);
                    if (!$endDT) {
                        continue;
                    }

                    $endTS = $endDT->getTimestamp();

                    $key = $startTS . $endTS . $eventTitle . $location;
                    if ($removeDuplicates && in_array($key, $identifiers, true)) {
                        continue;
                    }

                    $eventsArray[$startTS][$i] = [
                        'id'    => $event->ID,
                        'start' => $startTS,
                        'end'   => $endTS,
                    ];
                    $identifiers[] = $key;
                }
            } else {

                $startTS = absint(Utils::getMeta($meta, 'start'));
                $endTS   = absint(Utils::getMeta($meta, 'end'));

                $key = $startTS . $endTS . $eventTitle . $location;
                if ($removeDuplicates && in_array($key, $identifiers, true)) {
                    continue;
                }

                $eventsArray[$startTS][$i] = [
                    'id'    => $event->ID,
                    'start' => $startTS,
                    'end'   => $endTS,
                ];
                $identifiers[] = $key;
            }

            $i++;
        }

        if ($eventsArray) {
            ksort($eventsArray);
        }

        return $eventsArray;
    }

    /**
     * Make RRule arguments.
     * @param WP_Post $event The event post object.
     * @return array
     */
    public static function makeRRuleArgs($event): array
    {
        $meta = get_post_meta($event->ID);
        $repeat = self::getMeta($meta, 'repeat');
        if ($repeat != 'on')
            return [];
        if (self::getMeta($meta, 'repeat-interval') == 'week' && self::getMeta($meta, 'repeat-weekly-interval') == '')
            return [];
        if (self::getMeta($meta, 'repeat-interval') == 'month' && self::getMeta($meta, 'repeat-monthly-type') == '')
            return [];

        $tz = wp_timezone();

        $startTS = (int) self::getMeta($meta, 'start');
        if ($startTS <= 0) {
            return [];
        }

        $oneYearAgoTS = (new \DateTimeImmutable('-1 year', $tz))->getTimestamp();

        if ($startTS >= $oneYearAgoTS) {
            $dtstart = $startTS;
        } else {
            $startLocal = (new \DateTimeImmutable('@' . $startTS))->setTimezone($tz);
            $oneYearAgo = new \DateTimeImmutable('-1 year', $tz);

            $dtstart = (new \DateTimeImmutable(
                $oneYearAgo->format('Y-m-d') . ' ' . $startLocal->format('H:i:s'),
                $tz
            ))->getTimestamp();
        }
        $dtstart = '@' . $dtstart;

        $endTS = self::getMeta($meta, 'end');
        if ($endTS == '') return [];

        $rruleArgs = [
            'DTSTART' => $dtstart,
            //'COUNT' => 100,
        ];

        $lastDateTS = (int) self::getMeta($meta, 'repeat-lastdate');
        if ($lastDateTS > 0) {
            $lastLocal = (new \DateTimeImmutable('@' . $lastDateTS))->setTimezone($tz);
            $lastDateTS = (new \DateTimeImmutable($lastLocal->format('Y-m-d') . ' 23:59:59', $tz))->getTimestamp();
        } else {
            $lastDateTS = (new \DateTimeImmutable('+1 year', $tz))->getTimestamp();
        }
        $rruleArgs['UNTIL'] = '@' . $lastDateTS;

        $repeatInterval = self::getMeta($meta, 'repeat-interval');
        if ($repeatInterval == 'week') {

            $rruleArgs['FREQ'] = 'weekly';
            $rruleArgs['INTERVAL'] = self::getMeta($meta, 'repeat-weekly-interval');
            if ($dows = self::getMeta($meta, 'repeat-weekly-day')) {
                $daysOfWeek = self::daysOfWeek('rrule');
                foreach ($dows as $i => $dow) {
                    $dows[$i] = $daysOfWeek[$dow];
                }
                $rruleArgs['BYDAY'] = $dows;
            }
        } elseif ($repeatInterval == 'month') {
            $rruleArgs['FREQ'] = 'monthly';

            $months = (array)self::getMeta($meta, 'repeat-monthly-month');
            if (count($months) < 12) {
                $monthsRrule = self::getMonthNames('rrule');
                foreach ($months as $i => $month) {
                    $months[$i] = $monthsRrule[$month];
                }
                $rruleArgs['BYMONTH'] = $months;
            }

            $monthlyType = self::getMeta($meta, 'repeat-monthly-type');
            if ($monthlyType == 'date') {
                $rruleArgs['BYMONTHDAY'] = self::getMeta($meta, 'repeat-monthly-type-date');
            } elseif ($monthlyType == 'dow') {
                $monthlyDow = self::getMeta($meta, 'repeat-monthly-type-dow');
                $daysOfWeek = self::daysOfWeek('rrule');
                $rruleArgs['BYDAY'] = $daysOfWeek[$monthlyDow["day"]];
                $rruleArgs['BYSETPOS'] = ($monthlyDow["daycount"] ?? 1);
            }
        }

        return $rruleArgs;
    }

    /**
     * Make RRuleSet.
     * @param integer $event_id
     * @param string $start
     * @param string $end
     * @return array
     */
    public static function makeRRuleSet($event_id, $start = '', $end = '')
    {
        $event_id = absint($event_id);

        $meta = get_post_meta($event_id);
        if (empty($meta)) {
            return [];
        }

        $rruleArgsRaw = Utils::getMeta($meta, 'event-rrule-args');
        if (empty($rruleArgsRaw)) {
            return [];
        }

        $rruleArgs = json_decode($rruleArgsRaw, true);
        if (!is_array($rruleArgs) || empty($rruleArgs)) {
            return [];
        }

        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(date_default_timezone_get());

        $rset = new RSet();
        $rset->addRRule($rruleArgs);

        // Stored as UNIX timestamp derived from WP local time
        $startTS = absint(Utils::getMeta($meta, 'start'));
        $startTime = $startTS ? wp_date('H:i', $startTS, $tz) : '00:00';

        $normalizeLines = static function ($raw): array {
            $raw = (string) $raw;
            $raw = str_replace("\r", '', $raw);
            $lines = array_map('trim', explode("\n", $raw));
            return array_values(array_filter($lines, static fn($v) => $v !== ''));
        };

        // Exceptions (dates only, one per line) -> add time-of-day of event start
        $exceptionsRaw = Utils::getMeta($meta, 'exceptions');
        foreach ($normalizeLines($exceptionsRaw) as $exception) {
            // expected: Y-m-d
            $dateTime = $exception . ' ' . $startTime; // Y-m-d H:i
            if (self::validateDate($dateTime)) {
                $rset->addExDate($dateTime);
            }
        }

        // Additions (dates only, one per line) -> add time-of-day of event start
        $additionsRaw = Utils::getMeta($meta, 'additions');
        foreach ($normalizeLines($additionsRaw) as $addition) {
            $dateTime = $addition . ' ' . $startTime; // Y-m-d H:i
            if (self::validateDate($dateTime)) {
                $rset->addDate($dateTime);
            }
        }

        // Normalize $start / $end (UI/API inputs) to what validateDate() expects.
        $start = trim((string) $start);
        $end   = trim((string) $end);

        // If start is only a date, set to beginning of day in local time
        if ($start !== '' && !str_contains($start, ':')) {
            $start .= ' 00:00';
        }

        // If end is only a date, set to end of day so occurrences on that date are included
        if ($end !== '' && !str_contains($end, ':')) {
            $end .= ' 23:59';
        }

        $start = self::validateDate($start) ? $start : '';
        $end   = self::validateDate($end) ? $end : '';

        if ($start !== '' || $end !== '') {
            return $rset->getOccurrencesBetween($start, $end);
        }

        return $rset->getOccurrences();
    }

    /**
     * Get the meta value.
     * @param array $meta
     * @param string $key
     * @return mixed
     */
    public static function getMeta($meta, $key)
    {
        if (!isset($meta[$key][0])) {
            return '';
        } else {
            return maybe_unserialize($meta[$key][0]);
        }
    }

    /**
     * Get the human readable recurrence.
     * @param mixed $rrule An assoc array of parts, or a RFC string.
     * @return object RRule
     */
    public static function humanReadableRecurrence($rrule)
    {
        $opt = [
            'use_intl' => true,
            'locale' => substr(get_locale(), 0, 2),
            'date_formatter' => function ($date) {
                return $date->format(__('m-d-Y', 'rrze-calendar'));
            },
            'fallback' => 'en',
            'explicit_infinite' => true,
            'include_start' => false,
            'include_until' => true,
            'custom_path' => plugin()->getPath('languages/rrule'),
        ];

        $rrule = new RRule($rrule);
        return $rrule->humanReadable($opt);
    }

    /**
     * getTimezoneOffset
     * Returns offset between UTC and website's timezone in seconds (e.g. 7200) or as a string (e.g. +02:00)
     * @param $format "string"|"seconds"
     * @return int|string
     */
    public static function getTimezoneOffset($format = 'string')
    {
        $offset  = (float) get_option('gmt_offset');
        if ($format == 'seconds') {
            return (int)($offset * 60 * 60);
        } else {
            $hours   = (int) $offset;
            $minutes = ($offset - $hours);
            $sign      = ($offset < 0) ? '-' : '+';
            $abs_hour  = abs($hours);
            $abs_mins  = abs($minutes * 60);
            return sprintf('%s%02d:%02d', $sign, $abs_hour, $abs_mins);
        }
    }

    /**
     * Title filter
     * @param string $where
     * @param WP_Query $wp_query
     * @return string
     */
    public static function titleFilter($where, $wp_query)
    {
        global $wpdb;
        $title_filter_relation = (strtoupper($wp_query->get('title_filter_relation')) == 'OR' ? 'OR' : 'AND');
        if ($search_term = $wp_query->get('title_filter')) {
            $search_term = $wpdb->esc_like($search_term);
            $search_term = ' \'%' . $search_term . '%\'';
            $where .= ' ' . $title_filter_relation . ' ' . $wpdb->posts . '.post_title LIKE ' . $search_term;
        }
        if ($search_term_exclude = $wp_query->get('title_filter_exclude')) {
            $search_term_exclude = $wpdb->esc_like($search_term_exclude);
            $search_term_exclude = ' \'%' . $search_term_exclude . '%\'';
            $where .= ' ' . $title_filter_relation . ' ' . $wpdb->posts . '.post_title NOT LIKE ' . $search_term_exclude;
        }
        return $where;
    }

    /**
     * Validate any date representation: timestamp, string, DateTime.
     * 
     * @param mixed $date
     * @return bool
     */
    public static function validateDate($date): bool
    {
        if ($date instanceof \DateTimeInterface) {
            return true;
        }

        // Numeric timestamp
        if (is_numeric($date)) {
            return ((int) $date) > 0;
        }

        if (!is_string($date)) {
            return false;
        }

        $date = trim($date);
        if ($date === '') {
            return false;
        }

        $tz = function_exists('wp_timezone')
            ? wp_timezone()
            : new \DateTimeZone(date_default_timezone_get());

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $date, $tz);
            if ($dt instanceof \DateTimeImmutable) {
                return true;
            }
        }

        return false;
    }

    /**
     * Translate Outlook/Exchange SUMMARY to German (WordPress i18n ready).
     *
     * @param string $summary  The SUMMARY string (e.g., "Busy", "Out of Office").
     * @return string  Localized translation.
     */
    public static function translateOutlookSummaryToGerman(string $summary): string
    {
        $norm = trim(mb_strtolower($summary));

        // Normalize common aliases
        $aliases = [
            'out-of-office'    => 'out of office',
            'oof'              => 'out of office',
            'workingelsewhere' => 'working elsewhere',
            'work elsewhere'   => 'working elsewhere',
        ];
        if (isset($aliases[$norm])) {
            $norm = $aliases[$norm];
        }

        // Main mapping
        $map = [
            'free'              => __('Free', 'rrze-calendar'),
            'tentative'         => __('Tentative', 'rrze-calendar'),
            'busy'              => __('Busy', 'rrze-calendar'),
            'out of office'     => __('Out of office', 'rrze-calendar'),
            'working elsewhere' => __('Working elsewhere', 'rrze-calendar'),
        ];

        return $map[$norm] ?? $summary;
    }

    /**
     * Prepare a post_title to be used safely as SUMMARY in an iCalendar/vCalendar file.
     *
     * @param string $postTitle The raw post title from WordPress.
     * @return string Cleaned and RFC 5545–escaped summary text.
     */
    public static function prepareSummaryFromPostTitle(string $postTitle): string
    {
        // Decode HTML entities (&amp;, &quot;, etc.)
        $summary = wp_specialchars_decode($postTitle, ENT_QUOTES);

        // Sanitize as plain text (removes invalid octets, trims, normalizes whitespace)
        $summary = sanitize_text_field($summary);

        // Escape special characters for iCalendar / vCalendar (RFC 5545)
        $summary = str_replace('\\', '\\\\', $summary);                // Escape backslashes
        $summary = str_replace(',',  '\,',  $summary);                 // Escape commas
        $summary = str_replace(';',  '\;',  $summary);                 // Escape semicolons
        $summary = str_replace(["\r\n", "\n", "\r"], '\\n', $summary); // Normalize newlines

        // Limit maximum length
        $summary = mb_substr($summary, 0, 255, 'UTF-8');

        return $summary;
    }

    /**
     * Prepare post_content to be used safely as DESCRIPTION in an iCalendar/vCalendar file.
     *
     * @param string $postContent The raw post content from WordPress.
     * @return string Cleaned and RFC 5545–escaped description text.
     */
    public static function prepareDescriptionFromPostContent(string $postContent): string
    {
        // Expand shortcodes and blocks into rendered HTML
        $description = apply_filters('the_content', $postContent);

        // Strip all HTML tags
        $description = wp_strip_all_tags($description, true);

        // Decode HTML entities (&amp;, &quot;, etc.)
        $description = wp_specialchars_decode($description, ENT_QUOTES);

        // Sanitize as plain text (removes control chars, trims, normalizes whitespace)
        $description = sanitize_text_field($description);

        // Escape special characters for iCalendar / vCalendar (RFC 5545)
        $description = str_replace('\\', '\\\\', $description);                // Escape backslashes
        $description = str_replace(',',  '\,',  $description);                 // Escape commas
        $description = str_replace(';',  '\;',  $description);                 // Escape semicolons
        $description = str_replace(["\r\n", "\n", "\r"], '\\n', $description); // Normalize newlines

        // Limit the length to a safe range
        $description = mb_substr($description, 0, 2000, 'UTF-8');

        return $description;
    }

    /**
     * Convert a UTC timestamp into a date/time in the WordPress timezone.
     *
     * @param int    $timestamp UTC timestamp.
     * @param string $format    Optional output format (default: 'Y-m-d H:i:s').
     * @return string
     */
    public static function timestampToLocal(int $timestamp, string $format = 'Y-m-d H:i:s'): string
    {
        $dt = new DateTime('@' . $timestamp);      // Interpret as UTC
        $dt->setTimezone(wp_timezone());           // Convert to WP timezone
        return $dt->format($format);
    }

    /**
     * Convert a local WordPress date/time string to a UTC timestamp.
     *
     * @param string $datestr Date/time in WP local timezone (e.g. '2025-09-10 09:00').
     * @return int UTC timestamp.
     */
    public static function localToTimestamp(string $datestr): int
    {
        $dt = new DateTime($datestr, wp_timezone());     // Interpret in WP timezone
        $dt->setTimezone(new DateTimeZone('UTC'));       // Convert to UTC
        return $dt->getTimestamp();
    }

    /**
     * Convert a UTC timestamp into an ICS-compatible UTC datetime string.
     *
     * Output format: YYYYMMDDTHHMMSSZ
     * Example: 20250910T090000Z
     *
     * @param int $timestamp UTC timestamp.
     * @return string ICS UTC datetime.
     */
    public static function timestampToIcsUtc($timestamp): string
    {
        if (!is_numeric($timestamp) || $timestamp <= 0) {
            return '';
        }

        $dt = new DateTimeImmutable('@' . $timestamp, new DateTimeZone('UTC'));
        return $dt->format('Ymd\THis\Z');
    }

    /**
     * Parse any feed datetime safely using WordPress timezone rules.
     *
     * - If the string contains a timezone offset (Z, +02:00), parse as UTC
     * - If the string has no timezone, interpret in WP timezone
     * - Returns a correct UTC timestamp for comparisons
     */
    public static function parseFeedDatetime(string $feedDateTime): int
    {
        $hasTZ = preg_match('/(Z|[+-][0-9]{2}:?[0-9]{2}|GMT|UTC)/i', $feedDateTime);

        if ($hasTZ) {
            // String includes timezone info → parse as UTC
            $dt = new DateTime($feedDateTime, new DateTimeZone('UTC'));
        } else {
            // No timezone → interpret using the WordPress timezone
            $dt = new DateTime($feedDateTime, wp_timezone());
            $dt->setTimezone(new DateTimeZone('UTC'));
        }

        return $dt->getTimestamp();
    }

    /**
     * Parse any datetime string:
     * - If it already contains timezone → respect it
     * - Otherwise → interpret in WordPress timezone
     *
     * @param string $dateString
     * @return DateTime
     */
    public static function parseAnyDatetime($dateString)
    {
        // Contains explicit timezone? Then trust it.
        $hasTZ = preg_match(
            '/Z$|GMT|UTC|[+-][0-9]{2}:?[0-9]{2}/i',
            $dateString
        );

        try {
            if ($hasTZ) {
                return new \DateTime($dateString);
            }
            return new \DateTime($dateString, wp_timezone());
        } catch (\Exception $e) {
            // Safe fallback: now()
            return new \DateTime('now', wp_timezone());
        }
    }

    /**
     * Convert a WP-local datetime string (Y-m-d[ H:i[:s]]) to a UNIX timestamp.
     *
     * Interprets the input as local time in the WordPress timezone settings.
     *
     * @param string $datetime
     * @return int
     */
    public static function wpLocalToTimestamp(string $datetime): int
    {
        $datetime = trim($datetime);
        $datetime = str_replace('T', ' ', $datetime);

        // Get WP timezone
        $tz = function_exists('wp_timezone')
            ? wp_timezone()
            : new \DateTimeZone(date_default_timezone_get());

        // Normalize formats
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetime)) {
            $datetime .= ' 00:00:00';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $datetime)) {
            $datetime .= ':00';
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime, $tz);

        if (!$dt) {
            // You can replace this with logging if preferred
            return 0;
        }

        return $dt->getTimestamp();
    }
}
