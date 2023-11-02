<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use DateTime;
use DateTimeZone;
use RRule\RRule;
use RRule\RSet;

class Utils
{
    public static function validateUrl(string $input): string
    {
        $url = filter_var($input, FILTER_SANITIZE_URL);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        return '';
    }

    public static function ksortMulti(&$arr = [])
    {
        ksort($arr);

        foreach (array_keys($arr) as $key) {
            if (is_array($arr[$key])) {
                self::ksortMulti($arr[$key]);
            }
        }
    }

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
        $offset = str_replace('--', '+', str_replace('+-', '-', $offset));
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
     * dateFormat
     * Formatted date strings.
     *
     * @param string $format
     * @param mixed $dtStr
     * @param mixed $tz
     * @param mixed $offset
     * @return string
     */
    public static function dateFormat($format, $dtStr = '', $tz = '', $offset = '')
    {
        global $wp_locale;
        $date = '';

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
        $offset = str_replace('--', '+', str_replace('+-', '-', $offset));

        // Create new datetime from date string
        $dt = new DateTime(trim($dtStr . ' ' . $offset), $tz);

        // Localize
        if (empty($wp_locale->month) || empty($wp_locale->weekday)) {
            $date = $dt->format($format);
        } else {
            $format = preg_replace('/(?<!\\\\)r/', DATE_RFC2822, $format);
            $newFormat    = '';
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

        return !empty($date) ? $date : null;
    }

    /**
     * timeFormat
     * Format time string data.
     *
     * @param string $timeString
     * @param mixed $format
     * @return string
     */
    public static function timeFormat($timeString, $format = 'H:i:s')
    {
        $output = null;
        if (empty($format)) {
            $format = get_option('time_format');
        }

        // Strip unsupported format elements from string
        $format = trim(preg_replace('/[BsueOPTZ]/', '', $format));

        // Get digits from time string
        $timeDigits = preg_replace('/[^0-9]+/', '', $timeString);

        // Get am/pm from time string
        $timeAmPm = preg_replace('/[^amp]+/', '', strtolower($timeString));
        if ($timeAmPm != 'am' && $timeAmPm != 'pm') {
            $timeAmPm = null;
        }

        // Prepend zero to digits if length is odd
        if (strlen($timeDigits) % 2 == 1) {
            $timeDigits = '0' . $timeDigits;
        }

        // Get hour, minutes and seconds from time digits
        $timeH = substr($timeDigits, 0, 2);
        $timeM = substr($timeDigits, 2, 2);
        $timeS = strlen($timeDigits) == 6 ? substr($timeDigits, 4, 2) : null;

        // Convert hour to correct 24-hour value if needed
        if ($timeAmPm == 'pm') {
            $timeH = (int)$timeH + 12;
        }

        if ($timeAmPm == 'am' && $timeH == '12') {
            $timeH = '00';
        }

        // Determine am/pm if not passed in
        if (empty($timeAmPm)) {
            $timeAmPm = (int)$timeH >= 12 ? 'pm' : 'am';
        }

        // Get 12-hour version of hour
        $timeH12 = (int)$timeH % 12;
        if ($timeH12 == 0) {
            $timeH12 = 12;
        }
        if ($timeH12 < 10) {
            $timeH12 = '0' . (string)$timeH12;
        }

        // Convert am/pm abbreviations for Greek (this is simpler than putting it in the i18n files)
        if (get_locale() == 'el') {
            $timeAmPm = ($timeAmPm == 'am') ? 'πμ' : 'μμ';
        }

        // Format output
        switch ($format) {
                // 12-hour formats without seconds
            case 'g:i a':
                $output = intval($timeH12) . ':' . $timeM . '&nbsp;' . $timeAmPm;
                break;
            case 'g:ia':
                $output = intval($timeH12) . ':' . $timeM . $timeAmPm;
                break;
            case 'g:i A':
                $output = intval($timeH12) . ':' . $timeM . '&nbsp;' . strtoupper($timeAmPm);
                break;
            case 'g:iA':
                $output = intval($timeH12) . ':' . $timeM . strtoupper($timeAmPm);
                break;
            case 'h:i a':
                $output = $timeH12 . ':' . $timeM . '&nbsp;' . $timeAmPm;
                break;
            case 'h:ia':
                $output = $timeH12 . ':' . $timeM . $timeAmPm;
                break;
            case 'h:i A':
                $output = $timeH12 . ':' . $timeM . '&nbsp;' . strtoupper($timeAmPm);
                break;
            case 'h:iA':
                $output = $timeH12 . ':' . $timeM . strtoupper($timeAmPm);
                break;
                // 24-hour formats without seconds
            case 'G:i':
                $output = intval($timeH) . ':' . $timeM;
                break;
            case 'Gi':
                $output = intval($timeH) . $timeM;
                break;
                // case 'H:i': is the default, below
            case 'Hi':
                $output = $timeH . $timeM;
                break;
                // 24-hour formats without seconds, using h and m or min
            case 'G \h i \m\i\n':
                $output = intval($timeH) . '&nbsp;h&nbsp;' . $timeM . '&nbsp;min';
                break;
            case 'G\h i\m\i\n':
                $output = intval($timeH) . 'h&nbsp;' . $timeM . 'min';
                break;
            case 'G\hi\m\i\n':
                $output = intval($timeH) . 'h' . $timeM . 'min';
                break;
            case 'G \h i \m':
                $output = intval($timeH) . '&nbsp;h&nbsp;' . $timeM . '&nbsp;m';
                break;
            case 'G\h i\m':
                $output = intval($timeH) . 'h&nbsp;' . $timeM . 'm';
                break;
            case 'G\hi\m':
                $output = intval($timeH) . 'h' . $timeM . 'm';
                break;
            case 'H \h i \m\i\n':
                $output = $timeH . '&nbsp;h&nbsp;' . $timeM . '&nbsp;min';
                break;
            case 'H\h i\m\i\n':
                $output = $timeH . 'h&nbsp;' . $timeM . 'min';
                break;
            case 'H\hi\m\i\n':
                $output = $timeH . 'h' . $timeM . 'min';
                break;
            case 'H \h i \m':
                $output = $timeH . '&nbsp;h&nbsp;' . $timeM . '&nbsp;m';
                break;
            case 'H\h i\m':
                $output = $timeH . 'h&nbsp;' . $timeM . 'm';
                break;
            case 'H\hi\m':
                $output = $timeH . 'h' . $timeM . 'm';
                break;
                // 12-hour formats with seconds
            case 'g:i:s a':
                $output = intval($timeH12) . ':' . $timeM . ':' . $timeS . '&nbsp;' . $timeAmPm;
                break;
            case 'g:i:sa':
                $output = intval($timeH12) . ':' . $timeM . ':' . $timeS . $timeAmPm;
                break;
            case 'g:i:s A':
                $output = intval($timeH12) . ':' . $timeM . ':' . $timeS . '&nbsp;' . strtoupper($timeAmPm);
                break;
            case 'g:i:sA':
                $output = intval($timeH12) . ':' . $timeM . ':' . $timeS . strtoupper($timeAmPm);
                break;
            case 'h:i:s a':
                $output = $timeH12 . ':' . $timeM . ':' . $timeS . '&nbsp;' . $timeAmPm;
                break;
            case 'h:i:sa':
                $output = $timeH12 . ':' . $timeM . ':' . $timeS . $timeAmPm;
                break;
            case 'h:i:s A':
                $output = $timeH12 . ':' . $timeM . ':' . $timeS . '&nbsp;' . strtoupper($timeAmPm);
                break;
            case 'h:i:sA':
                $output = $timeH12 . ':' . $timeM . ':' . $timeS . strtoupper($timeAmPm);
                break;
                // 24-hour formats with seconds
            case 'G:i:s':
                $output = intval($timeH) . ':' . $timeM . ':' . $timeS;
                break;
            case 'H:i:s':
                $output = $timeH . ':' . $timeM . ':' . $timeS;
                break;
            case 'His':
                $output = $timeH . $timeM . $timeS;
                break;
                // Hour-only formats used for grid labels
            case 'H:00':
                $output = $timeH . ':00';
                break;
            case 'h:00':
                $output = $timeH12 . ':00';
                break;
            case 'H00':
                $output = $timeH . '00';
                break;
            case 'g a':
                $output = intval($timeH12) . ' ' . $timeAmPm;
                break;
            case 'g A':
                $output = intval($timeH12) . ' ' . strtoupper($timeAmPm);
                break;
                // Default
            case 'H:i':
            default:
                $output = $timeH . ':' . $timeM;
                break;
        }

        return $output;
    }

    public static function getDaysOfWeek($format = null)
    {
        $days = [];
        $daysOfWeek = self::daysOfWeek($format);
        $startOfWeek = get_option('start_of_week', 0);
        for ($i = 0; $i < 7; $i++) {
            $weekDay = ($i + $startOfWeek) % 7;
            $days[$weekDay] = $daysOfWeek[$weekDay];
        }
        return $days;
    }

    public static function daysOfWeek($format = null)
    {
        global $wp_locale;
        $daysOfWeek = [];
        switch ($format) {
            case 'rrule':
                $daysOfWeek = [
                    'monday' => 'MO',
                    'tuesday' => 'TU',
                    'wednesday' => 'WE',
                    'thursday' => 'TH',
                    'friday' => 'FR',
                    'saturday' => 'SA',
                    'sunday' => 'SU',
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

    public static function getMonthNames($format = null)
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
                    0 => $wp_locale->get_month_abbrev($wp_locale->get_month('01')),
                    1 => $wp_locale->get_month_abbrev($wp_locale->get_month('02')),
                    2 => $wp_locale->get_month_abbrev($wp_locale->get_month('03')),
                    3 => $wp_locale->get_month_abbrev($wp_locale->get_month('04')),
                    4 => $wp_locale->get_month_abbrev($wp_locale->get_month('05')),
                    5 => $wp_locale->get_month_abbrev($wp_locale->get_month('06')),
                    6 => $wp_locale->get_month_abbrev($wp_locale->get_month('07')),
                    7 => $wp_locale->get_month_abbrev($wp_locale->get_month('08')),
                    8 => $wp_locale->get_month_abbrev($wp_locale->get_month('09')),
                    9 => $wp_locale->get_month_abbrev($wp_locale->get_month('10')),
                    10 => $wp_locale->get_month_abbrev($wp_locale->get_month('11')),
                    11 => $wp_locale->get_month_abbrev($wp_locale->get_month('12')),
                    12 => $wp_locale->get_month_abbrev($wp_locale->get_month('12')),
                ];
                break;
            case 'full':
            default:
                $monthNames = [
                    0 => $wp_locale->get_month('01'),
                    1 => $wp_locale->get_month('02'),
                    2 => $wp_locale->get_month('03'),
                    3 => $wp_locale->get_month('04'),
                    4 => $wp_locale->get_month('05'),
                    5 => $wp_locale->get_month('06'),
                    6 => $wp_locale->get_month('07'),
                    7 => $wp_locale->get_month('08'),
                    8 => $wp_locale->get_month('09'),
                    9 => $wp_locale->get_month('10'),
                    10 => $wp_locale->get_month('11'),
                    11 => $wp_locale->get_month('12'),
                    12 => $wp_locale->get_month('12'),
                ];
                break;
        }
        return $monthNames;
    }

    public static function firstDow($date = null)
    {
        return self::date('w', self::date('Ym', $date) . '01');
    }

    public static function dayClasses($args)
    {
        $defaults = [
            'date' => self::date('Ymd'),
            'today' => self::date('Ymd'),
            'count' => 0,
            'filler' => false,
            'flat' => true,
        ];
        extract(array_merge($defaults, $args));
        $day_classes = [];
        if ($date < $today) {
            $day_classes[] = 'past';
        } elseif ($date == $today) {
            $day_classes[] = 'today';
        } else {
            $day_classes[] = 'future';
        }
        if ($count == 0) {
            $day_classes[] = 'empty';
        } elseif ($count == 1 && !empty($filler)) {
            $day_classes[] = 'available';
        } else {
            $day_classes[] = 'has_events';
        }
        if (!empty($flat)) {
            $day_classes = implode(' ', $day_classes);
        }
        return $day_classes;
    }

    /**
     * Recursives KSort.
     *
     * @param array $array
     * @return boolean
     */
    public static function recurKsort(array &$array)
    {
        foreach ($array as &$value) {
            if (is_array($value)) self::recurKsort($value);
        }
        return ksort($array);
    }

    /**
     * Generates a random id.
     *
     * @param integer $length
     * @return string
     */
    public static function createId(int $length = 8): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generates a random UUID based on the post ID and website host.
     *
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
     *
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
     *
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

    public static function buildEventsArray($events, $start = NULL, $end = NULL): array
    {
        $eventsArray = [];
        $i = 0;
        $identifiers = [];
        $removeDuplicates = settings()->getOption('remove_duplicates') === '1';
        foreach ($events as $event) {
            if ($event == NULL) continue;
            $meta = get_post_meta($event->ID);
            if (empty($meta)) continue;
            $isImport = get_post_meta($event->ID, 'ics_feed_id', true);
            if ($isImport && $occurrences = get_post_meta($event->ID, 'ics_event_ocurrences', true)) {
                foreach ($occurrences as $startDt) {
                    $startTStmp = absint(Utils::getMeta($meta, 'start'));
                    $endTStmp = absint(Utils::getMeta($meta, 'end'));
                    $startTS = strtotime($startDt . ' ' . date('H:i', $startTStmp));
                    $endTS = strtotime($startDt . ' ' . date('H:i', $endTStmp));
                    $eventTitle = get_the_title($event->ID);
                    $location = get_post_meta($event->ID, 'location', TRUE);
                    if ($removeDuplicates && in_array($startTS . $endTS . $eventTitle . $location, $identifiers)) {
                        continue;
                    } else {
                        $eventsArray[$startTS][$i]['id'] = $event->ID;
                        $eventsArray[$startTS][$i]['start'] = $startTS;
                        $eventsArray[$startTS][$i]['end'] = $endTS;
                        $identifiers[] = $startTS . $endTS . $eventTitle . $location;
                    }
                }
            } elseif ('on' == Utils::getMeta($meta, 'repeat')) {
                $occurrences = Utils::makeRRuleSet($event->ID, $start, $end);
                foreach ($occurrences as $occurrence) {
                    $startTS = $occurrence->getTimestamp();
                    $endTStmp = absint(Utils::getMeta($meta, 'end'));
                    $endTS = strtotime(date('Y-m-d', $startTS) . ' ' . date('H:i', $endTStmp));
                    $eventTitle = get_the_title($event->ID);
                    $location = get_post_meta($event->ID, 'location', TRUE);
                    if ($removeDuplicates && in_array($startTS . $endTS . $eventTitle . $location, $identifiers)) {
                        continue;
                    } else {
                        $eventsArray[$startTS][$i]['id'] = $event->ID;
                        $eventsArray[$startTS][$i]['start'] = $startTS;
                        $eventsArray[$startTS][$i]['end'] = $endTS;
                        $identifiers[] = $startTS . $endTS . $eventTitle . $location;
                    }
                }
            } else {
                $startTS = absint(Utils::getMeta($meta, 'start'));
                $endTS = absint(Utils::getMeta($meta, 'end'));
                $eventTitle = get_the_title($event->ID);
                $location = get_post_meta($event->ID, 'location', TRUE);
                if ($removeDuplicates && in_array($startTS . $endTS . $eventTitle . $location, $identifiers)) {
                    continue;
                } else {
                    $eventsArray[$startTS][$i]['id'] = $event->ID;
                    $eventsArray[$startTS][$i]['start'] = $startTS;
                    $eventsArray[$startTS][$i]['end'] = $endTS;
                    $identifiers[] = $startTS . $endTS . $eventTitle . $location;
                }
            }
            $i++;
        }
        if ($eventsArray) {
            ksort($eventsArray);
        }
        return $eventsArray;
    }

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
        $startTS = self::getMeta($meta, 'start');
        if ($startTS == '') return [];
        if ($startTS >= strtotime('-1 year')) {
            $dtstart = date('Y-m-d H:i:s', (int)$startTS);
        } else {
            $dtstart = date('Y-m-d', strtotime('-1 year')) . ' ' . date('H:i:s', $startTS);
        }
        $endTS = self::getMeta($meta, 'end');
        if ($endTS == '') return [];
        $dtend = date('Y-m-d H:i:s', (int)$endTS);

        $rruleArgs = [
            'DTSTART' => $dtstart,
            //'COUNT' => 100,
        ];

        $lastDateTS = self::getMeta($meta, 'repeat-lastdate');
        if ($lastDateTS != '') {
            $lastDate = '@' . $lastDateTS;
        } else {
            $lastDate = '@' . strtotime('+1 year');
        }
        $rruleArgs['UNTIL'] = $lastDate;

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
        //$rrule = new RRule($rruleArgs);
        //var_dump($rrule);
        //exit;
        //foreach ( $rrule as $occurrence ) {
        //    echo $occurrence->format('r'),"<br />";
        //}
        // exit;

        return $rruleArgs;
    }

    public static function makeRRuleSet($event_id, $start = NULL, $end = NULL): array
    {
        $meta = get_post_meta($event_id);
        $rruleArgs = Utils::getMeta($meta, 'event-rrule-args');

        if ($rruleArgs = json_decode($rruleArgs, true)) {
            $rset = new RSet();
            $rset->addRRule($rruleArgs);
            $startTS = absint(Utils::getMeta($meta, 'start'));
            $startTime = date('H:i', $startTS);

            // Exceptions
            $exceptionsRaw = Utils::getMeta($meta, 'exceptions');
            if (!empty($exceptionsRaw)) {
                $exceptions = explode("\n", str_replace("\r", '', $exceptionsRaw));
                foreach ($exceptions as $exception) {
                    $rset->addExDate($exception . ' ' . $startTime);
                }
            }
            // Additions
            $additionsRaw = Utils::getMeta($meta, 'additions');
            if (!empty($additionsRaw)) {
                $additions = explode("\n", str_replace("\r", '', $additionsRaw));
                foreach ($additions as $addition) {
                    $rset->addDate($addition . ' ' . $startTime);
                }
            }

            if ($start != NULL || $end != NULL) {
                return $rset->getOccurrencesBetween($start, $end);
            } else {
                return $rset->getOccurrences();
            }
        }
        return [];
    }

    public static function getMeta($meta, $key)
    {
        if (!isset($meta[$key][0]))
            return '';
        if (str_starts_with($meta[$key][0], 'a:')) {
            return unserialize($meta[$key][0]);
        } else {
            return $meta[$key][0];
        }
    }

    public static function humanReadableRecurrence(string $rrule)
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

    public static function titleFilter($where, $wp_query){
        global $wpdb;
        $title_filter_relation = (strtoupper($wp_query->get( 'title_filter_relation'))=='OR' ? 'OR' : 'AND');
        if ($search_term = $wp_query->get( 'title_filter' )){
            $search_term = $wpdb->esc_like($search_term);
            $search_term = ' \'%' . $search_term . '%\'';
            $where .= ' '.$title_filter_relation.' ' . $wpdb->posts . '.post_title LIKE '.$search_term;
        }
        if ($search_term_exclude = $wp_query->get( 'title_filter_exclude' )){
            $search_term_exclude = $wpdb->esc_like($search_term_exclude);
            $search_term_exclude = ' \'%' . $search_term_exclude . '%\'';
            $where .= ' '.$title_filter_relation.' ' . $wpdb->posts . '.post_title NOT LIKE '.$search_term_exclude;
        }
        return $where;
    }
}
