<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

global $wp_locale;

$daysOfWeek = Utils::getDaysOfWeek();
$startOfWeek = get_option('start_of_week', 0);

$uid = Utils::createId();

$today = Utils::date('Ymd');
$todayD = Utils::date('d');
$todayYM = Utils::date('Ym');

echo '<section class="rrze-calendar layout-month monthnav-arrows calendar_toggle calendar_toggle_lightbox" id="', $uid, '">';

echo '<select class="rrze-calendar-select" autocomplete="off" data-this-month="', esc_attr($todayYM), '">';

// Build list from earliest to latest month
foreach (array_keys((array)$data['events']) as $year) {
    for ($m = 1; $m <= 12; $m++) {
        $month = $m < 10 ? '0' . $m : '' . $m;
        $ym = $year . $month;
        if (isset($data['earliest']) && $ym < $data['earliest']) {
            continue;
        }
        if (isset($data['latest']) && $ym > $data['latest']) {
            break (2);
        }
        $selected = $ym == $todayYM ? ' selected="selected"' : '';
        $monthLabel = ucwords(Utils::date(__('F Y', 'rrze-calendar'), $m . '/1/' . $year));
        echo '<option value="', esc_attr($ym), '"', $selected, '>', $monthLabel, '</option>';
    }
}
echo '</select>';

// Toggle show/hide past events on mobile view
echo '<p class="rrze-calendar-past-events-toggle mobile-only inline_block" aria-hidden="true">',
'<a href="#" data-rrze-calendar-action="show-past-events">', __('Show past events', 'rrze-calendar'), '</a>',
'</p>';

// Build monthly calendars
foreach (array_keys((array)$data['events']) as $year) {
    for ($m = 1; $m <= 12; $m++) {
        $month = $m < 10 ? '0' . $m : '' . $m;
        $ym = $year . $month;
        if (isset($data['earliest']) && $ym < $data['earliest']) {
            continue;
        }
        if (isset($data['latest']) && $ym > $data['latest']) {
            break (2);
        }
        $monthLabel = ucwords(Utils::date(__('F Y', 'rrze-calendar'), $m . '/1/' . $year));
        $monthGuid = Utils::createId() . '-' . $ym;

        $past = ($ym < $todayYM) ? ' past' : '';
        echo '<article class="rrze-calendar-month-wrapper', $past, '" data-year-month="', esc_attr($ym), '">';

        echo '<h3 class="rrze-calendar-label" id="', esc_attr($monthGuid), '">', $monthLabel, '</h3>';

        echo '<table class="rrze-calendar-month-grid" aria-labelledby="', esc_attr($monthGuid), '">';
        echo '<thead><tr>';
        foreach ((array)$daysOfWeek as $w => $dow) {
            echo '<th data-dow="', $w, '">', $dow, '</th>';
        }
        echo '</tr></thead>';

        echo '<tbody><tr>';

        $firstDow = Utils::firstDow($m . '/1/' . $year);
        if ($firstDow < $startOfWeek) {
            $firstDow = $firstDow + 7;
        }
        $pastMonth = ($m - 1) ? ($m - 1) : 1;
        $lastDayOfPastMonth = Utils::date('t', $pastMonth . '/1/' . $year);
        $dayCount = 2;
        for ($offDow = $startOfWeek; $offDow < $firstDow; $offDow++) {
            $date = Utils::date('Ymd', $pastMonth . '/' .  ($lastDayOfPastMonth - $firstDow + $dayCount) . '/' . $year);
            echo '<td class="off" data-dow="', intval($offDow), '">';
            echo '<div class="day">';
            /* translators: %s: Day of the month without leading zeros. */
            echo '<span class="no-mobile" aria-hidden="true">', Utils::date(__('j', 'rrze-calendar'), $date), '</span>';
            echo '</div>';
            echo '</td>';
            $dayCount++;
        }
        for ($day = 1; $day <= Utils::date('t', $m . '/1/' . $year); $day++) {
            $date = Utils::date('Ymd', $m . '/' . $day . '/' . $year);
            $d = Utils::date('d', $date);
            $dow = Utils::date('w', $date);
            $dayEvents = isset($data['events'][$year][$month][$d]) ? $data['events'][$year][$month][$d] : null;
            $dayGuid = Utils::createId() . '-' . $ym . $d;
            $dayClasses = Utils::dayClasses([
                'date' => $date,
                'today' => $today,
                'count' => count((array)$dayEvents),
                'filler' => !empty($dayEvents['all-day'][0]['filler'])
            ]);

            if ($dow == $startOfWeek) {
                echo '</tr><tr>';
            }

            echo '<td data-dow="', intval($dow), '" class="', esc_attr($dayClasses), '">';
            echo '<div class="day">';
            echo '<span class="mobile-only" id="', esc_attr($dayGuid), '">', Utils::date(__('l F jS, Y', 'rrze-calendar'), $date), '</span>';
            /* translators: %s: Day of the month without leading zeros. */
            echo '<span class="no-mobile" aria-hidden="true">', Utils::date(__('j', 'rrze-calendar'), $date), '</span>';
            echo '</div>';

            if (!empty($dayEvents)) {
                echo '<ul class="events" aria-labelledby="', esc_attr($dayGuid), '">';
                foreach ((array)$dayEvents as $time => $events) {
                    foreach ((array)$events as $event) {
                        // Colors
                        if (!empty($event['cat_bgcolor'])) {
                            $colors[$event['post_id']] = [
                                'bg_color' => $event['cat_bgcolor'],
                                'color'    => $event['cat_color']
                            ];
                        }

                        if ($time == 'all-day') {
                            echo '<li class="', Events::cssClasses($event, $time), '">';
                            if (!empty($event['multiday']['start_time'])) {
                                $start = Utils::timeFormat($event['multiday']['start_time']);
                                $end = Utils::timeFormat($event['multiday']['end_time']);                                
                                $timeSpan = '<span class="time">' . $start;
                                if (!empty($end) && $end != $start) {
                                    $timeSpan .= '<span class="end_time">&#8211; ' . $end . '</span>';
                                }
                                $timeSpan .= '</span>';
                                echo $timeSpan;
                            } else {
                                echo '<span class="all-day-indicator">', __('All Day', 'rrze-calendar'), '</span>';
                            }

                            // Event label (title)
                            $label = $event['label'] ? strip_tags($event['label']) : '';

                            // Description/Location/Organizer
                            //$eventDesc = $event['eventdesc'] ? strip_tags($event['eventdesc']) : '';
                            //$location = $event['location'] ? strip_tags($event['location']) : '';
                            //$organizer = $event['organizer'] ? strip_tags($event['organizer']) : '';

                            // Readable RRULE
                            //$rrule = $event['readable_rrule'] ? '<i>' . $event['readable_rrule'] . '</i>' : '';

                            if (strpos($dayClasses, 'past') === false) {
                                printf(
                                    '<a class="rrze-calendar-sc-%3$s" href="%1$s">%2$s</a>',
                                    Endpoint::endpointUrl($event['slug']),
                                    $label,
                                    $event['post_id']
                                );
                            } else {
                                echo $label;
                            }

                            echo '</li>';
                        } else {
                            echo '<li class="', Events::cssClasses($event, $time), '">';
                            $timeSpan = '';
                            if (!empty($event['multiday']['start_time'])) {
                                $start = Utils::timeFormat($event['multiday']['start_time']);
                                $end = Utils::timeFormat($event['multiday']['end_time']);                                
                                $timeSpan = '<span class="time">' . $start;
                                if (!empty($end) && $end != $start) {
                                    $timeSpan .= '<span class="end_time">&#8211; ' . $end . '</span>';
                                }
                                $timeSpan .= '</span>';
                                echo $timeSpan;
                            } elseif (!empty($event['start'])) {
                                $timeSpan = '<span class="time">' . $event['start'];
                                if (!empty($event['end']) && $event['end'] != $event['start']) {
                                    $timeSpan .= '<span class="end_time">&#8211; ' . $event['end'] . '</span>';
                                }
                                $timeSpan .= '</span>';
                                echo $timeSpan;
                            }

                            // Event label (title)
                            $label = $event['label'] ? strip_tags($event['label']) : '';

                            // Description/Location/Organizer
                            $eventDesc = $event['eventdesc'] ?? '';
                            $eventDesc = nl2br(htmlspecialchars_decode($eventDesc));
                            $eventDesc = apply_filters('the_content', $eventDesc);
                            $eventDesc = str_replace(']]>', ']]&gt;', $eventDesc);
                            //$location = $event['location'] ? strip_tags($event['location']) : '';
                            //$organizer = $event['organizer'] ? strip_tags($event['organizer']) : '';

                            // Readable RRULE
                            //$rrule = $event['readable_rrule'] ? '<i>' . $event['readable_rrule'] . '</i>' : '';

                            if (strpos($dayClasses, 'past') === false) {
                                $ttipText = $eventDesc ? '<span class="tooltip-container"><span class="tooltip"><span><a class="rrze-calendar-sc-%3$s" href="%1$s">%2$s</a></span><span class="tooltip-drop tooltip-top">%4$s</span></span></span>' : '<a class="rrze-calendar-sc-%3$s" href="%1$s">%2$s</a>';
                                printf(
                                    $ttipText,
                                    Endpoint::endpointUrl($event['slug']),
                                    $label,
                                    $event['post_id'],
                                    $eventDesc
                                );
                            } else {
                                echo $label;
                            }
                            echo '</li>';
                        }
                    }
                }
                echo '</ul>';
            }
            echo '</td>';
        }
        $calcDow = ($startOfWeek != 0 && $dow == 0) ? 7 : $dow;
        $futureMonth = ($m + 1) > 12 ? 12 : ($m + 1);
        $dayCount = 0;
        for ($offDow = $calcDow + 1; $offDow < ($startOfWeek + 7); $offDow++) {
            $date = Utils::date('Ymd', $futureMonth . '/' . $dayCount + 1 . '/' . $year);
            echo '<td class="off" data-dow="', intval($offDow % 7), '">';
            echo '<div class="day">';
            /* translators: %s: Day of the month without leading zeros. */
            echo '<span class="no-mobile" aria-hidden="true">', Utils::date(__('j', 'rrze-calendar'), $date), '</span>';
            echo '</div>';
            echo '</td>';
            $dayCount++;
        }
        echo '</tr>';
        echo '</tbody></table>';

        // "No events" messages for mobile view
        if (empty($data['events'][$year][$month])) {
            echo '<p class="mobile-only no_events">', __('No events found.', 'rrze-calendar'), '</p>';
        } elseif (
            $ym == $todayYM &&
            max(array_keys($data['events'][$year][$month])) <= $todayD &&
            empty($data['events'][$year][$month][$todayD])
        ) {
            echo '<p class="mobile-only no_additional_events">', __('No additional events this month.', 'rrze-calendar'), '</p>';
        }

        echo '</article>';
    }

    // Colors (inline css)
    if (!empty($colors)) {
        foreach ($colors as $key => $color) {
            $inline = sprintf(
                'a.rrze-calendar-sc-%1$s {box-shadow: inset 0 -0.1rem 0 0 %2$s; text-decoration-color: %2$s;} a.rrze-calendar-sc-%1$s:active, a.rrze-calendar-sc-%1$s:focus, a.rrze-calendar-sc-%1$s:hover {box-shadow: inset 0 -1.5rem 0 %2$s; text-decoration-color: %2$s; color: %3$s;}',
                $key,
                $color['bg_color'],
                $color['color']
            );
            wp_add_inline_style('rrze-calendar-sc-calendar', $inline);
        }
    }
}

echo '</section>';
