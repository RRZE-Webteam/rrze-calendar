<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\CalendarFeed;
use RRZE\Calendar\Utils;
use ICal\ICal;
use DateTime;
use function RRZE\Calendar\plugin;

class Import
{
    const TIMEOUT_IN_SECONDS = 15;

    const DEFAULT_SPAN = 2;

    /**
     * Check whether the response contains a complete VCALENDAR wrapper.
     *
     * The bundled parser accepts truncated input such as only
     * "BEGIN:VCALENDAR", so completeness must be verified before an empty
     * result can be treated as authoritative.
     *
     * @param string $icsContent
     * @return bool
     */
    public static function isCompleteCalendar(string $icsContent): bool
    {
        $icsContent = trim($icsContent);
        $icsContent = preg_replace('/^\xEF\xBB\xBF/', '', $icsContent);
        $icsContent = str_replace(["\r\n", "\n\r", "\r"], "\n", (string) $icsContent);
        $lines = explode("\n", $icsContent);

        if (count($lines) < 2) {
            return false;
        }

        $lastLine = array_key_last($lines);
        if (
            strtoupper(trim($lines[0])) !== 'BEGIN:VCALENDAR'
            || strtoupper(trim($lines[$lastLine])) !== 'END:VCALENDAR'
        ) {
            return false;
        }

        $components = [];
        foreach ($lines as $lineNumber => $line) {
            if (str_starts_with($line, ' ') || str_starts_with($line, "\t")) {
                continue;
            }

            $line = strtoupper(trim($line));

            if (preg_match('/^BEGIN:([A-Z0-9-]+)$/', $line, $matches)) {
                if (empty($components) && $lineNumber !== 0) {
                    return false;
                }
                $components[] = $matches[1];
                continue;
            }

            if (! preg_match('/^END:([A-Z0-9-]+)$/', $line, $matches)) {
                continue;
            }

            if (array_pop($components) !== $matches[1]) {
                return false;
            }

            if (empty($components) && $lineNumber !== $lastLine) {
                return false;
            }
        }

        return empty($components);
    }

    /**
     * Validate required VEVENT properties before invoking the permissive parser.
     *
     * @param string $icsContent
     * @return bool
     */
    public static function hasValidEventComponents(string $icsContent): bool
    {
        $icsContent = str_replace(["\r\n", "\n\r", "\r"], "\n", $icsContent);
        $rawLines = explode("\n", $icsContent);
        $lines = [];

        foreach ($rawLines as $line) {
            if (
                ! empty($lines)
                && (str_starts_with($line, ' ') || str_starts_with($line, "\t"))
            ) {
                $lines[array_key_last($lines)] .= substr($line, 1);
            } else {
                $lines[] = $line;
            }
        }

        $isCancellationCalendar = (bool) preg_match('/^METHOD\s*:\s*CANCEL\s*$/mi', $icsContent);
        $event = null;
        $nestedDepth = 0;

        foreach ($lines as $line) {
            $normalized = strtoupper(trim($line));
            if ($normalized === 'BEGIN:VEVENT') {
                if ($event !== null) {
                    return false;
                }
                $event = [];
                $nestedDepth = 0;
                continue;
            }

            if ($normalized === 'END:VEVENT') {
                if (
                    $event === null
                    || $nestedDepth !== 0
                    || ! self::isValidEventComponent($event, $isCancellationCalendar)
                ) {
                    return false;
                }
                $event = null;
                $nestedDepth = 0;
                continue;
            }

            if ($event === null) {
                continue;
            }

            if (str_starts_with($normalized, 'BEGIN:')) {
                $nestedDepth++;
                continue;
            }

            if (str_starts_with($normalized, 'END:')) {
                if ($nestedDepth === 0) {
                    return false;
                }
                $nestedDepth--;
                continue;
            }

            if ($nestedDepth > 0 || ! str_contains($line, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $line, 2);
            $property = strtoupper(explode(';', $property, 2)[0]);
            if (in_array($property, ['UID', 'DTSTART', 'RECURRENCE-ID'], true)) {
                $event[$property] = trim($value);
            }
        }

        return $event === null;
    }

    /**
     * Validate one parsed VEVENT property set.
     *
     * @param array $event
     * @param bool $isCancellationCalendar
     * @return bool
     */
    private static function isValidEventComponent(
        array $event,
        bool $isCancellationCalendar
    ): bool {
        if (empty($event['UID'])) {
            return false;
        }

        if (
            ! empty($event['DTSTART'])
            && ! self::isValidIcsDateValue($event['DTSTART'])
        ) {
            return false;
        }

        if (
            ! empty($event['RECURRENCE-ID'])
            && ! self::isValidIcsDateValue($event['RECURRENCE-ID'])
        ) {
            return false;
        }

        return $isCancellationCalendar || ! empty($event['DTSTART']);
    }

    /**
     * Validate an RFC 5545 DATE or DATE-TIME value used by this importer.
     *
     * @param string $value
     * @return bool
     */
    private static function isValidIcsDateValue(string $value): bool
    {
        return (bool) preg_match(
            '/^\d{8}(?:T\d{6}Z?)?$/',
            strtoupper(trim($value))
        );
    }

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
        // Get the current timestamp according to the WordPress timezone.
        $currentTimestamp = current_time('timestamp');

        // Format the timestamp using wp_date(), which uses the WordPress timezone.
        $currentDateTime = wp_date('Y-m-d H:i:s', $currentTimestamp);

        // Ensure positive values
        $pastDays  = abs($pastDays);
        $limitDays = abs($limitDays);

        // Another date formatted using the WP timezone
        $startDate = wp_date('Ymd', $currentTimestamp);

        // Add a month to $pastDays to accommodate multi-day events that may begin out of range.
        $rangeStart = Utils::dateFormat('Y/m/d', $startDate, null, '-' . ($pastDays + 30) . 'days');
        // Extend by one week past current date.
        $rangeEnd = Utils::dateFormat('Y/m/d', $startDate, null, '+' . ($limitDays + 7) . ' days');

        // The value in years to use for indefinite, recurring events
        $defaultSpan = intval(ceil($pastDays + $limitDays) / 365) ?: self::DEFAULT_SPAN;

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
        if (empty($url)) {
            return false;
        }

        // Clear cache if requested
        if ($cache == false) {
            Cache::deleteIcalCache($url);
        }

        // Get ICS file contents
        $icsContent = Cache::getIcalCache($url);
        if (
            $icsContent !== false
            && (
                ! self::isCompleteCalendar((string) $icsContent)
                || ! self::hasValidEventComponents((string) $icsContent)
            )
        ) {
            Cache::deleteIcalCache($url);
            $icsContent = false;
        }

        if ($icsContent === false) {
            $icsContent = self::urlGetContent($url);
            if (
                self::isCompleteCalendar((string) $icsContent)
                && self::hasValidEventComponents((string) $icsContent)
            ) {
                Cache::setIcalCache($url, $icsContent);
            } else {
                return false;
            }
        }

        // WP Timezone
        $wpTz = wp_timezone();

        // ICS data is not empty
        if ($icsContent) {
            try {
                $isCancellationCalendar = (bool) preg_match('/^METHOD\s*:\s*CANCEL\s*$/mi', $icsContent);
                $parserOptions = [
                    'defaultSpan'                 => $defaultSpan,
                    'defaultTimeZone'             => $wpTz->getName(),
                    'disableCharacterReplacement' => false,
                    'skipRecurrence'              => false,
                ];

                // Cancellation messages are deltas, not snapshots. Their DTSTART
                // may be far outside the import window, especially for old
                // recurring series, so they must be parsed without date filters.
                if ($isCancellationCalendar) {
                    $parserOptions['skipRecurrence'] = true;
                } else {
                    $parserOptions['filterDaysAfter'] = $filterDaysAfter;
                    $parserOptions['filterDaysBefore'] = $filterDaysBefore;
                }

                // Parse ICS contents
                $ICal = new ICal('ICal.ics', $parserOptions);
                $ICal->initString($icsContent);
                $events = [];
                $cancellations = [];

                if ($isCancellationCalendar) {
                    foreach ($ICal->events() as $event) {
                        if (! empty($event->uid)) {
                            $cancellations[] = self::getCancellationData($event);
                        }
                    }
                } elseif ($ICal->hasEvents()) {
                    $events = $ICal->eventsFromRange($rangeStart, $rangeEnd) ?: [];
                }

                foreach ($events as $i => $event) {
                    if (strtoupper(trim((string) ($event->status ?? ''))) === 'CANCELLED') {
                        if (! empty($event->uid)) {
                            $cancellations[] = self::getCancellationData($event);
                        }
                        unset($events[$i]);
                    }
                }

                if (! empty($cancellations)) {
                    foreach ($events as $i => $event) {
                        foreach ($cancellations as $cancellation) {
                            if ((string) ($event->uid ?? '') !== $cancellation['uid']) {
                                continue;
                            }

                            if (
                                $cancellation['recurrence_date'] === ''
                                || self::getEventDate($event) === $cancellation['recurrence_date']
                            ) {
                                unset($events[$i]);
                                break;
                            }
                        }
                    }

                    foreach ($events as $event) {
                        $cancelledOccurrences = [];
                        foreach ($cancellations as $cancellation) {
                            if (
                                (string) ($event->uid ?? '') === $cancellation['uid']
                                && $cancellation['recurrence_date'] !== ''
                            ) {
                                $cancelledOccurrences[] = $cancellation['recurrence_date'];
                            }
                        }

                        if (! empty($cancelledOccurrences)) {
                            $event->additionalProperties['cancelled_occurrences'] = array_values(
                                array_unique($cancelledOccurrences)
                            );
                        }
                    }
                }

                // Only import selected events
                $include = (string) get_post_meta($feedID, CalendarFeed::FEED_INCLUDE, true);
                if ($include != '') {
                    foreach ($events as $i => $event) {
                        if (! str_contains((string) ($event->summary ?? ''), $include)) {
                            unset($events[$i]);
                        }
                    }
                }
                // Skip excluded events
                $exclude = (string) get_post_meta($feedID, CalendarFeed::FEED_EXCLUDE, true);
                if ($exclude != '') {
                    foreach ($events as $i => $event) {
                        if (str_contains((string) ($event->summary ?? ''), $exclude)) {
                            unset($events[$i]);
                        }
                    }
                }

                $events = array_values($events);
                $cancelledUids = array_values(
                    array_unique(array_column($cancellations, 'uid'))
                );

                return [
                    'events' => $events,
                    'meta' => [
                        'event_count' => count($events),
                        'source_event_count' => $ICal->eventCount,
                        'cancelled_event_count' => count($cancellations),
                        'cancelled_event_uids' => $cancelledUids,
                        'cancellations' => $cancellations,
                        'update_type' => $isCancellationCalendar ? 'cancellation' : 'snapshot',
                        'calendar_hash' => hash('sha256', $icsContent),
                        'free_busy_count' => $ICal->freeBusyCount,
                        'todo_count' => $ICal->todoCount,
                        'alarmCount' => $ICal->alarmCount
                    ]
                ];
            } catch (\Throwable $e) {
                do_action(
                    'rrze.log.error',
                    'Plugin: {plugin} ICal-Error: {error}',
                    [
                        'plugin' => plugin()->getBasename(),
                        'error' => $e->getMessage()
                    ]
                );
                return false;
            }
        }

        return false;
    }

    /**
     * Build cancellation metadata for a parsed event.
     *
     * @param object $event
     * @return array
     */
    private static function getCancellationData(object $event): array
    {
        return [
            'uid' => (string) ($event->uid ?? ''),
            'recurrence_date' => self::getEventDate($event, 'recurrence_id_array'),
        ];
    }

    /**
     * Get an event property date in the WordPress timezone.
     *
     * @param object $event
     * @param string $property
     * @return string
     */
    private static function getEventDate(object $event, string $property = 'dtstart_array'): string
    {
        $value = $event->{$property} ?? [];
        $timestamp = is_array($value) ? ($value[2] ?? null) : null;

        return $timestamp ? wp_date('Y-m-d', (int) $timestamp, wp_timezone()) : '';
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
