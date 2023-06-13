<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use DateTime;
use DateTimeZone;

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

    public static function setEventSlug(string $label, int $postId, int $eventKey): string
    {
        $label = sanitize_title_with_dashes(remove_accents($label));
        return sprintf('%1$s-%2$s-%3$s', $label, $postId, $eventKey);
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
    public static function timeFormat($timeString, $format = null)
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
        $daysOfWeek = self::daysOfWeek($format);
        $startOfWeek = get_option('start_of_week', 0);
        for ($i = 0; $i < $startOfWeek; $i++) {
            $day = $daysOfWeek[$i];
            unset($daysOfWeek[$i]);
            $daysOfWeek[$i] = $day;
        }
        return $daysOfWeek;
    }

    public static function daysOfWeek($format = null)
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
}