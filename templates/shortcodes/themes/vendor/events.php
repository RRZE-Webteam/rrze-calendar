<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

$count = 1;
$dateFormat = 'd';
$multidateFormat = 'd. F';
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
            echo '<section class="rrze-calendar events-list">';
            foreach ((array)$data['events'][$year][$month] as $day => $dayEvents) {

                // Pull out multi-day events and display them separately first
                foreach ((array)$dayEvents as $time => $events) {

                    foreach ((array)$events as $eventKey => $event) {

                        // Only list multi-day events
                        if (empty($event['multiday'])) {
                            continue;
                        }

                        if ($count > $limit) break;

                        // Has this multi-day event already been listed?
                        if (!in_array($event['multiday']['event_key'], $multidayEventKeysUsed)) {

                            // Format date/time for header
                            $mdStart = Utils::dateFormat($multidateFormat, strtotime($event['multiday']['start_date']));
                            $mdEnd = Utils::dateFormat($multidateFormat, strtotime($event['multiday']['end_date']));

                            // Format date/time for header
                            $mdStartMinimal = Utils::dateFormat($dateFormat, strtotime($event['multiday']['start_date']));
                            $mdEndMinimal = Utils::dateFormat($dateFormat, strtotime($event['multiday']['end_date']));

                            if ($time != 'all-day') {
                                $mdStart .= ' <span class="time-inline">' . Utils::timeFormat($event['multiday']['start_time']) . '</span>';
                                $mdEnd .= ' <span class="time-inline">' . Utils::timeFormat($event['multiday']['end_time']) . '</span>';
                            }

                            // Display month label if needed
                            if (!$displayMonthLabel) {
                                echo '<h3 class="calendar-label" id="', esc_attr($monthGuid), '">', $monthLabel, '</h3>';
                                $displayMonthLabel = true;
                            }

                            $dayLabel = $mdStart . ' &#8211 ' . $mdEnd;
                            $dayGuid = $uid . '-' . Utils::createId();

                            $dayMultiLabel = '<span class="start-number">' . $mdStartMinimal . '</span><hr /><span class="end-number">' . $mdEndMinimal . '</span>';
                            $dayMultiGuid = $uid . '-' . Utils::createId();

                            echo '<article class="calendar-article" data-date="', esc_attr($dayLabel), '">';
                            echo '<h4 class="calendar-date multi" id="', esc_attr($dayMultiGuid), '">', $dayMultiLabel, '</h4>';
                            echo '<dl class="calendar-events" aria-labelledby="', esc_attr($dayGuid), '">';
                            echo '<div class="multiday-event">';
                            // Display label (title)
                            echo '<h5 class="time"><p>', $dayLabel, '</p></h5>';
                            printf('<dd class="event-label"><a href="%1$s">%2$s</a></dd>', Endpoint::endpointUrl($event['slug']), html_entity_decode(str_replace('/', '/<wbr />', $event['label'])));
                            // Display Description/Location/Organizer
                            echo '<dd class="event-location">', $event['location'], '</dd>';
                            echo '</div>';
                            echo '</dl>';
                            echo '</article>';
                            // This multi-day event has already been listed
                            $multidayEventKeysUsed[] = $event['multiday']['event_key'];

                            $count++;
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

                        if ($count > $limit) break;

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
                            echo '<dd class="event-location">', $event['location'], '</dd>';
                            echo '</div>';
                        }

                        $count++;
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
