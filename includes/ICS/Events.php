<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\CPT\{CalendarEvent, CalendarFeed};

class Events
{
    private static $pastDays = 365;

    private static $limitDays = 365;

    public static function updateFeedsItems()
    {
        self::deleteUnlinkedEvents();

        $feeds = self::getFeeds();
        foreach ($feeds as $post) {
            if ($post->post_status == 'publish') {
                $pastDays = get_post_meta($post->ID, CalendarFeed::FEED_PAST_DAYS, true) ?: self::$pastDays;
                $pastDays = absint($pastDays);
                self::updateItems($post->ID, true, $pastDays);
                self::insertData($post->ID);
            } else {
                self::deleteEvent($post->ID);
            }
        }
    }

    private static function getFeeds(array $postIn = [])
    {
        $args = [
            'numberposts' => -1,
            'post_type'   => CalendarFeed::POST_TYPE,
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private', 'trash']
        ];
        if (!empty($postIn)) {
            $args = array_merge($args, ['post__in' => $postIn]);
        }
        return get_posts($args);
    }

    public static function updateItems(int $postId, bool $cache = true, int $pastDays = 365, int $limitDays = 365)
    {
        $events = Import::getEvents($postId, $cache, $pastDays, $limitDays);

        $error = !$events ? __('No events found.', 'rrze-calendar') : '';
        $items = !empty($events['events']) ? $events['events'] : [];
        $meta = !empty($events['meta']) ? $events['meta'] : [];

        update_post_meta($postId, CalendarFeed::FEED_DATETIME, current_time('mysql', true));
        update_post_meta($postId, CalendarFeed::FEED_ERROR, $error);
        update_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, $items);
        update_post_meta($postId, CalendarFeed::FEED_EVENTS_META, $meta);
    }

    /**
     * Get feed items
     *
     * @param integer $postId
     * @param integer $pastDays
     * @param integer $limitDays
     * @return void
     */
    private static function getItems(int $postId, int $pastDays, int $limitDays)
    {
        $feedItems = [];
        if (
            get_post_status($postId) !== 'publish'
            || !$items = get_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, true)
        ) {
            return $feedItems;
        }
        $feedItems['events'] = [];
        $feedItems['tz'] = get_option('timezone_string');

        // Set display date range.
        $pastDays = abs($pastDays);
        $limitDays = abs($limitDays);
        $startDate = date('Ymd', current_time('timestamp'));
        $firstDate = Utils::dateFormat('Ymd', $startDate, null, '-' . $pastDays + 30 . ' days');
        $limitDate = Utils::dateFormat('Ymd', $startDate, null, '+' . ($limitDays + 7) . ' days');

        // Set earliest and latest dates
        $feedItems['earliest'] = substr($firstDate, 0, 6);
        $feedItems['latest'] = substr($limitDate, 0, 6);

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

        foreach ((array)$feedItems['events'] as $date => $events) {
            // Only reorganize dates that are in the proper date range
            if ($date >= $firstDate && $date <= $limitDate) {
                // Get the date's events in order
                ksort($events);

                // Fix recurrence exceptions
                $events = self::fixRecurrenceExceptions($events);

                // Insert the date's events into the year/month/day hierarchical array
                $year = substr($date, 0, 4);
                $month = substr($date, 4, 2);
                $day = substr($date, 6, 2);
                $feedItems['events'][$year][$month][$day] = $events;
            }

            // Remove the old flat date item from the array
            unset($feedItems['events'][$date]);
        }

        // Add empty event arrays
        for ($i = substr($firstDate, 0, 6); $i <= substr($limitDate, 0, 6); $i++) {
            $Y = substr($i, 0, 4);
            $m = substr($i, 4, 2);
            if (intval($m) < 1 || intval($m) > 12) {
                continue;
            }
            if (!isset($feedItems['events'][$Y][$m])) {
                $feedItems['events'][$Y][$m] = null;
            }
        }

        // Sort events
        foreach (array_keys((array)$feedItems['events']) as $keyYear) {
            ksort($feedItems['events'][$keyYear]);
        }
        ksort($feedItems['events']);

        return $feedItems;
    }

    private static function assembleEvents($postId, $event, $eventKey, $urlTz, &$feedItems)
    {
        // Set start and end dates for event
        $dtstartDate = wp_date('Ymd', $event->dtstart_array[2], $urlTz);
        // Conditional is for events that are missing DTEND altogether
        $dtendDate = wp_date('Ymd', (!isset($event->dtend_array[2]) ? $event->dtstart_array[2] : $event->dtend_array[2]), $urlTz);

        // All-day events
        if (strlen($event->dtstart) == 8 || (strpos($event->dtstart, 'T000000') !== false && strpos($event->dtend, 'T000000') !== false)) {
            $dtstartTime = null;
            $dtendTime = null;
            $allDay = true;
        }
        // Start/end times
        else {
            $dtstartTime = wp_date('His', $event->dtstart_array[2], $urlTz);
            // Conditional is for events that are missing DTEND altogether
            $dtendTime = wp_date('His', (!isset($event->dtend_array[2]) ? $event->dtstart_array[2] : $event->dtend_array[2]), $urlTz);
            $allDay = false;
        }

        // Workaround for events in feeds that do not contain an end date/time
        if (empty($dtendDate)) {
            $dtendDate = isset($dtstartDate) ? $dtstartDate : null;
        }
        if (empty($dtendTime)) {
            $dtendTime = isset($dtstartTime) ? $dtstartTime : null;
        }

        // Summary (Title)
        $summary = empty($event->summary) ?: $event->summary;

        // Get the terms from the category
        $categories = [];
        $terms = wp_get_post_terms(
            $postId,
            CalendarEvent::TAX_CATEGORY,
            [
                'fields' => 'ids',
                'parent' => 0
            ]
        );
        if (!empty($terms) && !is_wp_error($terms)) {
            $categories = [$terms[0]];
        }

        // Get the terms from the tag
        $tags = [];
        $terms = wp_get_post_terms(
            $postId,
            CalendarEvent::TAX_TAG
        );
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $tags[] = $term->id;
            }
        }

        // General event item details (regardless of all-day/start/end times)
        $eventItem = [
            'post_id' => $postId,
            'feed_url' => get_post_meta($postId, CalendarFeed::FEED_URL),
            'timezone' => $urlTz->getName(),
            'summary' => Utils::translateOutlookSummaryToGerman($summary),
            'categories' => $categories,
            'tags' => $tags,
            'uid' => $event->uid,
            'dtstart_date' => !empty($dtstartDate) ? $dtstartDate : '',
            'dtstart_time' => !empty($dtstartTime) ? $dtstartTime : '',
            'dtend_date' => !empty($dtendDate) ? $dtendDate : '',
            'dtend_time' => !empty($dtendTime) ? $dtendTime : '',
            'description' => $event->description,
            'location' => $event->location,
            'organizer' => $event->organizer ? $event->organizer_array : '',
            'url' => !is_null($event->url) ? $event->url : '',
            'rrule' => !is_null($event->rrule) ? $event->rrule : '',
            'readable_rrule' => !is_null($event->rrule) ? Utils::humanReadableRecurrence($event->rrule) : '',
            'exdate_array' => !empty($event->exdate_array) ? $event->exdate_array : [],
            'rdate_array' => !empty($event->rdate_array) ? $event->rdate_array : [],
        ];

        // Events with different start and end dates
        if (
            $dtendDate != $dtstartDate &&
            // Events that are NOT multiday, but end at midnight of the start date!
            !($dtendDate == Utils::dateFormat('Ymd', $dtstartDate, $urlTz, '+1 day') && $dtendTime == '000000')
        ) {
            $loopDate = $dtstartDate;
            while ($loopDate <= $dtendDate) {
                // Classified as an all-day event and we've hit the end date
                if ($allDay && $loopDate == $dtendDate) {
                    break;
                }
                // Multi-day events may be given with end date/time as midnight of the NEXT day
                $actualEndDate = (!empty($allDay) && empty($dtendTime))
                    ? Utils::dateFormat('Ymd', $dtendDate, $urlTz, '-1 day')
                    : $dtendDate;
                if ($dtstartDate == $actualEndDate) {
                    $feedItems['events'][$dtstartDate]['all-day'][] = $eventItem;
                    break;
                }
                // Get full date/time range of multi-day event
                $eventItem['multiday'] = [
                    'event_key' => $eventKey,
                    'date_start' => $dtstartDate,
                    'start_time' => $dtstartTime,
                    'date_end' => $actualEndDate,
                    'end_time' => $dtendTime,
                    'all_day' => $allDay,
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
                    $eventItem['start'] = Utils::timeFormat($dtstartTime);
                    $eventItem['end'] = null;
                    $eventItem['multiday']['position'] = 'first';
                    $feedItems['events'][$loopDate]['t' . $dtstartTime][] = $eventItem;
                }
                // Last date in range: show end time
                elseif ($loopDate == $actualEndDate) {
                    // If event ends at midnight, skip
                    if (!empty($dtendTime) && $dtendTime != '000000') {
                        $eventItem['sublabel'] = __('Ends', 'rrze-newsletter') . ' ' . Utils::timeFormat($dtendTime);
                        $eventItem['start'] = null;
                        $eventItem['end'] = Utils::timeFormat($dtendTime);
                        $eventItem['multiday']['position'] = 'last';
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
            $eventItem['end'] = Utils::timeFormat($dtendTime);
            $feedItems['events'][$dtstartDate]['t' . $dtstartTime][] = $eventItem;
        }
    }

    public static function getListTableData(string $searchTerm = '')
    {
        $items = [];
        $postId = 0;
        $screen = get_current_screen();
        $screenId = $screen ? $screen->id : '';

        if ($screenId == CalendarFeed::POST_TYPE) {
            global $post;
            $postType = get_post_type($post);
            if ($postType === CalendarFeed::POST_TYPE) {
                $pastDays = get_post_meta($post->ID, CalendarFeed::FEED_PAST_DAYS, true) ?: self::$pastDays;
                $pastDays = absint($pastDays) + 30;
                $limitDays = self::$limitDays + 7;
                $postId = $post->ID;
                $items = self::getItems($postId, $pastDays, $limitDays);
            }
        }

        return count($items) ? self::getListData($postId, $items, $searchTerm) : $items;
    }

    /**
     * Get the list of events to be displayed when the Feed is edited.
     *
     * @param integer $postId Post ID
     * @param array $items Feed items splitet into year/month/day groups
     * @param string $searchTerm Search term in event titles
     * @return array
     */
    public static function getListData(int $postId, array $items, string $searchTerm = ''): array
    {
        $data = [];
        $dateFormat = __('m-d-Y', 'rrze-calendar');

        $i = 0;
        $multidayEventKeysUsed = [];

        if (empty($items) || empty($items['events'])) {
            return $data;
        }

        foreach (array_keys((array)$items['events']) as $year) {
            for ($m = 1; $m <= 12; $m++) {
                $month = $m < 10 ? '0' . $m : '' . $m;
                $ym = $year . $month;
                if ($ym < $items['earliest']) {
                    continue;
                }
                if ($ym > $items['latest']) {
                    break 2;
                }

                if (isset($items['events'][$year][$month])) {

                    foreach ((array)$items['events'][$year][$month] as $day => $dayEvents) {
                        // Pull out multi-day events and list them separately first
                        foreach ((array)$dayEvents as $time => $events) {

                            foreach ((array)$events as $eventKey => $event) {
                                if (empty($event['multiday'])) {
                                    continue;
                                }

                                if (in_array($event['multiday']['event_key'], $multidayEventKeysUsed)) {
                                    continue;
                                }

                                // Event meta
                                $data[$i]['post_id'] = $event['post_id'];
                                $data[$i]['feed_url'] = $event['feed_url'];
                                $data[$i]['timezone'] = $event['timezone'];
                                $data[$i]['uid'] = $event['uid'];

                                // Event summary (title)
                                $summary = $event['summary'];
                                if ($searchTerm && stripos($summary, $searchTerm) === false) {
                                    continue;
                                }
                                $data[$i]['summary'] = $summary;

                                // Multiday
                                $data[$i]['multiday'] = true;

                                // Format date/time
                                $mdDtStart = Utils::dateFormat($dateFormat, strtotime($event['multiday']['date_start']));
                                $mdDtEnd = Utils::dateFormat($dateFormat, strtotime($event['multiday']['date_end']));
                                $dtStart = Utils::dateFormat('Y-m-d', strtotime($event['multiday']['date_start']));
                                $dtEnd = Utils::dateFormat('Y-m-d', strtotime($event['multiday']['date_end']));
                                if ($time != 'all-day') {
                                    $mdDtStart .= ', ' . Utils::timeFormat($event['multiday']['start_time']);
                                    $mdDtEnd .= ', ' . Utils::timeFormat($event['multiday']['end_time']);
                                    $dtStart .= ', ' . Utils::timeFormat($event['multiday']['start_time'], 'H:i:s');
                                    $dtEnd .= ', ' . Utils::timeFormat($event['multiday']['end_time'], 'H:i:s');
                                } else {
                                    $data[$i]['allday'] = true;
                                }

                                // Date/time
                                $data[$i]['dt_start'] = $dtStart;
                                $data[$i]['dt_end'] = $dtEnd;
                                $data[$i]['readable_date'] = $mdDtStart . ' &mdash; ' . $mdDtEnd;

                                // RRULE/FREQ
                                if ($event['rrule']) {
                                    $data[$i]['rrule'] = $event['rrule'];
                                    $data[$i]['readable_rrule'] = $event['readable_rrule'];
                                }

                                // EXDATE
                                if (!empty($event['exdate_array'])) {
                                    $data[$i]['exdate_array'] = $event['exdate_array'];
                                }

                                // RDATE
                                if (!empty($event['rdate_array'])) {
                                    $data[$i]['rdate_array'] = $event['rdate_array'];
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
                        foreach ((array)$dayEvents as $time => $events) {

                            foreach ((array)$events as $event) {

                                if (!empty($event['multiday'])) {
                                    continue;
                                }

                                // If it is not an all day event and limit date >= event end date then skip
                                if (
                                    $time !== 'all-day'
                                    && !empty($event['end'])
                                    && self::$limitDays >= $year . $month . $day
                                ) {
                                    continue;
                                }

                                // Event meta
                                $data[$i]['post_id'] = $event['post_id'];
                                $data[$i]['feed_url'] = $event['feed_url'];
                                $data[$i]['timezone'] = $event['timezone'];
                                $data[$i]['uid'] = $event['uid'];

                                // Event summary (title)
                                $summary = $event['summary'];
                                if ($searchTerm && stripos($summary, $searchTerm) === false) {
                                    continue;
                                }
                                $data[$i]['summary'] = $summary;

                                // Date/time
                                $mdate = Utils::dateFormat($dateFormat, $day . '-' .  $month . '-' . $year);
                                $dtStart = Utils::dateFormat('Y-m-d', $day . '-' .  $month . '-' . $year);
                                $dtEnd = $dtStart;
                                $mtime = '';
                                if ($time !== 'all-day') {
                                    if (!empty($event['start'])) {
                                        $mtime = ' ' . $event['start'];
                                        $dtStart = $dtStart . ' ' . $event['start'];
                                        if (!empty($event['end']) && $event['end'] != $event['start']) {
                                            $mtime .= ' &mdash; ' . $event['end'];
                                            $dtEnd = $dtEnd . ' ' . $event['end'];
                                        } else {
                                            $dtEnd = $dtStart;
                                        }
                                    }
                                } else {
                                    $data[$i]['allday'] = true;
                                }
                                $data[$i]['dt_start'] = $dtStart;
                                $data[$i]['dt_end'] = $dtEnd;
                                $data[$i]['readable_date'] = $mdate . $mtime;

                                // RRULE/FREQ
                                if ($event['rrule']) {
                                    $data[$i]['rrule'] = $event['rrule'];
                                    $data[$i]['readable_rrule'] = $event['readable_rrule'];
                                }

                                // EXDATE
                                if (!empty($event['exdate_array'])) {
                                    $data[$i]['exdate_array'] = $event['exdate_array'];
                                }

                                // RDATE
                                if (!empty($event['rdate_array'])) {
                                    $data[$i]['rdate_array'] = $event['rdate_array'];
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

        $rruleEventUidUsed = [];
        // RRULE ocurrences
        $ocurrences = [];
        foreach ($data as $key => $event) {
            if (in_array($event['uid'], $rruleEventUidUsed) && isset($event['rrule'])) {
                $ocurrences[$event['uid']][] = date('Y-m-d', strtotime($event['dt_start']));
                unset($data[$key]);
                continue;
            }
            if (isset($event['rrule'])) {
                $rruleEventUidUsed[] = $event['uid'];
                $ocurrences[$event['uid']][] = date('Y-m-d', strtotime($event['dt_start']));
            }
        }
        foreach ($data as $key => $event) {
            if (isset($ocurrences[$event['uid']]) && isset($event['rrule'])) {
                $data[$key]['ocurrences'] = $ocurrences[$event['uid']];
            }
        }
        $meta = get_post_meta($postId, CalendarFeed::FEED_EVENTS_META, true);
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
            if (!is_array($timeEvents)) {
                continue;
            }
            foreach ($timeEvents as $teEvent) {
                if (!empty($teEvent['recurrence_id'])) {
                    $recurrenceExceptions[$teEvent['uid']] = $time;
                }
            }
        }
        if (!empty($recurrenceExceptions)) {
            foreach ($recurrenceExceptions as $reUid => $reTime) {
                foreach ($events as $time => $timeEvents) {
                    if (!is_array($timeEvents)) {
                        continue;
                    }
                    foreach ($timeEvents as $te_key => $teEvent) {
                        if (empty($teEvent['recurrence_id']) && $teEvent['uid'] == $reUid) {
                            unset($events[$time][$te_key]);
                            break (2);
                        }
                    }
                }
            }
        }
        return $events;
    }

    /**
     * Insert event data in the calendar event post type.
     *
     * @param integer $postId
     * @return void
     */
    public static function insertData(int $postId)
    {
        $items = [];
        $post = get_post($postId);
        if (get_post_type($post) === CalendarFeed::POST_TYPE) {
            $pastDays = get_post_meta($post->ID, CalendarFeed::FEED_PAST_DAYS, true) ?: self::$pastDays;
            $pastDays = absint($pastDays) + 30;
            $limitDays = self::$limitDays + 7;
            $items = self::getItems($postId, $pastDays, $limitDays);
        }

        $items = count($items) ? self::getListData($postId, $items) : $items;

        if (count($items)) {
            self::deleteEvent($postId);
        } else {
            return;
        }

        foreach ($items as $event) {
            $args = [
                'post_author' => $post->post_author,
                'post_title' => $event['summary'],
                'post_content' => '',
                'post_excerpt' => $event['description'] ?? '',
                'post_type' => CalendarEvent::POST_TYPE,
                'post_status' => 'publish'
            ];

            $eventId = wp_insert_post($args, false, false);
            if (!$eventId) {
                continue;
            }

            update_post_meta($eventId, 'event-uid', $event['uid']);

            $terms = get_the_terms($postId, CalendarEvent::TAX_CATEGORY);
            if ($terms && !is_wp_error($terms)) {
                $termIds = wp_list_pluck($terms, 'term_id');
                wp_set_post_terms($eventId, $termIds, CalendarEvent::TAX_CATEGORY);
            }
            $terms = get_the_terms($postId, CalendarEvent::TAX_TAG);
            if ($terms && !is_wp_error($terms)) {
                $termIds = wp_list_pluck($terms, 'term_id');
                wp_set_post_terms($eventId, $termIds, CalendarEvent::TAX_TAG);
            }

            $dtStart = strtotime($event['dt_start']);
            $dtEnd = strtotime($event['dt_end']);
            // $post_date = date('Y-m-d H:i:s', $dtStart);
            // $post_date_gmt = get_gmt_from_date($post_date);

            add_post_meta($eventId, 'start', $dtStart, true);
            add_post_meta($eventId, 'end', $dtEnd, true);

            $allDay = $event['allday'] ?? false;
            if ($allDay) {
                add_post_meta($eventId, 'all-day', 'on', true);
            }

            add_post_meta($eventId, 'description', $event['description'], true);
            add_post_meta($eventId, 'location', $event['location'], true);

            add_post_meta($eventId, 'ics_feed_id', $postId, true);
            add_post_meta($eventId, 'ics_event_meta', $event, true);

            $ocurrences = $event['ocurrences'] ?? false;
            if ($ocurrences) {
                add_post_meta($eventId, 'ics_event_ocurrences', $ocurrences, true);
            }
        }
    }

    public static function deleteEvent(int $feedId)
    {
        $metaKey = 'ics_feed_id';
        $metaValue = $feedId;

        $query = new \WP_Query(
            [
                'meta_key' => $metaKey,
                'meta_value' => $metaValue,
                'post_type' => CalendarEvent::POST_TYPE,
                'posts_per_page' => -1
            ]
        );

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                wp_delete_post(get_the_ID(), true);
            }
            wp_reset_postdata();
        }
    }

    public static function deleteUnlinkedEvents()
    {
        $metaKey = 'ics_feed_id';
        $query = new \WP_Query([
            'meta_key' => $metaKey,
            'post_type' => CalendarEvent::POST_TYPE,
            'posts_per_page' => 100
        ]);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $postId = get_the_ID();

                $feedId = get_post_meta($postId, $metaKey, true);
                if (!get_post($feedId)) {
                    wp_delete_post($postId, true);
                }
            }
            wp_reset_postdata();
        }
    }
}
