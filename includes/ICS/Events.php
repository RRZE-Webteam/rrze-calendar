<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\CPT\{CalendarEvent, CalendarFeed};

class Events
{
    private static $pastDays = 365;

    private static $limitDays = 365;

    private static $syncLocks = [];

    public static function updateFeedsItems()
    {
        self::deleteUnlinkedEvents();

        $feeds = self::getFeeds();
        foreach ($feeds as $post) {
            if (! self::acquireSyncLock($post->ID)) {
                continue;
            }

            try {
                if ($post->post_status == 'publish') {
                    $pastDays = get_post_meta($post->ID, CalendarFeed::FEED_PAST_DAYS, true) ?: self::$pastDays;
                    $pastDays = absint($pastDays);
                    $storedState = self::getStoredState($post->ID);
                    if (self::updateItems($post->ID, true, $pastDays)) {
                        if (! self::insertData($post->ID)) {
                            self::restoreStoredState($post->ID, $storedState);
                        }
                    }
                } else {
                    self::deleteEvent($post->ID);
                }
            } finally {
                self::releaseSyncLock($post->ID);
            }
        }
    }

    private static function getFeeds(array $postIn = [])
    {
        $args = [
            'numberposts' => -1,
            'post_type'   => CalendarFeed::POST_TYPE,
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private', 'trash'],
        ];

        if (! empty($postIn)) {
            $args = array_merge($args, ['post__in' => $postIn]);
        }

        return get_posts($args);
    }

    public static function updateItems(int $postId, bool $cache = true, int $pastDays = 365, int $limitDays = 365): bool
    {
        $events = Import::getEvents($postId, $cache, $pastDays, $limitDays);

        return self::storeItems($postId, $events);
    }

    /**
     * Store a previously fetched feed result.
     *
     * @param int $postId
     * @param array|false $events
     * @return bool
     */
    public static function storeItems(int $postId, array|false $events): bool
    {
        if ($events === false) {
            update_post_meta($postId, CalendarFeed::FEED_DATETIME, current_time('mysql', true));
            update_post_meta(
                $postId,
                CalendarFeed::FEED_ERROR,
                __('The calendar could not be retrieved or is incomplete.', 'rrze-calendar')
            );

            return false;
        }

        $items = ! empty($events['events']) ? $events['events'] : [];
        $meta  = ! empty($events['meta']) ? $events['meta'] : [];
        $isCancellation = ($meta['update_type'] ?? 'snapshot') === 'cancellation';
        if ($isCancellation) {
            $items = self::filterStoredItemsForCancellations(
                (array) get_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, true),
                (array) ($meta['cancellations'] ?? [])
            );
        }
        if (empty($items) && ! $isCancellation) {
            $meta['event_count'] = self::countEvents($postId);
        }
        $error = empty($items) && ! $isCancellation
            ? __('No active events found.', 'rrze-calendar')
            : '';

        // Store last fetch time in GMT (2nd param true => GMT date)
        update_post_meta($postId, CalendarFeed::FEED_DATETIME, current_time('mysql', true));
        update_post_meta($postId, CalendarFeed::FEED_ERROR, $error);
        update_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, $items);
        update_post_meta($postId, CalendarFeed::FEED_EVENTS_META, $meta);

        return true;
    }

    /**
     * Remove cancellation targets from the stored snapshot used by the feed UI.
     *
     * @param array $items
     * @param array $cancellations
     * @return array
     */
    private static function filterStoredItemsForCancellations(
        array $items,
        array $cancellations
    ): array {
        foreach ($items as $key => $event) {
            $uid = (string) ($event->uid ?? '');
            foreach ($cancellations as $cancellation) {
                if (($cancellation['uid'] ?? '') !== $uid) {
                    continue;
                }

                $recurrenceDate = (string) ($cancellation['recurrence_date'] ?? '');
                $eventTimestamp = $event->dtstart_array[2] ?? null;
                $eventDate = $eventTimestamp
                    ? wp_date('Y-m-d', (int) $eventTimestamp, wp_timezone())
                    : '';

                if ($recurrenceDate === '' || $eventDate === $recurrenceDate) {
                    unset($items[$key]);
                    break;
                }
            }
        }

        return array_values($items);
    }

    /**
     * Capture stored feed data so a failed rebuild can restore it.
     *
     * @param int $postId
     * @return array
     */
    public static function getStoredState(int $postId): array
    {
        return [
            'items' => get_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, true),
            'meta' => get_post_meta($postId, CalendarFeed::FEED_EVENTS_META, true),
        ];
    }

    /**
     * Restore feed data after a failed or protected rebuild.
     *
     * @param int $postId
     * @param array $state
     * @return void
     */
    public static function restoreStoredState(int $postId, array $state): void
    {
        update_post_meta(
            $postId,
            CalendarFeed::FEED_EVENTS_ITEMS,
            $state['items'] ?? []
        );
        update_post_meta(
            $postId,
            CalendarFeed::FEED_EVENTS_META,
            $state['meta'] ?? []
        );
    }

    /**
     * Get ICS feed items
     *
     * @param int $postId Feed Post ID
     * @param int $pastDays
     * @param int $limitDays
     * @return array
     */
    private static function getItems(int $postId, int $pastDays, int $limitDays)
    {
        $feedItems = [];

        if (
            get_post_status($postId) !== 'publish'
            || ! $items = get_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, true)
        ) {
            return $feedItems;
        }

        $feedItems['events'] = [];
        $feedItems['tz']     = get_option('timezone_string');

        // Set display date range.
        $pastDays  = abs($pastDays);
        $limitDays = abs($limitDays);

        $startTimestamp = current_time('timestamp');
        $startDate      = wp_date('Ymd', $startTimestamp);

        // IMPORTANT: fix PHP operator precedence by building proper relative strings.
        // Previously: '-' . $pastDays + 30 . ' days' → produced nonsense.
        $firstDate = Utils::dateFormat(
            'Ymd',
            $startDate,
            null,
            sprintf('-%d days', $pastDays + 30)
        );

        $limitDate = Utils::dateFormat(
            'Ymd',
            $startDate,
            null,
            sprintf('+%d days', $limitDays + 7)
        );

        // Set earliest and latest dates (YYYYMM)
        $feedItems['earliest'] = substr($firstDate, 0, 6);
        $feedItems['latest']   = substr($limitDate, 0, 6);

        // Get timezone
        $urlTz = wp_timezone();

        // Assemble events
        foreach ($items as $eventKey => $event) {
            self::assembleEvents($postId, $event, $eventKey, $urlTz, $feedItems);
        }

        // If no events, create empty array for today
        if (empty($feedItems['events'])) {
            $feedItems['events'] = [Utils::dateFormat('Ymd') => []];
        }

        // Sort events and split into year/month/day groups
        ksort($feedItems['events']);

        foreach ((array) $feedItems['events'] as $date => $events) {
            // Only reorganize dates that are in the proper date range
            if ($date >= $firstDate && $date <= $limitDate) {
                // Get the date's events in order
                ksort($events);

                // Fix recurrence exceptions
                $events = self::fixRecurrenceExceptions($events);

                // Insert the date's events into the year/month/day hierarchical array
                $year  = substr($date, 0, 4);
                $month = substr($date, 4, 2);
                $day   = substr($date, 6, 2);

                $feedItems['events'][$year][$month][$day] = $events;
            }

            // Remove the old flat date item from the array
            unset($feedItems['events'][$date]);
        }

        // Add empty event arrays for months in range
        for ($i = substr($firstDate, 0, 6); $i <= substr($limitDate, 0, 6); $i++) {
            $Y = substr($i, 0, 4);
            $m = substr($i, 4, 2);
            $mi = (int) $m;

            if ($mi < 1 || $mi > 12) {
                continue;
            }
            if (! isset($feedItems['events'][$Y][$m])) {
                $feedItems['events'][$Y][$m] = null;
            }
        }

        // Sort events
        foreach (array_keys((array) $feedItems['events']) as $keyYear) {
            ksort($feedItems['events'][$keyYear]);
        }
        ksort($feedItems['events']);

        return $feedItems;
    }

    private static function assembleEvents($postId, $event, $eventKey, $urlTz, &$feedItems)
    {
        // Set start and end dates for event (Ymd in WP timezone)
        $dtstartDate = wp_date('Ymd', $event->dtstart_array[2], $urlTz);

        // Conditional is for events that are missing DTEND altogether
        $dtendTimestamp = isset($event->dtend_array[2]) ? $event->dtend_array[2] : $event->dtstart_array[2];
        $dtendDate      = wp_date('Ymd', $dtendTimestamp, $urlTz);

        // All-day events
        if (
            strlen($event->dtstart) == 8
            || (strpos($event->dtstart, 'T000000') !== false && strpos($event->dtend, 'T000000') !== false)
        ) {
            $dtstartTime = null;
            $dtendTime   = null;
            $allDay      = true;
        } else {
            // Start/end times (His in WP timezone)
            $dtstartTime = wp_date('His', $event->dtstart_array[2], $urlTz);
            $dtendTime   = wp_date('His', $dtendTimestamp, $urlTz);
            $allDay      = false;
        }

        // Workaround for events in feeds that do not contain an end date/time
        if (empty($dtendDate)) {
            $dtendDate = $dtstartDate ?: null;
        }
        if (empty($dtendTime)) {
            $dtendTime = $dtstartTime ?: null;
        }

        // Summary (Title)
        // FIX: original code used "empty($event->summary) ?: $event->summary", which returns boolean true on empty.
        $summary = ! empty($event->summary) ? $event->summary : '';

        // Get the terms from the category (top-level only)
        $categories = [];
        $terms      = wp_get_post_terms(
            $postId,
            CalendarEvent::TAX_CATEGORY,
            [
                'fields' => 'ids',
                'parent' => 0,
            ]
        );
        if (! empty($terms) && ! is_wp_error($terms)) {
            $categories = [$terms[0]];
        }

        // Get the terms from the tag
        $tags  = [];
        $terms = wp_get_post_terms(
            $postId,
            CalendarEvent::TAX_TAG
        );
        if (! empty($terms) && ! is_wp_error($terms)) {
            foreach ($terms as $term) {
                // FIX: use term_id instead of id
                $tags[] = $term->term_id;
            }
        }

        // General event item details (regardless of all-day/start/end times)
        $eventItem = [
            'post_id'        => $postId,
            'feed_url'       => get_post_meta($postId, CalendarFeed::FEED_URL, true),
            'timezone'       => $urlTz->getName(),
            'summary'        => Utils::translateOutlookSummaryToGerman($summary),
            'categories'     => $categories,
            'tags'           => $tags,
            'uid'            => $event->uid,
            'dtstart_date'   => ! empty($dtstartDate) ? $dtstartDate : '',
            'dtstart_time'   => ! empty($dtstartTime) ? $dtstartTime : '',
            'dtend_date'     => ! empty($dtendDate) ? $dtendDate : '',
            'dtend_time'     => ! empty($dtendTime) ? $dtendTime : '',
            'description'    => $event->description,
            'location'       => $event->location,
            'organizer'      => $event->organizer ? $event->organizer_array : '',
            'url'            => $event->url ?? '',
            'rrule'          => $event->rrule ?? '',
            'readable_rrule' => $event->rrule ? Utils::humanReadableRecurrence($event->rrule) : '',
            'exdate_array'   => ! empty($event->exdate_array) ? $event->exdate_array : [],
            'rdate_array'    => ! empty($event->rdate_array) ? $event->rdate_array : [],
            'cancelled_occurrences' => ! empty($event->cancelled_occurrences)
                ? (array) $event->cancelled_occurrences
                : [],
        ];

        // Events with different start and end dates
        if (
            $dtendDate != $dtstartDate &&
            // Events that are NOT multiday, but end at midnight of the start date!
            ! (
                $dtendDate == Utils::dateFormat('Ymd', $dtstartDate, $urlTz, '+1 day')
                && $dtendTime == '000000'
            )
        ) {
            $loopDate = $dtstartDate;

            while ($loopDate <= $dtendDate) {
                // Classified as an all-day event and we've hit the end date
                if ($allDay && $loopDate == $dtendDate) {
                    break;
                }

                // Multi-day events may be given with end date/time as midnight of the NEXT day
                $actualEndDate = (! empty($allDay) && empty($dtendTime))
                    ? Utils::dateFormat('Ymd', $dtendDate, $urlTz, '-1 day')
                    : $dtendDate;

                if ($dtstartDate == $actualEndDate) {
                    $feedItems['events'][$dtstartDate]['all-day'][] = $eventItem;
                    break;
                }

                // Get full date/time range of multi-day event
                $eventItem['multiday'] = [
                    'event_key'  => $eventKey,
                    'date_start' => $dtstartDate,
                    'start_time' => $dtstartTime,
                    'date_end'   => $actualEndDate,
                    'end_time'   => $dtendTime,
                    'all_day'    => $allDay,
                ];

                // Classified as an all-day event, or we're in the middle of the range -- treat as regular all-day event
                // For all-day events, $dtendDate is midnight on the date after the event ends
                if ($allDay || ($loopDate != $dtstartDate && $loopDate != $dtendDate)) {
                    $eventItem['multiday']['position'] = 'middle';

                    if ($loopDate == $dtstartDate) {
                        $eventItem['multiday']['position'] = 'first';
                    } elseif ($loopDate == $actualEndDate) {
                        $eventItem['multiday']['position'] = 'last';
                    }

                    $eventItem['start'] = $eventItem['end'] = null;
                    $feedItems['events'][$loopDate]['all-day'][] = $eventItem;
                }
                // First date in range: show start time
                elseif ($loopDate == $dtstartDate) {
                    $eventItem['start']                    = Utils::timeFormat($dtstartTime);
                    $eventItem['end']                      = null;
                    $eventItem['multiday']['position']     = 'first';
                    $feedItems['events'][$loopDate]['t' . $dtstartTime][] = $eventItem;
                }
                // Last date in range: show end time
                elseif ($loopDate == $actualEndDate) {
                    // If event ends at midnight, skip
                    if (! empty($dtendTime) && $dtendTime != '000000') {
                        // FIX: use correct textdomain
                        $eventItem['sublabel']                = __('Ends', 'rrze-calendar') . ' ' . Utils::timeFormat($dtendTime);
                        $eventItem['start']                    = null;
                        $eventItem['end']                      = Utils::timeFormat($dtendTime);
                        $eventItem['multiday']['position']     = 'last';
                        $feedItems['events'][$loopDate]['t' . $dtendTime][] = $eventItem;
                    }
                }

                $loopDate = Utils::dateFormat('Ymd', $loopDate, $urlTz, '+1 day');
            }
        }
        // All-day events
        elseif ($allDay) {
            $feedItems['events'][$dtstartDate]['all-day'][] = $eventItem;
        }
        // Events with start/end times
        else {
            $eventItem['start'] = Utils::timeFormat($dtstartTime);
            $eventItem['end']   = Utils::timeFormat($dtendTime);
            $feedItems['events'][$dtstartDate]['t' . $dtstartTime][] = $eventItem;
        }
    }

    /**
     * Get the list of events from ICS Feed to be displayed in the list table.
     * 
     * @param string $searchTerm Search term in event titles
     * @return array
     */
    public static function getListTableData(string $searchTerm = '')
    {
        $items  = [];
        $postId = 0;

        $screen   = get_current_screen();
        $screenId = $screen ? $screen->id : '';

        if ($screenId == CalendarFeed::POST_TYPE) {
            global $post;
            $postType = get_post_type($post);
            if ($postType === CalendarFeed::POST_TYPE) {
                $pastDays  = get_post_meta($post->ID, CalendarFeed::FEED_PAST_DAYS, true) ?: self::$pastDays;
                $pastDays  = absint($pastDays) + 30;
                $limitDays = self::$limitDays + 7;
                $postId    = $post->ID;
                $items     = self::getItems($postId, $pastDays, $limitDays);
            }
        }

        return count($items) ? self::getListData($postId, $items, $searchTerm) : $items;
    }

    /**
     * Get the list of events to be displayed when the Feed is edited.
     *
     * @param int    $postId   Post ID
     * @param array  $items    Feed items split into year/month/day groups
     * @param string $searchTerm Search term in event titles
     * @return array
     */
    public static function getListData(int $postId, array $items, string $searchTerm = ''): array
    {
        $data       = [];
        $dateFormat = __('m-d-Y', 'rrze-calendar');

        $i                        = 0;
        $multidayEventKeysUsed    = [];

        if (empty($items) || empty($items['events'])) {
            return $data;
        }

        foreach (array_keys((array) $items['events']) as $year) {
            for ($m = 1; $m <= 12; $m++) {
                $month = $m < 10 ? '0' . $m : (string) $m;
                $ym    = $year . $month;

                if ($ym < $items['earliest']) {
                    continue;
                }
                if ($ym > $items['latest']) {
                    break 2;
                }

                if (isset($items['events'][$year][$month])) {
                    foreach ((array) $items['events'][$year][$month] as $day => $dayEvents) {
                        // Pull out multi-day events and list them separately first
                        foreach ((array) $dayEvents as $time => $events) {
                            foreach ((array) $events as $eventKey => $event) {
                                if (empty($event['multiday'])) {
                                    continue;
                                }

                                if (in_array($event['multiday']['event_key'], $multidayEventKeysUsed, true)) {
                                    continue;
                                }

                                // Event meta
                                $data[$i]['post_id']  = $event['post_id'];
                                $data[$i]['feed_url'] = $event['feed_url'];
                                $data[$i]['timezone'] = $event['timezone'];
                                $data[$i]['uid']      = $event['uid'];

                                // Event summary (title)
                                $summary = $event['summary'];
                                if ($searchTerm && stripos($summary, $searchTerm) === false) {
                                    continue;
                                }
                                $data[$i]['summary'] = $summary;

                                // Multiday
                                $data[$i]['multiday'] = true;

                                // Format date/time
                                $tz = wp_timezone();

                                $start = new \DateTime($event['multiday']['date_start'], $tz);
                                $end   = new \DateTime($event['multiday']['date_end'], $tz);

                                $mdDtStart = $start->format('m-d-Y');
                                $mdDtEnd   = $end->format('m-d-Y');

                                $dtStart   = $start->format('Y-m-d');
                                $dtEnd     = $end->format('Y-m-d');

                                if ($time !== 'all-day') {
                                    $mdDtStart .= ', ' . Utils::timeFormat($event['multiday']['start_time']);
                                    $mdDtEnd   .= ', ' . Utils::timeFormat($event['multiday']['end_time']);
                                    $dtStart   .= ', ' . Utils::timeFormat($event['multiday']['start_time'], 'H:i:s');
                                    $dtEnd     .= ', ' . Utils::timeFormat($event['multiday']['end_time'], 'H:i:s');
                                } else {
                                    $data[$i]['allday'] = true;
                                }

                                // Date/time
                                $data[$i]['dt_start']      = $dtStart;
                                $data[$i]['dt_end']        = $dtEnd;
                                $data[$i]['readable_date'] = $mdDtStart . ' &mdash; ' . $mdDtEnd;

                                // RRULE/FREQ
                                if (! empty($event['rrule'])) {
                                    $data[$i]['rrule']          = $event['rrule'];
                                    $data[$i]['readable_rrule'] = $event['readable_rrule'];
                                }

                                // EXDATE
                                if (! empty($event['exdate_array'])) {
                                    $data[$i]['exdate_array'] = $event['exdate_array'];
                                }

                                // RDATE
                                if (! empty($event['rdate_array'])) {
                                    $data[$i]['rdate_array'] = $event['rdate_array'];
                                }

                                if (! empty($event['cancelled_occurrences'])) {
                                    $data[$i]['cancelled_occurrences'] = $event['cancelled_occurrences'];
                                }

                                // Location
                                $data[$i]['location'] = $event['location'];

                                // Organizer
                                $data[$i]['organizer'] = $event['organizer'];

                                // Description
                                $data[$i]['description'] = $event['description'];

                                // Now we use this event key for the next multiday event
                                $multidayEventKeysUsed[] = $event['multiday']['event_key'];
                                $i++;

                                // Remove event from array (to skip day if it only has multi-day events)
                                unset($dayEvents[$time][$eventKey]);
                            }

                            // Remove time from array if all of its events have been removed
                            if (empty($dayEvents[$time])) {
                                unset($dayEvents[$time]);
                            }
                        }

                        // Skip day if all of its events were multi-day
                        if (empty($dayEvents)) {
                            continue;
                        }

                        // Loop through day events
                        foreach ((array) $dayEvents as $time => $events) {
                            foreach ((array) $events as $event) {
                                if (! empty($event['multiday'])) {
                                    continue;
                                }

                                // NOTE: old condition using self::$limitDays was bogus (mixed ints and YYYYMMDD string).
                                // Removed because getItems() already limits the date range.

                                // Event meta
                                $data[$i]['post_id']  = $event['post_id'];
                                $data[$i]['feed_url'] = $event['feed_url'];
                                $data[$i]['timezone'] = $event['timezone'];
                                $data[$i]['uid']      = $event['uid'];

                                // Event summary (title)
                                $summary = $event['summary'];
                                if ($searchTerm && stripos($summary, $searchTerm) === false) {
                                    continue;
                                }
                                $data[$i]['summary'] = $summary;

                                // Date/time
                                $mdate   = Utils::dateFormat($dateFormat, $day . '-' . $month . '-' . $year);
                                $dtStart = Utils::dateFormat('Y-m-d', $day . '-' . $month . '-' . $year);
                                $dtEnd   = $dtStart;
                                $mtime   = '';

                                if ($time !== 'all-day') {
                                    if (! empty($event['start'])) {
                                        $mtime   = ' ' . $event['start'];
                                        $dtStart = $dtStart . ' ' . $event['start'];

                                        if (! empty($event['end']) && $event['end'] != $event['start']) {
                                            $mtime .= ' &mdash; ' . $event['end'];
                                            $dtEnd  = $dtEnd . ' ' . $event['end'];
                                        } else {
                                            $dtEnd = $dtStart;
                                        }
                                    }
                                } else {
                                    $data[$i]['allday'] = true;
                                }

                                $data[$i]['dt_start']      = $dtStart;
                                $data[$i]['dt_end']        = $dtEnd;
                                $data[$i]['readable_date'] = $mdate . $mtime;

                                // RRULE/FREQ
                                if (! empty($event['rrule'])) {
                                    $data[$i]['rrule']          = $event['rrule'];
                                    $data[$i]['readable_rrule'] = $event['readable_rrule'];
                                }

                                // EXDATE
                                if (! empty($event['exdate_array'])) {
                                    $data[$i]['exdate_array'] = $event['exdate_array'];
                                }

                                // RDATE
                                if (! empty($event['rdate_array'])) {
                                    $data[$i]['rdate_array'] = $event['rdate_array'];
                                }

                                if (! empty($event['cancelled_occurrences'])) {
                                    $data[$i]['cancelled_occurrences'] = $event['cancelled_occurrences'];
                                }

                                // Location
                                $data[$i]['location'] = $event['location'];

                                // Organizer
                                $data[$i]['organizer'] = $event['organizer'];

                                // Description
                                $data[$i]['description'] = $event['description'];

                                $i++;
                            }
                        }
                    }
                }
            }
        }

        // RRULE occurrences
        $rruleEventUidUsed = [];
        $ocurrences        = [];

        foreach ($data as $key => $event) {
            $dt = Utils::parseAnyDatetime($event['dt_start']);
            $dt->setTimezone(wp_timezone());

            if (in_array($event['uid'], $rruleEventUidUsed, true) && isset($event['rrule'])) {
                $ocurrences[$event['uid']][] = $dt->format('Y-m-d');
                unset($data[$key]);
                continue;
            }

            if (isset($event['rrule'])) {
                $rruleEventUidUsed[]        = $event['uid'];
                $ocurrences[$event['uid']][] = $dt->format('Y-m-d');
            }
        }

        foreach ($data as $key => $event) {
            if (isset($ocurrences[$event['uid']]) && isset($event['rrule'])) {
                $data[$key]['ocurrences'] = $ocurrences[$event['uid']];
            }
        }

        $meta                = get_post_meta($postId, CalendarFeed::FEED_EVENTS_META, true);
        $meta['event_count'] = count($data);
        update_post_meta($postId, CalendarFeed::FEED_EVENTS_META, $meta);

        return $data;
    }

    /**
     * Fix RECURRENCE-ID issue (Outlook/Office 365).
     *
     * @param array $events
     * @return array
     */
    private static function fixRecurrenceExceptions(array $events): array
    {
        $recurrenceExceptions = [];

        foreach ($events as $time => $timeEvents) {
            if (! is_array($timeEvents)) {
                continue;
            }
            foreach ($timeEvents as $teEvent) {
                if (! empty($teEvent['recurrence_id'])) {
                    $recurrenceExceptions[$teEvent['uid']] = $time;
                }
            }
        }

        if (! empty($recurrenceExceptions)) {
            foreach ($recurrenceExceptions as $reUid => $reTime) {
                foreach ($events as $time => $timeEvents) {
                    if (! is_array($timeEvents)) {
                        continue;
                    }
                    foreach ($timeEvents as $te_key => $teEvent) {
                        if (empty($teEvent['recurrence_id']) && $teEvent['uid'] == $reUid) {
                            unset($events[$time][$te_key]);
                            break 2;
                        }
                    }
                }
            }
        }

        return $events;
    }

    /**
     * Insert ICS event data in the CalendarEvent::POST_TYPE post type.
     *
     * @param int $postId
     * @param bool $allowDestructiveDelete
     * @return bool
     */
    public static function insertData(int $postId, bool $allowDestructiveDelete = false): bool
    {
        $items = [];
        $post  = get_post($postId);
        $meta  = get_post_meta($postId, CalendarFeed::FEED_EVENTS_META, true);
        $meta  = is_array($meta) ? $meta : [];

        if (($meta['update_type'] ?? 'snapshot') === 'cancellation') {
            $cancellations = (array) ($meta['cancellations'] ?? []);
            if (empty($cancellations)) {
                $cancellations = array_map(
                    fn($uid) => ['uid' => (string) $uid, 'recurrence_date' => ''],
                    (array) ($meta['cancelled_event_uids'] ?? [])
                );
            }

            if (
                self::cancellationsWouldDeleteAll($postId, $cancellations)
                && ! $allowDestructiveDelete
            ) {
                $meta['event_count'] = self::countEvents($postId);
                update_post_meta($postId, CalendarFeed::FEED_EVENTS_META, $meta);
                update_post_meta(
                    $postId,
                    CalendarFeed::FEED_ERROR,
                    __('The cancellation would delete all imported events and requires manual confirmation.', 'rrze-calendar')
                );
                return false;
            }

            self::applyCancellations($postId, $cancellations);
            $meta['event_count'] = self::countEvents($postId);
            update_post_meta($postId, CalendarFeed::FEED_EVENTS_META, $meta);
            update_post_meta($postId, CalendarFeed::FEED_ERROR, '');
            return true;
        }

        if (get_post_type($post) === CalendarFeed::POST_TYPE) {
            $pastDays  = get_post_meta($post->ID, CalendarFeed::FEED_PAST_DAYS, true) ?: self::$pastDays;
            $pastDays  = absint($pastDays) + 30;
            $limitDays = self::$limitDays + 7;
            $items     = self::getItems($postId, $pastDays, $limitDays);
        }

        $items = count($items) ? self::getListData($postId, $items) : $items;

        if (! count($items)) {
            if ($allowDestructiveDelete) {
                self::deleteEvent($postId);
                $meta['event_count'] = 0;
                update_post_meta($postId, CalendarFeed::FEED_EVENTS_META, $meta);
            } elseif (self::countEvents($postId) > 0) {
                update_post_meta(
                    $postId,
                    CalendarFeed::FEED_ERROR,
                    __('The update would delete all imported events and requires manual confirmation.', 'rrze-calendar')
                );
                return false;
            }
            return true;
        }

        $oldEventIds = self::getEventIds($postId);
        $newEventIds = [];

        foreach ($items as $event) {
            $eventId = self::insertEventPost($postId, $post, $event);
            if ($eventId === false) {
                self::deleteEventIds($newEventIds);
                $meta['event_count'] = count($oldEventIds);
                update_post_meta($postId, CalendarFeed::FEED_EVENTS_META, $meta);
                update_post_meta(
                    $postId,
                    CalendarFeed::FEED_ERROR,
                    __('The feed was retrieved, but replacement events could not be created. Existing events were preserved.', 'rrze-calendar')
                );
                return false;
            }

            $newEventIds[] = $eventId;
        }

        self::deleteEventIds($oldEventIds);
        update_post_meta($postId, CalendarFeed::FEED_ERROR, '');

        return true;
    }

    /**
     * Create one imported event post and its required metadata.
     *
     * @param int $feedId
     * @param object $feedPost
     * @param array $event
     * @return int|false
     */
    private static function insertEventPost(int $feedId, object $feedPost, array $event): int|false
    {
        if (empty($event['uid']) || empty($event['dt_start']) || empty($event['dt_end'])) {
            return false;
        }

        $args = [
            'post_author'  => $feedPost->post_author,
            'post_title'   => $event['summary'] ?? '',
            'post_content' => '',
            'post_excerpt' => $event['description'] ?? '',
            'post_type'    => CalendarEvent::POST_TYPE,
            'post_status'  => 'publish',
        ];

        $eventId = wp_insert_post($args, true, false);
        if (is_wp_error($eventId) || ! $eventId) {
            return false;
        }

        try {
            $dtStart = Utils::wpLocalToTimestamp((string) $event['dt_start']);
            $dtEnd   = Utils::wpLocalToTimestamp((string) $event['dt_end']);
            $requiredMeta = [
                'event-uid' => $event['uid'],
                'start' => $dtStart,
                'end' => $dtEnd,
                'ics_feed_id' => $feedId,
                'ics_event_meta' => $event,
            ];

            foreach ($requiredMeta as $key => $value) {
                if (add_post_meta($eventId, $key, $value, true) === false) {
                    wp_delete_post($eventId, true);
                    return false;
                }
            }

            add_post_meta($eventId, 'description', $event['description'] ?? '', true);
            add_post_meta($eventId, 'location', $event['location'] ?? '', true);

            if (! empty($event['allday'])) {
                add_post_meta($eventId, 'all-day', 'on', true);
            }

            if (! empty($event['ocurrences'])) {
                add_post_meta($eventId, 'ics_event_ocurrences', $event['ocurrences'], true);
            }

            foreach ([CalendarEvent::TAX_CATEGORY, CalendarEvent::TAX_TAG] as $taxonomy) {
                $terms = get_the_terms($feedId, $taxonomy);
                if (! $terms || is_wp_error($terms)) {
                    continue;
                }

                $result = wp_set_post_terms(
                    $eventId,
                    wp_list_pluck($terms, 'term_id'),
                    $taxonomy
                );
                if (is_wp_error($result)) {
                    wp_delete_post($eventId, true);
                    return false;
                }
            }
        } catch (\Throwable $e) {
            wp_delete_post($eventId, true);
            return false;
        }

        return (int) $eventId;
    }

    public static function deleteEvent(int $feedId)
    {
        self::deleteEventIds(self::getEventIds($feedId));
    }

    /**
     * Get imported event IDs for a feed.
     *
     * @param int $feedId
     * @return array
     */
    private static function getEventIds(int $feedId): array
    {
        return array_map(
            'absint',
            get_posts(
                [
                    'fields'         => 'ids',
                    'meta_key'       => 'ics_feed_id',
                    'meta_value'     => $feedId,
                    'post_type'      => CalendarEvent::POST_TYPE,
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                ]
            )
        );
    }

    /**
     * Permanently delete event posts by ID.
     *
     * @param array $postIds
     * @return void
     */
    private static function deleteEventIds(array $postIds): void
    {
        foreach ($postIds as $postId) {
            wp_delete_post(absint($postId), true);
        }
    }

    /**
     * Count published events imported from a feed.
     *
     * @param int $feedId
     * @return int
     */
    public static function countEvents(int $feedId): int
    {
        $query = new \WP_Query(
            [
                'fields'         => 'ids',
                'meta_key'       => 'ics_feed_id',
                'meta_value'     => $feedId,
                'post_type'      => CalendarEvent::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
            ]
        );

        return (int) $query->found_posts;
    }

    /**
     * Hash the local imported-event state used by destructive confirmations.
     *
     * @param int $feedId
     * @return string
     */
    public static function getEventStateHash(int $feedId): string
    {
        $state = [];
        $postIds = self::getEventIds($feedId);
        sort($postIds, SORT_NUMERIC);

        foreach ($postIds as $postId) {
            $state[] = [
                'id' => $postId,
                'uid' => (string) get_post_meta($postId, 'event-uid', true),
                'occurrences' => get_post_meta($postId, 'ics_event_ocurrences', true),
            ];
        }

        return hash('sha256', serialize($state));
    }

    /**
     * Acquire an atomic per-feed synchronization lock.
     *
     * @param int $feedId
     * @return bool
     */
    public static function acquireSyncLock(int $feedId): bool
    {
        $key = self::getSyncLockKey($feedId);
        $now = time();
        $token = wp_generate_uuid4();
        $lock = [
            'token' => $token,
            'created' => $now,
        ];

        if (add_option($key, $lock, '', false)) {
            self::$syncLocks[$feedId] = $token;
            return true;
        }

        $existingLock = get_option($key, []);
        $created = is_array($existingLock) ? (int) ($existingLock['created'] ?? 0) : 0;
        if ($created > 0 && ($now - $created) < 15 * MINUTE_IN_SECONDS) {
            return false;
        }

        delete_option($key);

        if (! add_option($key, $lock, '', false)) {
            return false;
        }

        self::$syncLocks[$feedId] = $token;

        return true;
    }

    /**
     * Release the per-feed synchronization lock.
     *
     * @param int $feedId
     * @return void
     */
    public static function releaseSyncLock(int $feedId): void
    {
        $token = self::$syncLocks[$feedId] ?? '';
        if ($token === '') {
            return;
        }

        $key = self::getSyncLockKey($feedId);
        $existingLock = get_option($key, []);
        if (
            is_array($existingLock)
            && ! empty($existingLock['token'])
            && hash_equals((string) $existingLock['token'], $token)
        ) {
            delete_option($key);
        }

        unset(self::$syncLocks[$feedId]);
    }

    /**
     * Build a synchronization lock option name.
     *
     * @param int $feedId
     * @return string
     */
    private static function getSyncLockKey(int $feedId): string
    {
        return 'rrze_calendar_sync_lock_' . $feedId;
    }

    /**
     * Determine whether cancellations would remove every imported event post.
     *
     * @param int $feedId
     * @param array $cancellations
     * @return bool
     */
    public static function cancellationsWouldDeleteAll(int $feedId, array $cancellations): bool
    {
        $posts = self::getImportedEventPosts($feedId);
        if (empty($posts)) {
            return false;
        }

        foreach ($posts as $post) {
            if (! self::cancellationDeletesPost($post, $cancellations)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply targeted ICS cancellations.
     *
     * @param int $feedId
     * @param array $cancellations
     * @return void
     */
    private static function applyCancellations(int $feedId, array $cancellations): void
    {
        $cancellations = array_values(
            array_filter(
                $cancellations,
                fn($cancellation) => ! empty($cancellation['uid'])
            )
        );
        $uids = array_values(
            array_unique(array_column($cancellations, 'uid'))
        );

        if (empty($uids)) {
            return;
        }

        $posts = self::getImportedEventPosts($feedId, $uids);

        foreach ($posts as $post) {
            $uid = (string) get_post_meta($post->ID, 'event-uid', true);

            foreach ($cancellations as $cancellation) {
                if ($cancellation['uid'] !== $uid) {
                    continue;
                }

                $recurrenceDate = (string) ($cancellation['recurrence_date'] ?? '');
                if ($recurrenceDate === '') {
                    wp_delete_post($post->ID, true);
                    break;
                }

                $eventMeta = get_post_meta($post->ID, 'ics_event_meta', true);
                $eventMeta = is_array($eventMeta) ? $eventMeta : [];
                $isRecurring = ! empty($eventMeta['rrule']);
                if ($isRecurring) {
                    self::persistRecurringCancellation($post->ID, $eventMeta, $recurrenceDate);
                }

                $occurrences = get_post_meta($post->ID, 'ics_event_ocurrences', true);
                if (is_array($occurrences) && ! empty($occurrences)) {
                    $remaining = array_values(
                        array_filter(
                            $occurrences,
                            fn($date) => substr((string) $date, 0, 10) !== $recurrenceDate
                        )
                    );

                    if (count($remaining) === count($occurrences)) {
                        break;
                    }

                    if (empty($remaining) && ! $isRecurring) {
                        wp_delete_post($post->ID, true);
                    } else {
                        // Keep a truthy sentinel for recurring posts with no
                        // occurrences in the current import window. Otherwise
                        // the frontend falls back to the series DTSTART.
                        update_post_meta(
                            $post->ID,
                            'ics_event_ocurrences',
                            empty($remaining) ? [''] : $remaining
                        );
                    }
                    break;
                }

                $eventDate = is_array($eventMeta)
                    ? substr((string) ($eventMeta['dt_start'] ?? ''), 0, 10)
                    : '';

                if (! $isRecurring && $eventDate === $recurrenceDate) {
                    wp_delete_post($post->ID, true);
                }
                break;
            }
        }
    }

    /**
     * Query imported event posts, optionally limited to ICS UIDs.
     *
     * @param int $feedId
     * @param array $uids
     * @return array
     */
    private static function getImportedEventPosts(int $feedId, array $uids = []): array
    {
        $metaQuery = [
            [
                'key' => 'ics_feed_id',
                'value' => $feedId,
            ],
        ];

        if (! empty($uids)) {
            $metaQuery = [
                'relation' => 'AND',
                $metaQuery[0],
                [
                    'key' => 'event-uid',
                    'value' => array_values(array_unique(array_map('strval', $uids))),
                    'compare' => 'IN',
                ],
            ];
        }

        return get_posts(
            [
                'meta_query'     => $metaQuery,
                'post_type'      => CalendarEvent::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]
        );
    }

    /**
     * Determine whether the supplied cancellations delete a specific post.
     *
     * @param object $post
     * @param array $cancellations
     * @return bool
     */
    private static function cancellationDeletesPost(object $post, array $cancellations): bool
    {
        $uid = (string) get_post_meta($post->ID, 'event-uid', true);
        $eventMeta = get_post_meta($post->ID, 'ics_event_meta', true);
        $eventMeta = is_array($eventMeta) ? $eventMeta : [];
        $isRecurring = ! empty($eventMeta['rrule']);

        foreach ($cancellations as $cancellation) {
            if (($cancellation['uid'] ?? '') !== $uid) {
                continue;
            }

            $recurrenceDate = (string) ($cancellation['recurrence_date'] ?? '');
            if ($recurrenceDate === '') {
                return true;
            }

            if ($isRecurring) {
                return false;
            }

            $eventDate = substr((string) ($eventMeta['dt_start'] ?? ''), 0, 10);
            if ($eventDate === $recurrenceDate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Persist a recurring-instance cancellation for later ICS export.
     *
     * @param int $postId
     * @param array $eventMeta
     * @param string $recurrenceDate
     * @return void
     */
    private static function persistRecurringCancellation(
        int $postId,
        array $eventMeta,
        string $recurrenceDate
    ): void {
        $cancelledOccurrences = (array) ($eventMeta['cancelled_occurrences'] ?? []);
        $cancelledOccurrences[] = $recurrenceDate;
        $eventMeta['cancelled_occurrences'] = array_values(
            array_unique(array_filter(array_map('strval', $cancelledOccurrences)))
        );
        update_post_meta($postId, 'ics_event_meta', $eventMeta);
    }

    public static function deleteUnlinkedEvents()
    {
        $metaKey = 'ics_feed_id';

        $query = new \WP_Query([
            'meta_key'       => $metaKey,
            'post_type'      => CalendarEvent::POST_TYPE,
            'posts_per_page' => 100,
        ]);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $postId = get_the_ID();

                $feedId = get_post_meta($postId, $metaKey, true);
                if (! get_post($feedId)) {
                    wp_delete_post($postId, true);
                }
            }
            wp_reset_postdata();
        }
    }
}
