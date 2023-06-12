<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

get_header();

echo '<div class="rrze-calendar">';

echo '<h2>', __('Event', 'rrze-calendar'), '</h2>';

if (class_exists(__NAMESPACE__ . '\Endpoint') && !empty($data)) {
    singleEventOutput($data);
} else {
    echo '<p>', __('No event found.', 'rrze-calendar'), '</p>';
}

echo '</div>';

get_footer();

function singleEventOutput(&$data)
{
    // Date & time format
    $dateFormat = get_option('date_format');

    // Feed URL
    $feedUrl = $data['feed_url'] ?? '';
    // Calendar URL
    $calendarUrl = '';
    if (parse_url($feedUrl, PHP_URL_HOST) == 'groupware.fau.de') {
        $calendarUrl = str_replace('.ics', '.html', $feedUrl);
    }
    $uid = Utils::createId();
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
            $displayLabel = false;

            // Build month's calendar
            if (isset($data['events'][$year][$month])) {
                echo '<section class="calendar-section" data-year-month="', esc_attr($ym), '">';
                foreach ((array)$data['events'][$year][$month] as $day => $dayEvents) {
                    // Pull out multi-day events and display them separately first
                    foreach ((array)$dayEvents as $time => $events) {

                        foreach ((array)$events as $eventKey => $event) {

                            // Only list multi-day events
                            if (empty($event['multiday'])) {
                                continue;
                            }

                            // Has this multi-day event already been listed?
                            if (!in_array($event['multiday']['event_key'], $multidayEventKeysUsed)) {
                                // Format date/time for header
                                $mdStart = date($dateFormat, strtotime($event['multiday']['start_date']));
                                $mdEnd = date($dateFormat, strtotime($event['multiday']['end_date']));
                                if ($time != 'all-day') {
                                    $mdStart .= ' <small class="time-inline">' . Utils::timeFormat($event['multiday']['start_time']) . '</small>';
                                    $mdEnd .= ' <small class="time-inline">' . Utils::timeFormat($event['multiday']['end_time']) . '</small>';
                                }

                                // Label (title)
                                if (!$displayLabel) {
                                    echo '<h3 class="calendar-label" id="', esc_attr($event['slug']), '">', $event['label'], '</h3>';
                                    $displayLabel = true;
                                }

                                $dayLabel = $mdStart . ' &#8211; ' . $mdEnd;
                                $dayGuid = $uid . '-' . Utils::createId();

                                echo '<article class="calendar-article" data-date="', esc_attr($dayLabel), '">';
                                echo '<h4 class="calendar-date" id="', esc_attr($dayGuid), '">', $dayLabel, '</h4>';
                                echo '<div class="multiday-event">';

                                if (!empty($event['readable_rrule'])) {
                                    echo '<div class="event-desc">', $event['readable_rrule'], '</div>';
                                }

                                // Location/Organizer/Description
                                if (!empty($event['location'])) {
                                    printf('<div class="event-location">%1$s %2$s</div>', __("Location:", 'rrze-calendar'), make_clickable($event['location']));
                                }
                                $eventDesc = $event['eventdesc'] ?? __("No description", 'rrze-calendar');
                                $eventDesc = make_clickable(nl2br(htmlspecialchars_decode($eventDesc)));
                                $eventDesc = apply_filters('the_content', $eventDesc);
                                $eventDesc = str_replace(']]>', ']]&gt;', $eventDesc);
                                echo '<div class="event-desc">', $eventDesc, '</div>';

                                if (!empty($calendarUrl)) {
                                    printf('<div class="calendar-url"><a href="%1$s" target="_blank">%2$s <span class="dashicons dashicons-external"></span></a></div>', $calendarUrl, __('View the calendar', 'rrze-calendar'));
                                }
                                if (!empty($feedUrl)) {
                                    printf('<div class="feed-url"><a href="%1$s">%2$s</a></div>', $feedUrl, __('Subscribe to calendar', 'rrze-calendar'));
                                }
                                echo '</div>';
                                echo '</article>';

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
                    $displayAllDayLabel = false;
                    $displayDayLabel = false;
                    foreach ((array)$dayEvents as $time => $events) {
                        foreach ((array)$events as $event) {

                            // Don't list multi-day events (these should all be removed above already)
                            if (!empty($event['multiday'])) {
                                continue;
                            }

                            // Label (title)
                            if (!$displayLabel) {
                                echo '<h3 class="calendar-label" id="', esc_attr($event['slug']), '">', $event['label'], '</h3>';
                                $displayLabel = true;
                            }

                            // Show day label if not yet displayed
                            if (!$displayDayLabel) {
                                $dayLabel = Utils::dateFormat($dateFormat, $month . '/' . $day . '/' . $year);
                                $dayGuid = $uid . '-' . $year . $month . $day;
                                echo '<article class="calendar-article" data-date="', esc_attr($dayLabel), '">';
                                echo '<h4 class="calendar-date" id="', esc_attr($dayGuid), '">', $dayLabel, '</h4>';
                                $displayDayLabel = true;
                            }

                            if ($time == 'all-day') {
                                echo '<div class="all-day-event">';
                                if (!$displayAllDayLabel) {
                                    echo '<div class="all-day-label">', __('All Day', 'rrze-calendar');
                                    if (!empty($event['readable_rrule'])) {
                                        echo '<br>', $event['readable_rrule'];
                                    }
                                    echo '</div>';
                                    $displayAllDayLabel = true;
                                }

                                // Location/Organizer/Description
                                if (!empty($event['location'])) {
                                    printf('<div class="event-location">%1$s %2$s</div>', __("Location:", 'rrze-calendar'), make_clickable($event['location']));
                                }
                                $eventDesc = $event['eventdesc'] ?? __("No description", 'rrze-calendar');
                                $eventDesc = make_clickable(nl2br(htmlspecialchars_decode($eventDesc)));
                                $eventDesc = apply_filters('the_content', $eventDesc);
                                $eventDesc = str_replace(']]>', ']]&gt;', $eventDesc);
                                echo '<div class="event-desc">', $eventDesc, '</div>';

                                if (!empty($calendarUrl)) {
                                    printf('<div class="calendar-url"><a href="%1$s" target="_blank">%2$s <span class="dashicons dashicons-external"></span></a></div>', $calendarUrl, __('View the calendar', 'rrze-calendar'));
                                }
                                if (!empty($feedUrl)) {
                                    printf('<div class="feed-url"><a href="%1$s">%2$s</a></div>', $feedUrl, __('Subscribe to calendar', 'rrze-calendar'));
                                }
                                echo '</div>';
                            } else {
                                echo '<div class="day-event">';

                                if (!empty($event['start'])) {
                                    echo '<div class="time">';
                                    echo $event['start'];
                                    if (!empty($event['end']) && $event['end'] != $event['start']) {
                                        echo ' &mdash; ', $event['end'];
                                    }
                                    if (!empty($event['readable_rrule'])) {
                                        echo '<br>', $event['readable_rrule'];
                                    }
                                    echo '</div>';
                                }

                                // Location/Organizer/Description
                                if (!empty($event['location'])) {
                                    printf('<div class="event-location">%1$s %2$s</div>', __("Location:", 'rrze-calendar'), make_clickable($event['location']));
                                }
                                $eventDesc = $event['eventdesc'] ?? __("No description", 'rrze-calendar');
                                $eventDesc = make_clickable(nl2br(htmlspecialchars_decode($eventDesc)));
                                $eventDesc = apply_filters('the_content', $eventDesc);
                                $eventDesc = str_replace(']]>', ']]&gt;', $eventDesc);
                                echo '<div class="event-desc">', $eventDesc, '</div>';

                                if (!empty($calendarUrl)) {
                                    printf('<div class="calendar-url"><a href="%1$s" target="_blank">%2$s <span class="dashicons dashicons-external"></span></a></div>', $calendarUrl, __('View the calendar', 'rrze-calendar'));
                                }
                                if (!empty($feedUrl)) {
                                    printf('<div class="feed-url"><a href="%1$s">%2$s</a></div>', $feedUrl, __('Subscribe to calendar', 'rrze-calendar'));
                                }
                                echo '</div>';
                            }
                        }
                    }
                    if ($displayDayLabel) {
                        echo '</article>';
                    }
                }
                echo '</section>';
            }
        }
    }
}
