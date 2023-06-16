<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\CPT\{CalendarEvent, CalendarFeed};
use RRule\RRule;

use function RRZE\Calendar\plugin;

class Events
{
    public static function getEventBySlug(string $slug, $rawOutput = false)
    {
        $slugAry = explode('-', $slug);
        $key = array_pop($slugAry);
        $postId = !is_null($slugAry) ? array_pop($slugAry) : 0;
        if (
            get_post_status($postId) !== 'publish'
            || !$items = get_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, true)
        ) {
            return [];
        }
        $item = $items[$key] ?? null;
        if (!is_null($item)) {
            return !$rawOutput ? self::getItem($postId, $key) : $item;
        } else {
            return [];
        }
    }

    protected static function getEventsByFeed(int $postId, $rawOutput = false)
    {
        if (
            get_post_status($postId) === 'publish'
            && $items = get_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, true)
        ) {
            return !$rawOutput ? self::getItems($postId) : $items;
        } else {
            return [];
        }
    }

    public static function getListTableData(string $searchTerm = ''): array
    {
        $items = [];
        if ($screen = get_current_screen()) {
            if ($screen->id == CalendarFeed::POST_TYPE) {
                global $post;
                if (get_post_type($post) === CalendarFeed::POST_TYPE) {
                    $items = self::getItems($post->ID);
                }
            } elseif ($screen->id == 'calendar_feed_page_all_events') {
                $items = self::getAllItems();
            }
        }
        return !empty($items) ? self::getListData($items, $searchTerm) : $items;
    }

    public static function updateFeedsItems()
    {
        $feeds = self::getFeeds();
        foreach ($feeds as $post) {
            self::updateItems($post->ID);
        }
    }

    protected static function getFeeds(array $postIn = [])
    {
        $args = [
            'numberposts' => -1,
            'post_type'   => CalendarFeed::POST_TYPE,
            'post_status' => 'publish'
        ];
        if (!empty($postIn)) {
            $args = array_merge($args, ['post__in' => $postIn]);
        }
        return get_posts($args);
    }

    public static function updateItems(int $postId)
    {
        $url = (string) get_post_meta($postId, CalendarFeed::FEED_URL, true);

        $events = Import::getEvents($url);
        $error = !$events ? __('No events found.', 'rrze-calendar') : '';
        $items = !empty($events['events']) ? $events['events'] : [];
        $meta = !empty($events['meta']) ? $events['meta'] : [];

        update_post_meta($postId, CalendarFeed::FEED_DATETIME, current_time('mysql', true));
        update_post_meta($postId, CalendarFeed::FEED_ERROR, $error);
        update_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, $items);
        update_post_meta($postId, CalendarFeed::FEED_EVENTS_META, $meta);
    }


    public static function getItem(int $postId, int $key): array
    {
        return self::processItem($postId, $key);
    }

    public static function getItems(int $postId): array
    {
        return self::processItems([$postId]);
    }

    public static function getItemsFromFeedIds(array $feedsIds, bool $list = false, $pastDays = 0, $limitDays = 365): array
    {
        if ($list) {
            $items = self::processItems($feedsIds);
            return !empty($items) ? self::getListData($items) : $items;
        } else {
            return self::processItems($feedsIds, $pastDays, $limitDays);
        }
    }

    public static function getAllItems(): array
    {
        $feedsIds = [];
        $feeds = self::getFeeds();
        foreach ($feeds as $post) {
            $feedsIds[] = $post->ID;
        }
        return self::processItems($feedsIds);
    }

    /**
     * processItem
     *
     * @param int $postId
     * @param int $eventKey
     * @return array
     */
    protected static function processItem(int $postId, int $eventKey): array
    {
        $feedItems = [];

        $feedItems['events'] = [];
        $feedItems['feed_url'] = (string) get_post_meta($postId, CalendarFeed::FEED_URL, true);
        $feedItems['tz'] = get_option('timezone_string');

        $limitDays = 365;

        // Set display date range.
        $firstDate = Utils::dateFormat('Ymd');
        $limitDate = Utils::dateFormat('Ymd', $firstDate, null, '+' . intval($limitDays - 1) . ' days');

        // Set earliest and latest dates
        $feedItems['earliest'] = substr($firstDate, 0, 6);
        $feedItems['latest'] = substr($limitDate, 0, 6);

        // Get timezone
        $urlTz = wp_timezone();

        // Process events
        if (
            get_post_status($postId) !== 'publish'
            || !$items = get_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, true)
        ) {
            return $feedItems;
        }

        // Assemble events
        $event = (object) $items[$eventKey];
        self::assembler($postId, $event, $eventKey, $urlTz, $feedItems);

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
                $ym = substr($date, 0, 6);
                $feedItems['events'][$year][$month][$day] = $events;
            }

            // Remove the old flat date item from the array
            unset($feedItems['events'][$date]);
        }

        // Add empty event arrays
        for ($i = substr($feedItems['earliest'], 0, 6); $i <= substr($feedItems['latest'], 0, 6); $i++) {
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

    /**
     * processItems
     *
     * @param array $postIds
     * @return mixed
     */
    protected static function processItems(array $postIds, int $pastDays = 0, int $limitDays = 365)
    {
        $feedItems = [];

        $feedItems['events'] = [];
        $feedItems['tz'] = get_option('timezone_string');

        // Set display date range.
        $pastDays = $pastDays ? abs($pastDays) : 0;
        $startDate = date('Ymd', current_time('timestamp'));
        if ($pastDays) {
            $firstDate = Utils::dateFormat('Ymd', $startDate, null, '-' . abs($pastDays) . ' days');
        } else {
            $firstDate = $startDate;
        }
        $limitDate = Utils::dateFormat('Ymd', $firstDate, null, '+' . intval($limitDays - 1) . ' days');

        // Set earliest and latest dates
        $feedItems['earliest'] = substr($firstDate, 0, 6);
        $feedItems['latest'] = substr($limitDate, 0, 6);

        // Get timezone
        $urlTz = wp_timezone();

        // Process events
        foreach ($postIds as $postId) {
            if (
                get_post_status($postId) !== 'publish'
                || !$items = get_post_meta($postId, CalendarFeed::FEED_EVENTS_ITEMS, true)
            ) {
                continue;
            }

            // Assemble events
            foreach ($items as $eventKey => $event) {
                self::assembler($postId, $event, $eventKey, $urlTz, $feedItems);
            }
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
                $ym = substr($date, 0, 6);
                $feedItems['events'][$year][$month][$day] = $events;
            }

            // Remove the old flat date item from the array
            unset($feedItems['events'][$date]);
        }

        // Add empty event arrays
        for ($i = substr($feedItems['earliest'], 0, 6); $i <= substr($feedItems['latest'], 0, 6); $i++) {
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

    protected static function assembler($postId, $event, $eventKey, $urlTz, &$feedItems)
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

        // Label (Title)
        $label = empty($event->summary) ?: $event->summary;

        // Get the terms from the category
        $termId = '';
        $catName = '';
        $terms = wp_get_post_terms(
            $postId,
            CalendarEvent::TAX_CATEGORY,
            [
                'fields' => 'ids',
                'parent' => 0
            ]
        );
        if (!empty($terms) && !is_wp_error($terms)) {
            $termId = $terms[0];
            $term = get_term($termId, CalendarEvent::TAX_CATEGORY);
            $catName = $term->name;
        }
        $catBgColor = $termId ? Utils::sanitizeHexColor(get_term_meta($termId, 'color', true)) : '';
        $catColor = $catBgColor ? Utils::getContrastYIQ($catBgColor) : '';

        // Get the terms from the tag
        $tags = [];
        $terms = wp_get_post_terms(
            $postId,
            CalendarEvent::TAX_TAG
        );
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $tags[] = $term->slug;
            }
        }

        // General event item details (regardless of all-day/start/end times)
        $eventItem = [
            'post_id' => $postId,
            'slug' => Utils::setEventSlug($label, $postId, $eventKey),
            'label' => $label,
            'cat_name' => $catName,
            'cat_bgcolor' => $catBgColor,
            'cat_color' => $catColor,
            'tag_slugs' => $tags,
            'uid' => !empty($event->uid) ? $event->uid : '',
            'dtstart_date' => !empty($dtstartDate) ? $dtstartDate : '',
            'dtstart_time' => !empty($dtstartTime) ? $dtstartTime : '',
            'dtend_date' => !empty($dtendDate) ? $dtendDate : '',
            'dtend_time' => !empty($dtendTime) ? $dtendTime : '',
            'eventdesc' => !empty($event->description) ? $event->description : '',
            'location' => !empty($event->location) ? $event->location : '',
            'organizer' => (!empty($event->organizer_array) ? $event->organizer_array : @$event->organizer),
            'url' => (!empty($event->url) ? $event->url : null),
            'rrule' => (!empty($event->rrule) ? $event->rrule : null),
            'readable_rrule' => !empty($event->rrule) ? self::humanReadableRecurrence($event->rrule) : null,
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
                    'start_date' => $dtstartDate,
                    'start_time' => $dtstartTime,
                    'end_date' => $actualEndDate,
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

    /**
     * Get the list of events to be displayed when the Feed is edited.
     *
     * @param array $items Feed items splitet into year/month/day groups
     * @param string $searchTerm Search term in event titles
     * @param array $slugs Search for the exact slug or slugs in events
     * @return array
     */
    public static function getListData(array $items, string $searchTerm = '', array $slugs = []): array
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

                                if ($slugs && !in_array($event['slug'], $slugs)) {
                                    continue;
                                }

                                // Event meta
                                $data[$i]['post_id'] = $event['post_id'];
                                $data[$i]['slug'] = $event['slug'];
                                $data[$i]['uid'] = $event['uid'];

                                // Event label (title)
                                $title = $event['label'];
                                if ($searchTerm && stripos($title, $searchTerm) === false) {
                                    continue;
                                }
                                $data[$i]['title'] = $title;

                                // Multiday
                                $data[$i]['is_multiday'] = true;

                                // Format date/time
                                $mdStart = Utils::dateFormat($dateFormat, strtotime($event['multiday']['start_date']));
                                $mdEnd = Utils::dateFormat($dateFormat, strtotime($event['multiday']['end_date']));
                                $dStart = Utils::dateFormat('Y-m-d', strtotime($event['multiday']['start_date']));
                                $dEnd = Utils::dateFormat('Y-m-d', strtotime($event['multiday']['end_date']));
                                if ($time != 'all-day') {
                                    $mdStart .= ' ' . Utils::timeFormat($event['multiday']['start_time']);
                                    $mdEnd .= ' ' . Utils::timeFormat($event['multiday']['end_time']);
                                    $dStart .= ' ' . Utils::timeFormat($event['multiday']['start_time'], 'H:i:s');
                                    $dEnd .= ' ' . Utils::timeFormat($event['multiday']['end_time'], 'H:i:s');
                                } else {
                                    $data[$i]['is_allday'] = true;
                                }

                                // Date/time
                                $data[$i]['start_date'] = $dStart;
                                $data[$i]['end_date'] = $dEnd;
                                $data[$i]['date'] = $mdStart . ' &#8211; ' . $mdEnd;

                                // RRULE/FREQ
                                $data[$i]['rrule'] = '';
                                $data[$i]['readable_rrule'] = '';
                                if (!empty($event['rrule'])) {
                                    $data[$i]['rrule'] = $event['rrule'];
                                    $data[$i]['readable_rrule'] = $event['readable_rrule'];
                                }

                                // Location
                                $data[$i]['location'] = $event['location'];

                                // Organizer
                                $data[$i]['organizer'] = $event['organizer'];

                                // Description
                                $data[$i]['eventdesc'] = $event['eventdesc'];

                                // Get the terms from the category
                                $termId = '';
                                $catName = '';
                                $terms = wp_get_post_terms(
                                    $data[$i]['post_id'],
                                    CalendarEvent::TAX_CATEGORY,
                                    [
                                        'fields' => 'ids',
                                        'parent' => 0
                                    ]
                                );
                                if (!empty($terms) && !is_wp_error($terms)) {
                                    $termId = $terms[0];
                                    $term = get_term($termId, CalendarEvent::TAX_CATEGORY);
                                    $catName = $term->name;
                                }
                                $data[$i]['cat_name'] = $catName;
                                $catBgColor = $termId ? Utils::sanitizeHexColor(get_term_meta($termId, 'color', true)) : '';
                                $catColor = $catBgColor ? Utils::getContrastYIQ($catBgColor) : '';
                                $data[$i]['cat_bgcolor'] = $catBgColor;
                                $data[$i]['cat_color'] = $catColor;

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

                                // If it is not an all day event and current time > event end datetime then skip
                                if (
                                    $time !== 'all-day'
                                    && !empty($event['end'])
                                    && current_time('Y-m-d H:i:s') > sprintf('%1$s-%2$s-%3$s %4$s', $year, $month, $day, $event['end'])
                                ) {
                                    continue;
                                }

                                if ($slugs && !in_array($event['slug'], $slugs)) {
                                    continue;
                                }

                                // Event meta
                                $data[$i]['post_id'] = $event['post_id'];
                                $data[$i]['slug'] = $event['slug'];
                                $data[$i]['uid'] = $event['uid'];

                                // Event label (title)
                                $title = html_entity_decode(str_replace('/', '/<wbr />', $event['label']));
                                if ($searchTerm && stripos($title, $searchTerm) === false) {
                                    continue;
                                }
                                $data[$i]['title'] = $title;

                                // Date/time
                                $mdate = Utils::dateFormat($dateFormat, $day . '-' .  $month . '-' . $year);
                                $dStart = Utils::dateFormat('Y-m-d', $day . '-' .  $month . '-' . $year);
                                $dEnd = $dStart;
                                $mtime = '';
                                if ($time !== 'all-day') {
                                    if (!empty($event['start'])) {
                                        $mtime = ' ' . $event['start'];
                                        $dStart = get_gmt_from_date($dStart . ' ' . $event['start']);
                                        if (!empty($event['end']) && $event['end'] != $event['start']) {
                                            $mtime .= ' &#8211; ' . $event['end'];
                                            $dEnd = get_gmt_from_date($dEnd . ' ' . $event['end']);
                                        } else {
                                            $dEnd = $dStart;
                                        }
                                    }
                                } else {
                                    $data[$i]['is_allday'] = true;
                                }
                                $data[$i]['start_date'] = $dStart;
                                $data[$i]['end_date'] = $dEnd;
                                $data[$i]['date'] = $mdate . $mtime;

                                // RRULE/FREQ
                                $data[$i]['rrule'] = '';
                                $data[$i]['readable_rrule'] = '';
                                if (!empty($event['rrule'])) {
                                    $data[$i]['rrule'] = $event['rrule'];
                                    $data[$i]['readable_rrule'] = $event['readable_rrule'];
                                }

                                // Location
                                $data[$i]['location'] = $event['location'];

                                // Organizer
                                $data[$i]['organizer'] = $event['organizer'];

                                // Description
                                $data[$i]['eventdesc'] = $event['eventdesc'];

                                // Get the terms from the category
                                $termId = '';
                                $catName = '';
                                $terms = wp_get_post_terms(
                                    $data[$i]['post_id'],
                                    CalendarEvent::TAX_CATEGORY,
                                    [
                                        'fields' => 'ids',
                                        'parent' => 0
                                    ]
                                );
                                if (!empty($terms) && !is_wp_error($terms)) {
                                    $termId = $terms[0];
                                    $term = get_term($termId, CalendarEvent::TAX_CATEGORY);
                                    $catName = $term->name;
                                }
                                $data[$i]['cat_name'] = $catName;
                                $catBgColor = $termId ? Utils::sanitizeHexColor(get_term_meta($termId, 'color', true)) : '';
                                $catColor = $catBgColor ? Utils::getContrastYIQ($catBgColor) : '';
                                $data[$i]['cat_bgcolor'] = $catBgColor;
                                $data[$i]['cat_color'] = $catColor;

                                $i++;
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * getSingleData
     *
     * @param array $data
     * @return array
     */
    public static function getSingleData(array $data): array
    {
        $output = [];

        // Feed URL
        $feedUrl = $data['feed_url'];

        // Calendar URL
        $calendarUrl = '';
        if (parse_url($feedUrl, PHP_URL_HOST) == 'groupware.fau.de') {
            $feedUrl = str_replace('http://', 'https://', $feedUrl);
            $calendarUrl = str_replace('.ics', '.html', $feedUrl);
        }

        $multidayEventKeysUsed = [];

        foreach (array_keys((array)$data['events']) as $year) {
            for ($m = 1; $m <= 12; $m++) {
                $month = $m < 10 ? '0' . $m : '' . $m;
                $ym = $year . $month;
                if ($ym < $data['earliest']) {
                    continue;
                }
                if ($ym > $data['latest']) {
                    break (2);
                }

                // Build month's calendar
                if (isset($data['events'][$year][$month])) {

                    foreach ((array)$data['events'][$year][$month] as $day => $dayEvents) {
                        // Pull out multi-day events and display them separately first
                        foreach ((array)$dayEvents as $time => $events) {

                            foreach ((array)$events as $eventKey => $event) {
                                // Post Id
                                $output['post_id'] = $event['post_id'];

                                // UID
                                $output['uid'] = $event['uid'];

                                // Label (title)
                                $output['label'] = $event['label'];

                                // Category name
                                $output['cat_name'] = $event['cat_name'];
                                // Category background color
                                $output['cat_bgcolor'] = $event['cat_bgcolor'];
                                // Category text color
                                $output['cat_color'] = $event['cat_color'];

                                // Tags slugs
                                $output['tag_slugs'] = $event['tag_slugs'];

                                $output['is_multiday'] = false;
                                // Only list multi-day events
                                if (empty($event['multiday'])) {
                                    continue;
                                }
                                $output['is_multiday'] = true;

                                // Has this multi-day event already been listed?
                                if (!in_array($event['multiday']['event_key'], $multidayEventKeysUsed)) {
                                    // Format date/time for header
                                    $output['multiday']['start_date'] = date('Y-m-d', strtotime($event['multiday']['start_date']));
                                    $output['multiday']['end_date'] = date('Y-m-d', strtotime($event['multiday']['end_date']));
                                    if ($time != 'all-day') {
                                        $output['multiday']['start_time'] = Utils::timeFormat($event['multiday']['start_time'], 'H:i');
                                        $output['multiday']['end_time'] = Utils::timeFormat($event['multiday']['end_time'], 'H:i');
                                    } else {
                                        $output['is_allday'] = true;
                                    }

                                    // Readable Rule
                                    if (!empty($event['readable_rrule'])) {
                                        $output['readable_rrule'] = $event['readable_rrule'];
                                    }

                                    // Location/Organizer/Description
                                    if (!empty($event['location'])) {
                                        $output['location'] = $event['location'];
                                    }

                                    if (!empty($event['eventdesc'])) {
                                        $output['description'] = $event['eventdesc'];
                                    }

                                    // Calendar view url
                                    if (!empty($calendarUrl)) {
                                        $output['calendar_view_url'] = $calendarUrl;
                                    }

                                    // Calendar subscription url
                                    if (!empty($feedUrl)) {
                                        $output['calendar_subscription_url'] = $feedUrl;
                                    }

                                    // This multi-day event has already been listed
                                    $multidayEventKeysUsed[] = $event['multiday']['event_key'];
                                }

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
                                // Post Id
                                $output['post_id'] = $event['post_id'];

                                // UID
                                $output['uid'] = $event['uid'];

                                // Label (title)
                                $output['label'] = $event['label'];

                                // Readable Rule
                                if (!empty($event['readable_rrule'])) {
                                    $output['readable_rrule'] = $event['readable_rrule'];
                                }

                                // Location/Organizer/Description
                                if (!empty($event['location'])) {
                                    $output['location'] = $event['location'];
                                }

                                if (!empty($event['eventdesc'])) {
                                    $output['description'] = $event['eventdesc'];
                                }

                                // Calendar view url
                                if (!empty($calendarUrl)) {
                                    $output['calendar_view_url'] = $calendarUrl;
                                }

                                // Calendar subscription url
                                if (!empty($feedUrl)) {
                                    $output['calendar_subscription_url'] = $feedUrl;
                                }

                                // Don't list multi-day events (these should all be removed above already)
                                if (!empty($event['multiday'])) {
                                    continue;
                                }

                                // Date/time
                                $output['start_date'] = $year . '-' . $month . '-' . $day;
                                $output['is_allday'] = false;
                                if ($time == 'all-day') {
                                    $output['is_allday'] = true;
                                } else {
                                    $output['start_time'] = $event['start'];
                                    $output['end_time'] = $event['end'] ?: '';
                                    if (!empty($event['end']) && $event['end'] != $event['start']) {
                                        $output['end_time'] = $event['end'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $output;
    }

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
     * Generate CSS classes to apply to wrapper for an event.
     * 
     * @param [type] $event
     * @param [type] $time
     * @return void
     */
    public static function cssClasses($event, $time)
    {
        $classes = ['event', $time];
        if (!empty($event['multiday']['position'])) {
            $classes[] = 'multiday_' . $event['multiday']['position'];
        }
        return esc_attr(implode(' ', $classes));
    }

    /**
     * Fix RECURRENCE-ID issue (Outlook/Office 365).
     *
     * @param array $events
     * @return array
     */
    protected static function fixRecurrenceExceptions(array $events): array
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
}
