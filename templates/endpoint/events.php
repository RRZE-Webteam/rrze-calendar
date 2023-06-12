<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

get_header();

echo '<div class="rrze-calendar">';

echo '<h2>', __('All Events', 'rrze-calendar'), '</h2>';

if (class_exists(__NAMESPACE__ . '\Endpoint') && !empty($data['events'])) {
    eventsOutput($data);
} else {
    echo '<p>', __('No events found.', 'rrze-calendar'), '</p>';
}

echo '</div>';

get_footer();

function eventsOutput(&$data)
{
    $dateFormat = __('j F', 'rrze-calendar');
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
            $monthLabel = ucwords(Utils::dateFormat('F Y', $m . '/1/' . $year));
            $displayMonthLabel = false;
            $monthGuid = $uid . '-' . $ym;

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
                                $mdStart = Utils::dateFormat($dateFormat, strtotime($event['multiday']['start_date']));
                                $mdEnd = Utils::dateFormat($dateFormat, strtotime($event['multiday']['end_date']));
                                if ($time != 'all-day') {
                                    $mdStart .= ' <small class="time-inline">' . Utils::timeFormat($event['multiday']['start_time']) . '</small>';
                                    $mdEnd .= ' <small class="time-inline">' . Utils::timeFormat($event['multiday']['end_time']) . '</small>';
                                }

                                // Display month label if needed
                                if (!$displayMonthLabel) {
                                    echo '<h3 class="calendar-label" id="', esc_attr($monthGuid), '">', $monthLabel, '</h3>';
                                    $displayMonthLabel = true;
                                }

                                $dayLabel = $mdStart . ' &#8211; ' . $mdEnd;
                                $dayGuid = $uid . '-' . Utils::createId();

                                echo '<article class="calendar-article" data-date="', esc_attr($dayLabel), '">';
                                echo '<h4 class="calendar-date" id="', esc_attr($dayGuid), '">', $dayLabel, '</h4>';
                                echo '<dl class="calendar-events" aria-labelledby="', esc_attr($dayGuid), '">';
                                echo '<div class="multiday-event">';
                                // Display label (title)
                                printf('<dd class="event-label"><a href="%1$s">%2$s</a></dd>', Endpoint::endpointUrl($event['slug']), html_entity_decode(str_replace('/', '/<wbr />', $event['label'])));
                                // Display Description/Location/Organizer
                                //echo '<dd class="event-desc">', $event['eventdesc'], '</dd>';
                                echo '</div>';
                                echo '</dl>';
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

                            // Display month label if needed
                            if (!$displayMonthLabel) {
                                echo '<h3 class="calendar-label" id="', esc_attr($monthGuid), '">', $monthLabel, '</h3>';
                                $displayMonthLabel = true;
                            }

                            // Show day label if not yet displayed
                            if (!$displayDayLabel) {
                                $dayLabel = Utils::dateFormat($dateFormat, $month . '/' . $day . '/' . $year);
                                $dayGuid = $uid . '-' . $year . $month . $day;
                                echo '<article class="calendar-article" data-date="', esc_attr($dayLabel), '">';
                                echo '<h4 class="calendar-date" id="', esc_attr($dayGuid), '">', $dayLabel, '</h4>';
                                echo '<dl class="calendar-events" aria-labelledby="', esc_attr($dayGuid), '">';
                                $displayDayLabel = true;
                            }

                            if ($time == 'all-day') {
                                echo '<div class="all-day-event">';
                                if (!$displayAllDayLabel) {
                                    echo '<dt class="all-day-label">', __('All Day', 'rrze-calendar'), '</dt>';
                                    $displayAllDayLabel = true;
                                }
                                // Display label (title)
                                printf('<dd class="event-label"><a href="%1$s">%2$s</a></dd>', Endpoint::endpointUrl($event['slug']), html_entity_decode(str_replace('/', '/<wbr />', $event['label'])));
                                // Display Description/Location/Organizer
                                //echo '<dd class="event-desc">', $event['eventdesc'], '</dd>';
                                echo '</div>';
                            } else {
                                echo '<div class="day-event">';
                                if (!empty($event['start'])) {
                                    echo '<dt class="time">';
                                    echo $event['start'];
                                    if (!empty($event['end']) && $event['end'] != $event['start']) {
                                        echo ' &mdash; ', $event['end'];
                                    }
                                    echo '</dt>';
                                }
                                // Display label (title)
                                printf('<dd class="event-label"><a href="%1$s">%2$s</a></dd>', Endpoint::endpointUrl($event['slug']), html_entity_decode(str_replace('/', '/<wbr />', $event['label'])));
                                // Display Description/Location/Organizer
                                //echo '<dd class="event-desc">', $event['eventdesc'], '</dd>';
                                echo '</div>';
                            }
                        }
                    }
                    if ($displayDayLabel) {
                        echo '</dl>';
                        echo '</article>';
                    }
                }
                echo '</section>';
            }
        }
    }
}
