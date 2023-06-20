<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\{Transients, Utils};
use ICal\ICal;
use DateTime;

class Import
{
    const TIMEOUT_IN_SECONDS = 15;

    /**
     * getEvents
     *
     * @param string $url
     * @param integer $limitDays
     * @return mixed
     */
    public static function getEvents(string $url, int $limitDays = 365)
    {
        $startDate = date('Ymd', current_time('timestamp'));

        // Add a month to $rangeStart to accommodate multi-day events that may begin out of range.
        $rangeStart = Utils::dateFormat('Y/m/d', $startDate, null, '-30 days');
        // Extend by one week past current date.
        $rangeEnd = Utils::dateFormat('Y/m/d', $startDate, null, '+' . intval($limitDays + 7) . ' days');

        // Get day counts for ICS Parser's range filters
        $nowDtm = new DateTime();
        $filterDaysAfter = $nowDtm->diff(new DateTime($rangeEnd))->format('%a');
        $filterDaysBefore = $nowDtm->diff(new DateTime($rangeStart))->format('%a');

        // Get WP Timezone
        $urlTz = wp_timezone();

        // Fix URL protocol
        if (strpos($url, 'webcal://') === 0) {
            $url = str_replace('webcal://', 'https://', $url);
        }

        // Get ICS file contents
        $icsContent = Transients::getIcalCache($url);
        if ($icsContent === false) {
            $icsContent = self::urlGetContent($url);
            if (strpos((string) $icsContent, 'BEGIN:VCALENDAR') === 0) {
                Transients::setIcalCache($url, $icsContent);
            } else {
                $icsContent = '';
            }
        }

        // ICS data is not empty
        if (!empty($icsContent)) {
            // Parse ICS contents
            $ICal = new ICal('ICal.ics', [
                'defaultSpan'                 => 1,
                'defaultTimeZone'             => $urlTz->getName(),
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
            if ($ICal->hasEvents() && $events = $ICal->eventsFromRange($rangeStart, $rangeEnd)) {
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
