<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

/**
 * Parser class
 */
class Parser
{
    /**
     * Undocumented function
     *
     * @param array $icsEvents
     * @return array
     */
    public static function parseEvents($icsEvents = []): array
    {
        $icsData = [];
        foreach ($icsEvents as $event) {
            $parseEvent = self::parseEvent($event);
            $icsData[key($parseEvent)][] = current($parseEvent);
        }

        return empty($icsData) ? [date_i18n('Ymd') => []] : $icsData;
    }

    /**
     * [parseEvent description]
     * @param  object $event [description]
     * @return array         [description]
     */
    public static function parseEvent($event)
    {
        $icsData = [];

        // Get the start date and time
        $dtstartDate = substr($event->dtstart_tz, 0, 8);
        $dtstartTime = substr($event->dtstart_tz, 9, 6);
        $dtendDate = substr($event->dtend_tz, 0, 8);
        $dtendTime = substr($event->dtend_tz, 9, 6);

        // All-day events
        if (strlen($event->dtstart) == 8 || (strpos($event->dtstart, 'T000000Z') !== false && strpos($event->dtend, 'T000000Z') !== false)) {
            $dtstartDate = substr($event->dtstart, 0, 8);
            $dtendDate = substr($event->dtend, 0, 8);
            $dtstartTime = null;
            $dtendTime = null;
            $allDay = true;
        }
        // Time-specific events
        else {
            // Option to ignore time zone adjustments (@todo Find the reason this is needed and compensate automatically)
            if (!empty($tzignore)) {
                $dtstartDate = substr($event->dtstart_array[1], 0, 8);
                $dtstartTime = substr($event->dtstart_array[1], 9, 6);
                $dtendDate = substr($event->dtend_array[1], 0, 8);
                $dtendTime = substr($event->dtend_array[1], 9, 6);
            }
            $allDay = false;
        }

        // Workaround for events in feeds that do not contain an end date/time
        if (empty($dtendDate)) {
            $dtendDate = isset($dtstartDate) ? $dtstartDate : null;
        }
        if (empty($dtendTime)) {
            $dtendTime = isset($dtstartTime) ? $dtstartTime : null;
        }

        // General event item details (regardless of all-day/start/end times)
        $eventItem = [
            'uid' => self::processString(@$event->uid),
            'summary' => self::processString(@$event->summary),
            'description' => self::processString(@$event->description),
            'location' => self::processString(@$event->location),
            'organizer' => (!empty($event->organizer_array) ? $event->organizer_array : @$event->organizer),
            'url' => (!empty($event->url) ? $event->url : null),
            'attach' => (!empty($event->attach_array) ? self::parseAttachArray($event->attach_array) : null),
            'dtstart' => @$dtstartTime,
            'dtend' => @$dtendTime,
            'rrule' => (!empty($event->rrule) ? $event->rrule : '')
        ];

        // Events with different start and end dates
        if ($dtendDate != $dtstartDate) {
            $loopDate = $dtstartDate;
            while ($loopDate <= $dtendDate) {
                // Classified as an all-day event and we've hit the end date -- don't display
                if ($allDay && $loopDate == $dtendDate) {
                    break;
                }
                // Classified as an all-day event, or we're in the middle of the range -- treat as regular all-day event
                if ($allDay || ($loopDate != $dtstartDate && $loopDate != $dtendDate)) {
                    $icsData[$loopDate]['all-day'][] = array_merge($eventItem, []);
                }
                // First date in range -- treat as all-day but also show start time
                elseif ($loopDate == $dtstartDate) {
                    $icsData[$loopDate]['t' . $dtstartTime][] = array_merge($eventItem, [
                        'start' => date_i18n(get_option('time_format'), mktime(
                            substr($dtstartTime, 0, 2),
                            substr($dtstartTime, 2, 2),
                            substr($dtstartTime, 4, 2),
                            substr($dtstartDate, 4, 2),
                            substr($dtstartDate, 6, 2),
                            substr($dtstartDate, 0, 2)
                        )),
                    ]);
                }
                // Last date in range -- treat as all-day but also show end time
                elseif ($loopDate == $dtendDate) {
                    // If event ends at midnight, skip
                    if ($dtendTime != '000000') {
                        $icsData[$loopDate]['t' . $dtendTime][] = array_merge($eventItem, [
                            'sublabel' => __('Ends', 'r34ics') . ' ' . date_i18n(get_option('time_format'), mktime(
                                substr($dtendTime, 0, 2),
                                substr($dtendTime, 2, 2),
                                substr($dtendTime, 4, 2),
                                substr($dtendDate, 4, 2),
                                substr($dtendDate, 6, 2),
                                substr($dtendDate, 0, 2)
                            )),
                            'end' => date_i18n(get_option('time_format'), mktime(
                                substr($dtendTime, 0, 2),
                                substr($dtendTime, 2, 2),
                                substr($dtendTime, 4, 2),
                                substr($dtendDate, 4, 2),
                                substr($dtendDate, 6, 2),
                                substr($dtendDate, 0, 2)
                            )),
                        ]);
                    }
                }
                $loopDate = date_i18n('Ymd', mktime(0, 0, 0, intval(substr($loopDate, 4, 2)), intval(substr($loopDate, 6, 2)) + 1, intval(substr($loopDate, 0, 4))));
            }
        }
        // All-day events
        elseif ($allDay) {
            $icsData[$dtstartDate]['all-day'][] = array_merge($eventItem, []);
        }
        // Events with start/end times
        else {
            $icsData[$dtstartDate]['t' . $dtstartTime][] = array_merge($eventItem, [
                'start' => date_i18n(get_option('time_format'), mktime(
                    substr($dtstartTime, 0, 2),
                    substr($dtstartTime, 2, 2),
                    substr($dtstartTime, 4, 2),
                    substr($dtstartDate, 4, 2),
                    substr($dtstartDate, 6, 2),
                    substr($dtstartDate, 0, 2)
                )),
                'end' => date_i18n(get_option('time_format'), mktime(
                    substr($dtendTime, 0, 2),
                    substr($dtendTime, 2, 2),
                    substr($dtendTime, 4, 2),
                    substr($dtendDate, 4, 2),
                    substr($dtendDate, 6, 2),
                    substr($dtendDate, 0, 2)
                )),
            ]);
        }

        return $icsData;
    }

    /**
     * Undocumented function
     *
     * @param [type] $string
     * @param boolean $removeTrailingNewLines
     * @return string
     */
    protected static function processString($string, $removeTrailingNewLines = FALSE): string
    {
        if ($removeTrailingNewLines) {
            $string = trim($string, PHP_EOL);
        }

        return nl2br($string);
    }

    /**
     * Undocumented function
     *
     * @param [type] $attach
     * @return string
     */
    protected static function parseAttachArray($attach): string
    {
        if (empty($attach) || !is_array($attach) || count($attach) != 2) {
            return false;
        }

        // Determine file/URL properties
        $url = $attach[1];
        $mime = isset($attach[0]['FMTTYPE']) ? $attach[0]['FMTTYPE'] : null;
        $filename = isset($attach[0]['FILENAME']) ? $attach[0]['FILENAME'] : pathinfo($url, PATHINFO_BASENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        // Return images as an image tag (MIME type MUST be passed or this may not actually be a direct image link (e.g. a Google Drive preview link)
        if (strpos($mime, 'image/') === 0) {
            return '<img src="' . esc_url($url) . '" alt="" style="position: relative; height: auto; width: 100%;" />';
        }

        // Return PDFs as download
        elseif ($mime == 'application/pdf' || $ext == 'pdf') {
            return '<a href="' . esc_url($url) . '" download="' . $filename . '">' . $filename . '</a>';
        }

        // Return others as clickable links
        else {
            return '<a href="' . esc_url($url) . '" target="_blank">' . $filename . '</a>';
        }

        // Do nothing with other files
        return '';
    }
}
