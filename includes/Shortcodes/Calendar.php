<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use DateInterval;
use RRZE\Calendar\Utils;
use RRZE\Calendar\Templates;
use RRZE\Calendar\CPT\CalendarEvent;

class Calendar
{
    public static function init()
    {
        add_shortcode('rrze-calendar', [__CLASS__, 'shortcode']);
        add_shortcode('rrze-kalender', [__CLASS__, 'shortcode']);
        add_shortcode('calendar', [__CLASS__, 'shortcode']);
        add_shortcode('kalender', [__CLASS__, 'shortcode']);
        add_action( 'wp_ajax_rrze-calendar-update-calendar', [__CLASS__, 'ajaxUpdateCalendar'] );
        add_action( 'wp_ajax_nopriv_rrze-calendar-update-calendar', [__CLASS__, 'ajaxUpdateCalendar'] );
    }

    public static function shortcode($atts, $content = "")
    {
        $atts = shortcode_atts(
            [
                'categories' => '',  // Multiple categories (slugs) are separated by commas.
                'kategorien' => '',  // Multiple categories (slugs) are separated by commas.
                'tags' => '',        // Multiple keywords (slugs) are separated by commas.
                'schlagworte' => '', // Multiple keywords (slugs) are separated by commas.
                'year' => date('Y', current_time('timestamp')),
                'month' => 'current',
                'day' => '',
                'layout' => 'full',
                'navigation' => 'ja',
            ],
            $atts
        );

        // Calendar period
        $buttonDayClass = 'inactive';
        $buttonMonthClass = 'active';
        $buttonYearClass = 'inactive';
        $day = '';

        if (isset($_GET['cal-year']) && is_numeric($_GET['cal-year']) && ((int)$_GET['cal-year'] > 2000 && (int)$_GET['cal-year'] < 3000)) {
            $year = (int)$_GET['cal-year'];
        } else {
            if (is_numeric($atts['year']) && ((int)$atts['year'] > 2000 && (int)$atts['year'] < 3000)) {
                $year = (int)$atts['year'];
            } else {
                $year = date('Y', current_time('timestamp'));
            }
        }
        if (isset($_GET['cal-month']) && is_numeric($_GET['cal-month']) && ((int)$_GET['cal-month'] >= 1 && (int)$_GET['cal-month'] <= 12)) {
            $month = (int)$_GET['cal-month'];
        } else {
            if (is_numeric($atts['month']) && ((int)$atts['month'] >= 1 && (int)$atts['month'] <= 12)) {
                $month = (int)$atts['month'];
            } elseif (!isset($_GET['cal-year']) && $atts['month'] == "current") {
                $month = date('m', current_time('timestamp'));
            } else {
                $month = '';
                $atts['layout'] = 'mini';
                $buttonDayClass = 'inactive';
                $buttonMonthClass = 'inactive';
                $buttonYearClass = 'active';
            }
        }
        if ($month != '') {
            if (isset($_GET['cal-day']) && is_numeric($_GET['cal-day']) && ((int)$_GET['cal-day'] >= 1 && (int)$_GET['cal-day'] <= 31)) {
                $day = (int)$_GET['cal-day'];
                $buttonDayClass = 'active';
                $buttonMonthClass = 'inactive';
                $buttonYearClass = 'inactive';
            } else {
                if (is_numeric($atts['day']) && ((int)$atts['day'] >= 1 && (int)$atts['day'] <= 31)) {
                    $day = (int)$atts['day'];
                    $buttonDayClass = 'active';
                    $buttonMonthClass = 'inactive';
                    $buttonYearClass = 'inactive';
                } elseif ($atts['day'] == "heute") {
                    $day = date('d', current_time('timestamp'));
                    $buttonDayClass = 'active';
                    $buttonMonthClass = 'inactive';
                    $buttonYearClass = 'inactive';
                } else {
                    $day = '';
                    $buttonDayClass = 'inactive';
                    $buttonMonthClass = 'active';
                    $buttonYearClass = 'inactive';
                    $monthName = date("F", mktime(0, 0, 0, $month, 1));
                    $startObj = date_create('first day of ' . $monthName . ' ' . $year);
                    $startTS = $startObj->getTimestamp();
                    $endObj = date_create('last day of ' . $monthName . ' ' . $year);
                    $endObj->add(new DateInterval('PT23H59M59S'));
                    $endTS = $endObj->getTimestamp();
                }
            }
        } else {
            $startObj = date_create('first day of january' . $year);
            $startTS = $startObj->getTimestamp();
            $endObj = date_create('last day of december ' . $year);
            $endObj->add(new DateInterval('PT23H59M59S'));
            $endTS = $endObj->getTimestamp();
        }
        if ($day != '') {
            $startTS = strtotime($year.'-'.$month.'-'.str_pad($day, 2, '0', STR_PAD_LEFT));
            $endTS = $startTS + 24*60*60;
        }

        // Calendar layout: full or mini
        $layout = $atts['layout'] == 'full' ? 'full' : 'mini';
        if ($layout == 'full' && $month == '') {
            $month = date('m', current_time('timestamp'));
        }

        // Paging
        $paging = $atts['navigation'] == 'ja' ? true : false;

        $args = [
            'post_type' => CalendarEvent::POST_TYPE,
            'numberposts' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'event-lastdate',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'event-lastdate',
                    'value' => $startTS,
                    'compare' => '>='
                ],
            ],
        ];

        $taxQuery = [];

        $tax = '';
        if ($atts['categories']) {
            $tax = $atts['categories'];
        } elseif ($atts['kategorien']) {
            $tax = $atts['kategorien'];
        }
        $categories = Utils::strListToArray($tax, 'sanitize_title');

        $tax = '';
        if ($atts['tags']) {
            $tax = $atts['tags'];
        } elseif ($atts['schlagworte']) {
            $tax = $atts['schlagworte'];
        }
        $tags = Utils::strListToArray($tax, 'sanitize_title');

        if (!empty($categories)) {
            $taxQuery = [
                [
                    'taxonomy' => CalendarEvent::TAX_CATEGORY,
                    'field'    => 'slug',
                    'terms'    => $categories
                ]
            ];
        }

        if (!empty($tags)) {
            $taxQuery = array_merge(
                $taxQuery,
                [
                    [
                        'taxonomy' => CalendarEvent::TAX_TAG,
                        'field'    => 'slug',
                        'terms'    => $tags
                    ]
                ]
            );
        }

        if ($taxQuery) {
            $taxQuery = array_merge(['relation' => 'AND'], $taxQuery);
            $args = array_merge($args, ['tax_query' => $taxQuery]);
        }

        // Get events in calendar period
        $events = get_posts($args);
        $eventsArray = Utils::buildEventsArray($events, date('Y-m-d', $startTS), (isset($endTS) ? date('Y-m-d', $endTS) : NULL));

        // Render calendar output
        $output = '<div class="rrze-calendar">';
        if ($layout == 'full' || $layout == 'mini' && $month == '') {
            $output .= '<p class="cal-type-select">'
                . do_shortcode('[button style="ghost" link="?cal-year=' . $year . '&cal-month=' . date('m', current_time('timestamp')) . '&cal-day=' . date('d', current_time('timestamp')) . '" class="' . $buttonDayClass . '" title="' . __('View day', 'rrze-calendar') . ']' . __('Day', 'rrze-calendar') . '[/button]'
                    . '[button style="ghost" link="?cal-year=' . $year . '&cal-month=' . date('m', current_time('timestamp')) . '" class="' . $buttonMonthClass . '" title="' . __('View monthly calendar', 'rrze-calendar') . ']' . __('Month', 'rrze-calendar') . '[/button]'
                    . '[button style="ghost" link="?cal-year=' . $year . '" class="' . $buttonYearClass . '" title="' . __('View yearly calendar', 'rrze-calendar') . ']' . __('Year', 'rrze-calendar') . '[/button]')
                . '</p>';
        }
        $output .= self::buildCalendar($year, $month, $day, $eventsArray, $layout, $paging, $taxQuery);
        $output .= '</div>';

        wp_enqueue_style('rrze-calendar-sc-calendar');
        wp_enqueue_script('jquery');
        wp_enqueue_script( 'rrze-calendar-sc-calendar', plugin_dir_url( __DIR__ ) . 'js/shortcode.js', array(), '1.0.0', true );
        return $output;
    }

    /*protected static function output(&$data, $template)
    {
        ob_start();
        include $template;
        return ob_get_clean();
    }*/

    /**
     * Build a event calendar for a defined period (month or year)
     * @param   string  $month      If empty -> build yearly calendar
     * @param   string  $year
     * @param   string  $day
     * @param   array   $eventsArray
     * @param   string  $layout     'full' or 'mini'
     * @param   bool    $paging     Allow skipping to next/previous month/year
     * @return  string
     */
    private static function buildCalendar($year, $month, $day, $eventsArray, $layout = 'full', $paging = true, $taxQuery = []): string {
        $output = '';
        if ($day != '') {
            $output .= '<div class="calendar-wrapper cal-day" data-period="'.$year.'-'.$month.'-'.$day.'" data-layout="' . ($layout == 'full' ? 'full' : 'mini') . '">';
            $output .= self::renderDayList($year, $month, $day, $eventsArray);
            $output .= '</div>';
        } elseif ($month != '') {
            $month = str_pad($month, 2, '0', STR_PAD_LEFT);
            $output .= '<div class="calendar-wrapper cal-month" data-period="'.$year.'-'.$month.'" data-layout="' . ($layout == 'full' ? 'full' : 'mini') . '">';
            if ($layout == 'full') {
                $output .= self::renderMonthCalendarFull($year, $month,  $eventsArray, $paging, $taxQuery);
            } else {
                $output .= self::renderMonthCalendarMini($year, $month,  $eventsArray, true, $taxQuery);
            }
            $output .= '</div>';
        } else {
            $output .= '<div class="calendar-wrapper cal-year" data-period="'.$year.'" data-layout="' . ($layout == 'full' ? 'full' : 'mini') . '">';
            $output .= '<div class="calendar-header"><h2 class="title-year">'.$year.'</h2>';
            if ($paging) {
                $output .= '<ul class="calendar-pager">
                    <li class="date-prev">
                        <a href="#" title="Zum vorangegangenen Jahr wechseln" rel="nofollow" data-direction="prev">« Zurück</a>
                    </li>
                    <li class="date-next">
                        <a href="#" title="Zum nächsten Jahr" rel="nofollow" data-direction="next">Weiter »</a>
                    </li>
                </ul>';
            }
            $output .= '</div>'
                .'<div class="calendar-year">';
            for ($i = 1; $i <= 12; $i++) {
                $month = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                $output .= self::renderMonthCalendarMini($year, $month,  $eventsArray, false, $taxQuery);
            }
            $output .= '</div></div>';
        }
        return $output;
    }

    /**
     * Render list of events on one day
     * @param array $events
     */
    private static function renderDayList($year, $month, $day, $eventsArray = [], $taxQuery = []) {
        $calDay = $year.'-'.str_pad($month, 2, '0', STR_PAD_LEFT).'-'.str_pad($day, 2, '0', STR_PAD_LEFT);
        $calDayTs = strtotime($calDay);
        $output = '<div class="calendar-header"><h2 class="title-year">' . date_i18n(get_option( 'date_format' ), $calDayTs) . '</h2>';
        $output .= '<ul class="calendar-pager">
            <li class="date-prev">
                <a href="#" title="Zum vorangegangenen day wechseln" rel="nofollow" data-direction="prev">« Zurück</a>
            </li>
            <li class="date-next">
                <a href="#" title="Zum nächsten day wechseln" rel="nofollow" data-direction="next">Weiter »</a>
            </li>
        </ul>';
        $output .= '</div>';

        $list = '<ul class="day-list">';
        $hasEvents = false;

        foreach ($eventsArray as $ts => $events) {
            foreach ($events as $event) {
                $meta = get_post_meta($event['id']);
                $eventStart = $ts;
                $eventEnd = $event['end'];
                $eventStartDate = date('Y-m-d', $eventStart);
                $eventEndDate = date('Y-m-d', $eventEnd);
                if ($calDay < $eventStartDate || $calDay > $eventEndDate) {
                    continue;
                }
                $isAllDay = Utils::getMeta($meta, 'all-day') == 'on';
                $timeText = '';

                $eventTitle = get_the_title($event['id']);
                $eventURL = get_the_permalink($event['id']);
                $eventTitle = '<a href="' . $eventURL . '">' . $eventTitle . '</a>';
                // Date/Time
                if ($eventStartDate == $eventEndDate && !$isAllDay) {
                    $timeText = '<span class="event-date">' . date('H:i', $eventStart) . ' - ' . date('H:i', $eventEnd) . '</span>';
                } elseif ($eventStartDate == $eventEndDate && $isAllDay) {
                    $timeText = '<span class="event-date">' . __('All Day', 'rrze-calendar') . '</span>';
                } else {
                    $timeText = '<span class="event-date">' . date_i18n(get_option( 'date_format' ), $eventStart) . ' - ' . date_i18n(get_option( 'date_format' ), $eventEnd) . '</span>';
                }
                // Location
                $location = Utils::getMeta($meta, 'location');
                if ($location != '') {
                    $locationText = '<br />' . $location;
                } else {
                    $locationText = '';
                }

                $list .= '<li>';
                $list .= $timeText . '<br />' . $eventTitle . $locationText;
                $list .= '</li>';
                $hasEvents = true;
            }
        }
        $list .= '</ul>';

        if ($hasEvents) {
            $output .= $list;
        } else {
            $output .= '<p>' . __('There are no events scheduled for this day.', 'rrze-calendar') . '</p>';
        }

        return $output;
    }

    /**
     * Render mini calendar month view with availability information for external use
     * @param   integer $month
     * @param   integer $year
     * @param   array   $events
     * @param   bool    $showYear
     * @return  string
     */

    private static function renderMonthCalendarMini($year, $month,  $eventsArray = [], $showYear = false, $taxQuery = []) {
        global $wp_locale;
        $first_day_in_month = date('w',mktime(0,0,0,$month,1,$year));
        $month_days = date('t',mktime(0,0,0,$month,1,$year));
        $month_names = Utils::getMonthNames('full');
        $month_name = $month_names[(int)$month-1];
        // in PHP, Sunday is the first day in the week with number zero (0)
        // to make our calendar works we will change this to (7)
        if ($first_day_in_month == 0){
            $first_day_in_month = 7;
        }
        if ($showYear) {
            $month_name .= ' ' . $year;
        } else {
            $month_name = '<a href="?cal-year='.$year.'&cal-month='.$month.'"">' . $month_name . '</a>';
        }
        $output = '<div class="calendar-month mini">';
        $output .= '<table>';
        $output .= '<tr><th colspan="7">' . $month_name . '</th></tr>';
        $output .= '<tr class="days">'
            .'<td>'.$wp_locale->get_weekday_abbrev($wp_locale->get_weekday(1)).'</td>'
            .'<td>'.$wp_locale->get_weekday_abbrev($wp_locale->get_weekday(2)).'</td>'
            .'<td>'.$wp_locale->get_weekday_abbrev($wp_locale->get_weekday(3)).'</td>'
            .'<td>'.$wp_locale->get_weekday_abbrev($wp_locale->get_weekday(4)).'</td>'
            .'<td>'.$wp_locale->get_weekday_abbrev($wp_locale->get_weekday(5)).'</td>'
            .'<td>'.$wp_locale->get_weekday_abbrev($wp_locale->get_weekday(6)).'</td>'
            .'<td>'.$wp_locale->get_weekday_abbrev($wp_locale->get_weekday(0)).'</td>'
            .'<tr>';

        for($i = 1; $i < $first_day_in_month; $i++) {
            $output .= '<td> </td>';
        }
        //var_dump($eventsArray);
        for($day = 1; $day <= $month_days; $day++) {
            $pos = ($day + $first_day_in_month - 1) % 7;
            $date = $year.'-'.$month.'-'.str_pad($day, 2, '0', STR_PAD_LEFT);
            $calDay = strtotime($date);
            $linkOpen = '';
            $linkClose = '';
            $class = 'has-no-events';
            foreach ($eventsArray as $ts => $events) {
                foreach ($events as $event) {
                    $eventStart = $ts;
                    $eventEnd = $event['end'];
                    //var_dump($calDay >= $eventStart && $calDay <= $eventEnd);
                    //var_dump($date, date('Y-m-d', $eventStart), date('Y-m-d', $eventEnd), ($date >= date('Y-m-d', $eventStart) && $date <= date('Y-m-d', $eventEnd)) );
                    //print "<br />";
                    //if ($calDay >= $eventStart && $calDay <= $eventEnd) {
                    if ($date >= date('Y-m-d', $eventStart) && $date <= date('Y-m-d', $eventEnd)) {
                        $linkOpen = '<a href="?cal-year='.$year.'&cal-month='.$month.'&cal-day='.str_pad($day, 2, '0', STR_PAD_LEFT).'" title="'.__('View Details', 'rrze-calendar').'">';
                        $linkClose = '</a>';
                        $class = 'has-events';
                        continue 2;
                    }
                }
            }
            $day = date('d', $calDay);
            $output .= '<td class="' . $class . '">' . $linkOpen . $day . $linkClose . '</td>';
            if ($pos == 0) $output .= '</tr><tr>';
        }
        //TODO: leere Tabellenzellen bis Monatsende
        //TODO: Link intern zu Tagesansicht

        $output .= '</tr>';
        $output .= '</table>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render Full Calendar Month View with event details
     * @param integer $month
     * @param integer $year
     * @param array $events
     * @param bool $paging  Allow skipping to next/previous month
     * @param array $locations
     * @return string
     */

    private static function renderMonthCalendarFull($year, $month,  $eventsArray = [], $paging = true, $taxQuery = []) {
        $first_day_in_month = date('w',mktime(0,0,0,$month,1,$year));
        $month_days = date('t',mktime(0,0,0,$month,1,$year));
        $month_names = Utils::getMonthNames('full');
        $month_name = $month_names[(int)$month-1];
        // in PHP, Sunday is the first day in the week with number zero (0)
        // to make our calendar works we will change this to (7)
        if ($first_day_in_month == 0){
            $first_day_in_month = 7;
        }
        $day_names = Utils::getDaysOfWeek('full');

        $taxQueryJSON = json_encode($taxQuery);
        $taxQueryBase64 = base64_encode($taxQueryJSON);

        // Calender Header (Title + Nav)
        $output = '<div class="calendar-header"><h2 class="title-year">' . $month_name . ' ' . $year . '</h2>';
        if ($paging) {
            $output .= '<ul class="calendar-pager">
            <li class="date-prev">
                <a href="#" title="' . __('Go to previous month', 'rrze-calendar') . '" rel="nofollow" data-direction="prev" data-taxquery="'.$taxQueryBase64.'">« ' . __('Previous', 'rrze-calendar') . '</a>
            </li>
            <li class="date-next">
                <a href="#" title="' . __('Go to next month', 'rrze-calendar') . '" rel="nofollow" data-direction="next" data-taxquery="'.$taxQueryBase64.'">' . __('Next', 'rrze-calendar') . ' »</a>
            </li>
        </ul>';
        }
        $output .= '</div>';
        $output .= '<div class="calendar-month full">';
        $output .= '<div class="days">';
        foreach ($day_names as $i => $day_name) {
            $output .= '<div style="grid-column-start: day-'.($i+1).'; grid-column-end: span 1; grid-row-start: date; grid-row-end: span 1;" class="day-names">' . $day_name . '</div>';
        }
        $output .='</div>';

        // Weeks
        $output .= '<div class="week">';
        for($i = 1; $i < $first_day_in_month; $i++) {
            $output .= '<div class="empty-day" style="grid-column: day-'.$i.' / day-'.$i.';  grid-row: 1 / 6;" aria-hidden="true"> </div>';
        }
        $weekNum = 1;
        $eventsPerDay = [];

        for($day = 1; $day <= $month_days; $day++) {
            $pos = ($day + $first_day_in_month - 1) % 7;
            $date = $year.'-'.$month.'-'.str_pad($day, 2, '0', STR_PAD_LEFT);
            $col = $pos == 0 ? 7 : $pos;
            $calDay = $date;
            $output .= '<div class="day" style="grid-column: day-'.$col.' / day-'.$col.'; grid-row: 1 / 2;" aria-hidden="true">' . $day . '</div>';
            $week = '';
            $daysLeft = $month_days - $day + 1;

            // Background div for each day
            $week .= '<div class="no-event" style="grid-column-start: day-'.$col.'; grid-column-end: span 1; grid-row-start: 2; grid-row-end: 6" aria-hidden="true"> </div>';

            foreach ($eventsArray as $ts => $events) {
                if (isset($eventsPerDay[$date]) && $eventsPerDay[$date] > 3) {
                    continue;
                }
                foreach ($events as $event) {
                    $eventStart = $ts;
                    $eventEnd = $event['end'];
                    $offset = Utils::getTimezoneOffset('seconds');
                    $eventStartLocal = $eventStart + $offset;
                    $eventEndLocal = $eventEnd + $offset;
                    $eventStartDate = date('Y-m-d', $eventStartLocal);
                    $eventEndDate = date('Y-m-d', $eventEndLocal);
                    if ($calDay < $eventStartDate || $calDay > $eventEndDate) {
                        continue;
                    }
                    $meta = get_post_meta($event['id']);
                    $isAllDay = Utils::getMeta($meta, 'all-day') == 'on';
                    $eventTitle = get_the_title($event['id']);
                    $eventURL = get_the_permalink($event['id']);
                    $categories = get_the_terms($event['id'], CalendarEvent::TAX_CATEGORY);
                    if ($categories) {
                        $catID = $categories[0]->term_id;
                        $catColor = get_term_meta($catID, 'color', true);
                    } else {
                        $catColor = '';
                    }
                    if ($catColor == '') $catColor = 'var(--color-primary-ci-hell, #003366)';
                    $eventTitleShort = $eventTitle;
                    if (strlen($eventTitle) > 40) {
                        $eventTitleShort = mb_substr($eventTitle, 0, 37) . '&hellip;';
                    }
                    $eventTitle = '<a href="' . $eventURL . '">' . $eventTitle . '</a>';
                    $eventTitleShort = '<a href="' . $eventURL . '">' . $eventTitleShort . '</a>';

                    $locationMeta = '';
                    $location = Utils::getMeta($meta, 'location');
                    if ($location != '') {
                        $locationMeta .= '<meta itemprop="location" content="' . $location . '" />';
                    }
                    $vc_url = Utils::getMeta($meta, 'vc-url');
                    if ($vc_url != '') {
                        $locationMeta .= '<span itemprop="location" itemscope itemtype="https://schema.org/VirtualLocation"><meta itemprop="url" content="'. $vc_url . '" /></span>';
                    }

                    $eventClasses = ['event'];
                    if ($calDay == $eventStartDate) {
                        // Events starting on this day
                        array_push($eventClasses, 'event-start', 'event-end');
                        $dateClasses = ['event-date'];
                        $span = floor(($eventEndLocal - $eventStartLocal) / (60 * 60 * 24) + 1);
                        if ($span < 1) $span = 1;
                        if ($span > 1 || $isAllDay) {
                            $timeOut = '';
                        } else {
                            $dateClasses[] = 'hide-desktop';
                            $timeOut = '<span class="event-time">' . date('H:i', $eventStartLocal) . ' - ' . date('H:i', $eventEndLocal) . '<br /></span>';
                        }
                        if ($isAllDay && $eventStartDate == $eventEndDate) {
                            $dateClasses[] = 'hide-desktop';
                            $timeOut = '<span class="event-time">' . __('All Day', 'rrze-calendar') . '<br /></span>';
                        }
                        if (($col + $span) > 8) {
                            $span = 8 - $col + 1; // trim if event longer than week
                            if (($key = array_search('event-end', $eventClasses)) !== false) {
                                unset($eventClasses[$key]);
                            }
                        }
                        if ($span > $daysLeft) {
                            $span = $daysLeft; // trim if event longer than month
                            if (($key = array_search('event-end', $eventClasses)) !== false) {
                                unset($eventClasses[$key]);
                            }
                        }
                        $eventInfos = [];

                        // Set row counter
                        for ($i = 0 ; $i < $span; $i++) {
                            $startDay = date('d', $eventStartLocal);
                            $countDay = (int)$startDay + $i;
                            $countDate = date('Y-m-', $eventStartLocal) . str_pad($countDay, 2, '0',STR_PAD_LEFT);
                            if (isset($eventsPerDay[$countDate])) {
                                $eventsPerDay[$countDate]++;
                            } else {
                                $eventsPerDay[$countDate] = 1;
                            }
                        }
                        $rowNum = $eventsPerDay[$eventStartDate];
                        if (isset($eventsPerDay[$countDate]) && $eventsPerDay[$countDate] > 3) {
                            $week .= '<div class="more-events" style="grid-column: day-' . $col . ' / day-' . ($col + 1) . '; grid-row: ' . ($rowNum + 1) . ' / ' . ($rowNum + 2) . ';">'
                                . '<a href="?cal-year=' . $year . '&cal-month=' . $month . '&cal-day=' . $day . '">'
                                . __('More&hellip;', 'rrze-calendar')
                                . '</a></div>';
                            continue 2;
                        }
                        if ($eventStartDate == $eventEndDate) {
                            $dateOut = date('d.m.Y', $eventStartLocal);
                        } else {
                            $dateOut = date('d.m.Y', $eventStartLocal) . ' - ' . date('d.m.Y', $eventEndLocal);
                        }
                        $thumbnail = get_the_post_thumbnail($event['id'], 'medium');
                        $content = get_post_meta($event['id'], 'description', true);
                        $excerpt = strip_tags($content);
                        if (strlen($excerpt) > 100) {
                            $excerpt = substr($excerpt, 0, 100);
                            $excerpt = '<span>' . substr($excerpt, 0, strrpos($excerpt, ' ')) . '&hellip;</span>';
                        }
                        $week .= '<div itemtype="https://schema.org/Event" itemscope class="' . implode(' ', $eventClasses) . '" style="grid-column: day-' . $col . ' / day-' . ($col + $span) . '; grid-row: ' . ($rowNum + 1) . ' / ' . ($rowNum + 2) . '; border-color: ' . $catColor . ';">'
                                . '<p><span class="' . implode(' ', $dateClasses) . '">' . $dateOut . '<br /></span>'
                                . $timeOut
                                . '<span itemprop="name" class="event-title">' . $eventTitleShort . '</span></p>'
                                . '<meta itemprop="startDate" content="'. date_i18n('c', $eventStart) . '">'
                                . '<meta itemprop="endDate" content="'. date_i18n('c', $eventEnd) . '">'
                                . $locationMeta
                                . '<div role="tooltip" aria-hidden="true">'
                                    . ($thumbnail != '' ? '<p style="margin: 0;">' . $thumbnail . '</p>' : '')
                                    . '<div class="event-title">' . $eventTitle . '</div>'
                                    . '<div class="event-date-time">' . $dateOut . ', ' . $timeOut . '</div>'
                                    . '<div itemprop="description" class="event-description">' . $excerpt . ' <a href="' . $eventURL . '">' . __('Read more', 'rrze-calendar') . ' &raquo;</a></div>'
                                . '</div>'
                            . '</div>';

                    } elseif (($col == 1 || $day == 1) && $calDay > $eventStartDate && $calDay <= $eventEndDate) {
                        // Event continuing from past week (or past month)
                        if ((($eventEndLocal - strtotime($calDay)) / (60 * 60 * 24)) < 0.3) {
                            // Don't show event that end before 6:00, because it is probably the rest of a previous' day event
                            continue;
                        }
                        $span = floor(($eventEndLocal - strtotime($calDay)) / (60 * 60 * 24) + 1);
                        if ($span > 7) {
                            $span = 7; // trim if event longer than week
                            array_push($eventClasses, 'event-week');
                        } else {
                            array_push($eventClasses, 'event-end');
                        }
                        if ($span > $daysLeft) {
                            $span = $daysLeft - 1; // trim if event longer than month
                        }
                        // Set row counter
                        for ($i = 0 ; $i <= $span; $i++) {
                            $startDay = date('d', $eventStartLocal);
                            $countDay = (int)$startDay + $i;
                            $countDate = date('Y-m-', $eventStartLocal) . str_pad($countDay, 2, '0',STR_PAD_LEFT);
                            if (isset($eventsPerDay[$countDate])) {
                                $eventsPerDay[$countDate]++;
                            } else {
                                $eventsPerDay[$countDate] = 1;
                            }
                        }

                        if ($eventStartDate == $eventEndDate) {
                            $dateOut = date('d.m.Y', $eventStartLocal);
                        } else {
                            $dateOut = date('d.m.Y', $eventStartLocal) . ' - ' . date('d.m.Y', $eventEndLocal);
                        }
                        $timeOut = '<span class="event-time">' . date('H:i', $eventStartLocal) . ' - ' . date('H:i', $eventEndLocal) . '<br /></span>';
                        $thumbnail = get_the_post_thumbnail($event['id'], 'medium');
                        $content = get_post_meta($event['id'], 'description', true);
                        $excerpt = strip_tags($content);
                        if (strlen($excerpt) > 100) {
                            $excerpt = substr($excerpt, 0, 100);
                            $excerpt = '<span>' . substr($excerpt, 0, strrpos($excerpt, ' ')) . '&hellip;</span>';
                        }
                        $rowNum = $eventsPerDay[$eventStartDate];
                        $week .= '<div itemtype="https://schema.org/Event" itemscope class="' . implode(' ', $eventClasses) . '" style="grid-column: day-' . $col . ' / day-' . ($col + $span) . '; grid-row: ' . ($rowNum + 1) . ' / ' . ($rowNum + 2) . ';">'
                            . '<p><span class="event-date">' . date('d.m.Y', $eventStartLocal) . ' - ' . date('d.m.Y', $eventEndLocal) . '<br /></span>'
                            . '<span itemprop="name" class="event-title">' . $eventTitleShort . '</span></p>'
                            . '<meta itemprop="startDate" content="'. date_i18n('c', $eventStart) . '">'
                            . '<meta itemprop="endDate" content="'. date_i18n('c', $eventEnd) . '">'
                            . $locationMeta
                            . '<div role="tooltip" aria-hidden="true">'
                            . '<p style="margin: 0;">' . $thumbnail . '</p>'
                            . '<div class="event-title">' . $eventTitle . '</div>'
                            . '<div class="event-date-time">' . $dateOut . ', ' . $timeOut . '</div>'
                            . '<div itemprop="description" class="event-description">' . $excerpt . ' <a href="' . $eventURL . '">' . __('Read more', 'rrze-calendar') . ' &raquo;</a></div>'
                            . '</div>'
                            . '</div>';
                    }
                }
            }

            // Add empty cells if month ends before weekend
            if ($day == $month_days && $col < 7) {
                for ($i = ($col + 1); $i <= 7; $i++) {
                    $week .= '<div class="empty-day" style="grid-column: day-'.$i.' / day-'.$i.';  grid-row: 1 / 6;" aria-hidden="true"> </div>';
                }
            }

            // Add week to output
            $output .= $week;

            // After 7 days: Increment week counter, reset row counter, line break
            if ($pos == 0) {
                $weekNum++;
                $eventsPerDay = [];
                $output .= '</div><div class="week">';
            }
        }
        $output .= '</div>';

        $output .= '</div>';
        return $output;
    }

    private static function renderSingleEvent($id) {

    }

    public static function ajaxUpdateCalendar() {
        check_ajax_referer( 'rrze-calendar-ajax-nonce' );
        $output = '';
        $periodRaw = sanitize_text_field($_POST['period']);
        $period = explode('-', $periodRaw);
        $layout = sanitize_text_field($_POST['layout']);
        if (count($period) == 3) {
            // day view
            $day = (int)$period[2];
            $month = (int)$period[1];
            $year = (int)$period[0];
            switch ($_POST['direction']) {
                case 'prev':
                    $date = date('Y-m-d', strtotime($periodRaw .' -1 day'));
                    $day = date('d', strtotime($periodRaw .' -1 day'));
                    break;
                case 'next':
                default:
                    $date = date('Y-m-d', strtotime($periodRaw .' +1 day'));
                    $day = date('d', strtotime($periodRaw .' +1 day'));
                    break;
            }
            $startObj = date_create($date);
            $endObj = $startObj;
        } elseif (count($period) == 2) {
            // month view
            $day = '';
            $month = (int)$period[1];
            $year = (int)$period[0];
            switch ($month) {
                case 0:
                    $month = '';
                    // $year = $year;
                    break;
                case 1:
                    $month += ($_POST['direction'] == 'next' ? 1 : 11);
                    $year += ($_POST['direction'] == 'next' ? 0 : -1);
                    break;
                case 12:
                    $month += ($_POST['direction'] == 'next' ? -11 : -1);
                    $year += ($_POST['direction'] == 'next' ? 1 : 0);
                    break;
                default:
                    $month += ($_POST['direction'] == 'next' ? 1 : -1);
                    // $year = $year;
                    break;
            }
            $monthName = strtolower(date("F", mktime(0, 0, 0, $month, 1)));
            $startObj = date_create('first day of ' . $monthName . ' ' . $year);
            $endObj = date_create('last day of ' . $monthName . ' ' . $year);
        } else {
            // year view
            $day = '';
            $month = '';
            $year = (int)$period[0];
            $year += ($_POST['direction'] == 'next' ? 1 : -1);
            $startObj = date_create('first day of january' . $year);
            $endObj = date_create('last day of december ' . $year);
        }
        //$endObj->add(new DateInterval('PT23H59M59S'));
        $endTS = $endObj->getTimestamp();
        $startTS = $startObj->getTimestamp();
        // Get events in calendar period
        $args = [
            'post_type' => CalendarEvent::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'event-lastdate',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'event-lastdate',
                    'value' => $startTS,
                    'compare' => '>='
                ],
            ],
        ];
        $taxQueryBase64 = '';
        if ($_POST['taxquery'] != '') {
            $taxQueryBase64 = sanitize_text_field($_POST['taxquery']);
            $taxQueryJSON = base64_decode($taxQueryBase64);
            $taxQuery = json_decode($taxQueryJSON, true);
        }
        if ($taxQuery) {
            $taxQuery = array_merge(['relation' => 'AND'], $taxQuery);
            $args = array_merge($args, ['tax_query' => $taxQuery]);
        }
        $events = get_posts($args);

        $eventsArray = Utils::buildEventsArray($events, date('Y-m-d', $startTS), (isset($endTS) ? date('Y-m-d', $endTS) : NULL));

        $output .= self::BuildCalendar($year, $month, $day, $eventsArray, $layout, true, $taxQuery);
        echo $output;
        wp_die();
    }
}
