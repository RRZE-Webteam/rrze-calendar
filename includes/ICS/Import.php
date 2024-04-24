<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\CalendarFeed;
use RRZE\Calendar\Utils;
use ICal\ICal;
use DateTime;

class Import
{
    const TIMEOUT_IN_SECONDS = 15;

    const DEFAULT_SPAN = 2;

    /**
     * getEvents
     *
     * @param integer $feedID
     * @param boolean $cache
     * @param integer $pastDays
     * @param integer $limitDays
     * @return mixed
     */
    public static function getEvents(int $feedID, bool $cache = true, int $pastDays = 365, int $limitDays = 365)
    {
        $currentTimestamp = current_time('timestamp');
        $currentDateTime = date('Y-m-d H:i:s', $currentTimestamp);
        $pastDays = abs($pastDays);
        $limitDays = abs($limitDays);
        $startDate = date('Ymd', $currentTimestamp);

        // Add a month to $pastDays to accommodate multi-day events that may begin out of range.
        $rangeStart = Utils::dateFormat('Y/m/d', $startDate, null, '-' . ($pastDays + 30) . 'days');
        // Extend by one week past current date.
        $rangeEnd = Utils::dateFormat('Y/m/d', $startDate, null, '+' . ($limitDays + 7) . ' days');

        // The value in years to use for indefinite, recurring events
        $defaultSpan = intval(ceil($pastDays + $limitDays) / 365) ?: self::DEFAULT_SPAN;

        // WP Timezone
        $urlTz = wp_timezone();
        $defaultTimeZone = $urlTz->getName();

        // Get day counts for ICS Parser's range filters
        $nowDtm = new DateTime($currentDateTime);
        $filterDaysAfter = $nowDtm->diff(new DateTime($rangeEnd))->format('%a');
        $filterDaysBefore = $nowDtm->diff(new DateTime($rangeStart))->format('%a');

        // Fix URL protocol
        $url = get_post_meta($feedID, CalendarFeed::FEED_URL, true);
        $url = $url ?: '';
        if (strpos($url, 'webcal://') === 0) {
            $url = str_replace('webcal://', 'https://', $url);
        }

        // Get ICS file contents
        $icsContent = Cache::getIcalCache($url);
        if ($icsContent === false || $cache == false) {
            $icsContent = self::urlGetContent($url);
            if (strpos((string) $icsContent, 'BEGIN:VCALENDAR') === 0) {
                Cache::setIcalCache($url, $icsContent);
            } else {
                $icsContent = '';
            }
        }

        // ICS data is not empty
        if ($icsContent) {
            // Parse ICS contents
            $ICal = new ICal('ICal.ics', [
                'defaultSpan'                 => $defaultSpan,
                'defaultTimeZone'             => $defaultTimeZone,
                'disableCharacterReplacement' => true,
                'filterDaysAfter'             => $filterDaysAfter,
                'filterDaysBefore'            => $filterDaysBefore,
                'replaceWindowsTimeZoneIds'   => true,
                'skipRecurrence'              => false,
            ]);
            $ICal->initString($icsContent);

            // Free up some memory
            unset($icsContent);

            // Has events?
            if (
                is_object($ICal)
                && $ICal->hasEvents()
                && $events = $ICal->eventsFromRange($rangeStart, $rangeEnd)
            ) {
                // Only import selected events
                $include = (string) get_post_meta($feedID, CalendarFeed::FEED_INCLUDE, true);
                if ($include != '') {
                    foreach ($events as $i => $event) {
                        if (!str_contains($event->summary, $include)) {
                            unset($events[$i]);
                        }
                    }
                }
                // Skip excluded events
                $exclude = (string) get_post_meta($feedID, CalendarFeed::FEED_EXCLUDE, true);
                if ($exclude != '') {
                    foreach ($events as $i => $event) {
                        if (str_contains($event->summary, $exclude)) {
                            unset($events[$i]);
                        }
                    }
                }

                return [
                    'events' => $events,
                    'meta' => [
                        'event_count' => $ICal->eventCount,
                        'free_busy_count' => $ICal->freeBusyCount,
                        'todo_count' => $ICal->todoCount,
                        'alarmCount' => $ICal->alarmCount
                    ]
                ];
            }
        }

        return false;
    }

    /**
     * Retrieve file from remote server.
     *
     * @param string $url
     * @return mixed
     */
    protected static function urlGetContent(string $url)
    {
        $args = [
            'timeout' => static::TIMEOUT_IN_SECONDS,
            'sslverify' => false,
            'method' => 'GET'
        ];

        $response = wp_safe_remote_get($url, $args);
        if (wp_remote_retrieve_response_code($response) != 200) {
            return false;
        }
        return $response['body'] ?? false;
    }
}
