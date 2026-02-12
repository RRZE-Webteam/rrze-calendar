<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use DateInterval;
use RRZE\Calendar\ICS\Export;
use RRZE\Calendar\Utils;
use RRZE\Calendar\CPT\CalendarEvent;
use function RRZE\Calendar\plugin;

/**
 * Calendar class
 * @package RRZE\Calendar\Shortcodes
 */
class Calendar
{
    private static bool $didRender = false;

    public static function init()
    {
        add_shortcode('rrze-calendar', [__CLASS__, 'shortcode']);
        add_shortcode('rrze-kalender', [__CLASS__, 'shortcode']);
        add_shortcode('calendar', [__CLASS__, 'shortcode']);
        add_shortcode('kalender', [__CLASS__, 'shortcode']);
        add_action('wp_ajax_rrze-calendar-update-calendar', [__CLASS__, 'ajaxUpdateCalendar']);
        add_action('wp_ajax_nopriv_rrze-calendar-update-calendar', [__CLASS__, 'ajaxUpdateCalendar']);

        add_action('wp_enqueue_scripts', [__CLASS__, 'wpEnqueueScripts']);
        add_action('wp_footer', [__CLASS__, 'maybePrintLateAssets'], 1);
    }

    /**
     * Enqueue scripts and styles for the frontend.
     * 
     * @return void
     */
    public static function wpEnqueueScripts()
    {
        $assetFile = include plugin()->getPath('build') . 'calendar.asset.php';

        wp_register_style(
            'rrze-calendar-sc-calendar',
            plugins_url('build/calendar.style.css', plugin()->getBasename()),
            [],
            $assetFile['version']
        );

        wp_register_script(
            'rrze-calendar-sc-calendar',
            plugins_url('build/calendar.js', plugin()->getBasename()),
            $assetFile['dependencies'],
            $assetFile['version'],
            true
        );

        wp_localize_script('rrze-calendar-sc-calendar', 'rrze_calendar_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('rrze-calendar-ajax-nonce'),
        ]);
    }

    public static function maybePrintLateAssets(): void
    {
        if (!self::$didRender) {
            return;
        }

        // If the theme/head timing is broken, force-print enqueued styles now.
        // This is a pragmatic fallback for themes missing wp_head() / late enqueue.
        wp_print_styles(['rrze-calendar-sc-calendar']);
    }

    public static function shortcode($atts, $content = "")
    {
        self::$didRender = true;

        wp_enqueue_style('rrze-calendar-sc-calendar');
        wp_enqueue_script('rrze-calendar-sc-calendar');

        $tz    = wp_timezone();
        $nowTs = (new \DateTimeImmutable('now', $tz))->getTimestamp();

        $atts = shortcode_atts(
            [
                'categories' => '',
                'kategorien' => '',
                'tags' => '',
                'schlagworte' => '',
                'year' => (int) wp_date('Y', $nowTs, $tz),
                'month' => 'current',
                'day' => '',
                'layout' => 'full',
                'navigation' => '1',
                'abonnement_link' => '',
                'include' => '',
                'exclude' => '',
            ],
            $atts
        );

        // Calendar period UI state
        $buttonDayClass   = 'inactive';
        $buttonMonthClass = 'active';
        $buttonYearClass  = 'inactive';
        $day = '';

        // --- YEAR ---
        if (isset($_GET['cal-year']) && is_numeric($_GET['cal-year'])) {
            $y = (int) $_GET['cal-year'];
            $year = ($y > 2000 && $y < 3000) ? $y : (int) wp_date('Y', $nowTs, $tz);
        } else {
            $y = is_numeric($atts['year']) ? (int) $atts['year'] : 0;
            $year = ($y > 2000 && $y < 3000) ? $y : (int) wp_date('Y', $nowTs, $tz);
        }

        // --- MONTH ---
        if (isset($_GET['cal-month']) && is_numeric($_GET['cal-month'])) {
            $m = (int) $_GET['cal-month'];
            $month = ($m >= 1 && $m <= 12) ? $m : null;
        } else {
            if (is_numeric($atts['month'])) {
                $m = (int) $atts['month'];
                $month = ($m >= 1 && $m <= 12) ? $m : null;
            } elseif (!isset($_GET['cal-year']) && $atts['month'] === 'current') {
                $month = (int) wp_date('m', $nowTs, $tz);
            } else {
                $month = null;
                $atts['layout'] = 'mini';
                $buttonDayClass   = 'inactive';
                $buttonMonthClass = 'inactive';
                $buttonYearClass  = 'active';
            }
        }

        // --- DAY + RANGE ---
        $startTS = 0;
        $endTS   = 0;

        if ($month !== null) {
            if (isset($_GET['cal-day']) && is_numeric($_GET['cal-day'])) {
                $d = (int) $_GET['cal-day'];
                if ($d >= 1 && $d <= 31) {
                    $day = $d;
                    $buttonDayClass   = 'active';
                    $buttonMonthClass = 'inactive';
                    $buttonYearClass  = 'inactive';
                }
            } else {
                if (is_numeric($atts['day'])) {
                    $d = (int) $atts['day'];
                    if ($d >= 1 && $d <= 31) {
                        $day = $d;
                        $buttonDayClass   = 'active';
                        $buttonMonthClass = 'inactive';
                        $buttonYearClass  = 'inactive';
                    }
                } elseif ($atts['day'] === 'heute') {
                    $day = (int) wp_date('d', $nowTs, $tz);
                    $buttonDayClass   = 'active';
                    $buttonMonthClass = 'inactive';
                    $buttonYearClass  = 'inactive';
                }
            }

            if ($day !== '') {
                // Day view: [start, start+1 day)
                $startObj = new \DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, (int) $day), $tz);
                $startTS  = $startObj->getTimestamp();
                $endTS    = $startObj->modify('+1 day')->getTimestamp();
            } else {
                // Month view: first day 00:00:00 to last day 23:59:59
                $startObj = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
                $startTS  = $startObj->getTimestamp();
                $endObj   = $startObj->modify('last day of this month')->setTime(23, 59, 59);
                $endTS    = $endObj->getTimestamp();

                $buttonDayClass   = 'inactive';
                $buttonMonthClass = 'active';
                $buttonYearClass  = 'inactive';
            }
        } else {
            // Year view: Jan 1 00:00:00 to Dec 31 23:59:59
            $startObj = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year), $tz);
            $startTS  = $startObj->getTimestamp();
            $endObj   = new \DateTimeImmutable(sprintf('%04d-12-31 23:59:59', $year), $tz);
            $endTS    = $endObj->getTimestamp();
        }

        // Layout: full or mini
        $layout = ($atts['layout'] === 'full') ? 'full' : 'mini';
        if ($layout === 'full' && $month === null) {
            $month = (int) wp_date('m', $nowTs, $tz);
        }

        // Paging
        $paging = in_array($atts['navigation'], ['1', 'ja', 'true', 'yes'], true);

        // Abonnement-Link
        $aboLink = in_array($atts['abonnement_link'], ['1', 'ja', 'true', 'yes'], true);

        $args = [
            'post_type'   => CalendarEvent::POST_TYPE,
            'numberposts' => -1,
            'meta_query'  => [
                'relation' => 'OR',
                [
                    'key'     => 'event-lastdate',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key'     => 'event-lastdate',
                    'value'   => $startTS,
                    'compare' => '>='
                ],
            ],
        ];

        // Tax queries
        $tax = $atts['categories'] ?: $atts['kategorien'];
        $categories = Utils::strListToArray($tax, 'sanitize_title');

        $tax = $atts['tags'] ?: $atts['schlagworte'];
        $tags = Utils::strListToArray($tax, 'sanitize_title');

        $taxQuery = [];

        if (!empty($categories)) {
            $taxQuery[] = [
                'taxonomy' => CalendarEvent::TAX_CATEGORY,
                'field'    => 'slug',
                'terms'    => $categories,
            ];
        }

        if (!empty($tags)) {
            $taxQuery[] = [
                'taxonomy' => CalendarEvent::TAX_TAG,
                'field'    => 'slug',
                'terms'    => $tags,
            ];
        }

        if (!empty($taxQuery)) {
            $args['tax_query'] = array_merge(['relation' => 'AND'], $taxQuery);
        }

        // Title include/exclude
        $include = sanitize_text_field($atts['include']);
        $exclude = sanitize_text_field($atts['exclude']);
        if ($exclude !== '' || $include !== '') {
            add_filter('posts_where', ['RRZE\Calendar\Utils', 'titleFilter'], 10, 2);
            if ($include !== '') {
                $args['title_filter'] = $include;
            }
            if ($exclude !== '') {
                $args['title_filter_exclude'] = $exclude;
            }
            $args['suppress_filters'] = false;
        }

        $events = get_posts($args);

        if ($exclude !== '' || $include !== '') {
            remove_filter('posts_where', ['RRZE\Calendar\Utils', 'titleFilter']);
        }

        // IMPORTANT: pass date bounds in WP timezone
        $eventsArray = Utils::buildEventsArray(
            $events,
            wp_date('Y-m-d', $startTS, $tz),
            $endTS ? wp_date('Y-m-d', $endTS, $tz) : null
        );

        // Render output
        $output = '<div class="rrze-calendar">';

        if ($layout === 'full' || ($layout === 'mini' && $month === null)) {
            $currentMonth = wp_date('m', $nowTs, $tz);
            $currentDay   = wp_date('d', $nowTs, $tz);

            $output .= '<p class="cal-type-select">'
                . do_shortcode(
                    '[button style="ghost" link="?cal-year=' . (int) $year . '&cal-month=' . esc_attr($currentMonth) . '&cal-day=' . esc_attr($currentDay) . '" class="' . esc_attr($buttonDayClass) . '" title="' . esc_attr(__('View day', 'rrze-calendar')) . '"]' . __('Day', 'rrze-calendar') . '[/button]'
                        . '[button style="ghost" link="?cal-year=' . (int) $year . '&cal-month=' . esc_attr($currentMonth) . '" class="' . esc_attr($buttonMonthClass) . '" title="' . esc_attr(__('View monthly calendar', 'rrze-calendar')) . '"]' . __('Month', 'rrze-calendar') . '[/button]'
                        . '[button style="ghost" link="?cal-year=' . (int) $year . '" class="' . esc_attr($buttonYearClass) . '" title="' . esc_attr(__('View yearly calendar', 'rrze-calendar')) . '"]' . __('Year', 'rrze-calendar') . '[/button]'
                )
                . '</p>';
        }

        $output .= self::buildCalendar(
            (int) $year,
            $month === null ? '' : (int) $month,
            $day,
            $eventsArray,
            $layout,
            $paging,
            $taxQuery,
            $aboLink
        );

        $output .= '</div>';

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
    private static function buildCalendar($year, $month, $day, $eventsArray, $layout = 'full', $paging = true, $taxQuery = [], $aboLink = false): string
    {
        $output = '';

        $year  = (int) $year;
        $month = $month !== '' ? (int) $month : '';
        $day   = $day !== '' ? (int) $day : '';

        if ($day !== '') {
            $output .= '<div class="calendar-wrapper cal-day" data-period="' . esc_attr(sprintf('%04d-%02d-%02d', $year, $month, $day)) . '" data-layout="' . esc_attr($layout === 'full' ? 'full' : 'mini') . '" data-abolink="' . esc_attr($aboLink ? '1' : '0') . '">';
            $output .= self::renderDayList($year, $month, $day, $eventsArray, $taxQuery, $aboLink);
            $output .= '</div>';
            return $output;
        }

        if ($month !== '') {
            $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $output .= '<div class="calendar-wrapper cal-month" data-period="' . esc_attr(sprintf('%04d-%s', $year, $monthStr)) . '" data-layout="' . esc_attr($layout === 'full' ? 'full' : 'mini') . '" data-abolink="' . esc_attr($aboLink ? '1' : '0') . '">';

            if ($layout === 'full') {
                $output .= self::renderMonthCalendarFull($year, $monthStr, $eventsArray, $paging, $taxQuery, $aboLink);
            } else {
                $output .= self::renderMonthCalendarMini($year, $monthStr, $eventsArray, true, $taxQuery);
            }

            $output .= '</div>';
            return $output;
        }

        // Year view
        $output .= '<div class="calendar-wrapper cal-year" data-period="' . esc_attr((string) $year) . '" data-layout="' . esc_attr($layout === 'full' ? 'full' : 'mini') . '" data-abolink="' . esc_attr($aboLink ? '1' : '0') . '">';
        $output .= '<div class="calendar-header"><h2 class="title-year">' . esc_html((string) $year) . '</h2>';

        if ($paging) {
            $output .= '<ul class="calendar-pager">
            <li class="date-prev">
                <a href="#" title="' . esc_attr(__('Go to previous year', 'rrze-calendar')) . '" rel="nofollow" data-direction="prev">« ' . esc_html(__('Previous', 'rrze-calendar')) . '</a>
            </li>
            <li class="date-next">
                <a href="#" title="' . esc_attr(__('Go to next year', 'rrze-calendar')) . '" rel="nofollow" data-direction="next">' . esc_html(__('Next', 'rrze-calendar')) . ' »</a>
            </li>
        </ul>';
        }

        $output .= '</div><div class="calendar-year">';

        for ($i = 1; $i <= 12; $i++) {
            $m = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $output .= self::renderMonthCalendarMini($year, $m, $eventsArray, false, $taxQuery);
        }

        $output .= '</div></div>';

        return $output;
    }

    /**
     * Render list of events on one day
     * 
     * @param integer $year
     * @param integer $month
     * @param integer $day
     * @param array $eventsArray
     * @param array $taxQuery
     * @param bool $aboLink
     * @return string
     */
    private static function renderDayList($year, $month, $day, $eventsArray = [], $taxQuery = [], $aboLink = false)
    {
        $tz = wp_timezone();

        $calDayStr = sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
        $calDayDt  = new \DateTimeImmutable($calDayStr . ' 00:00:00', $tz);
        $calDayTs  = $calDayDt->getTimestamp();

        $output  = '<div class="calendar-header"><h2 class="title-year">'
            . esc_html(wp_date(get_option('date_format'), $calDayTs, $tz))
            . '</h2>';

        $output .= '<ul class="calendar-pager">
        <li class="date-prev">
            <a href="#" title="' . esc_attr(__('Go to previous day', 'rrze-calendar')) . '" rel="nofollow" data-direction="prev">« ' . esc_html(__('Previous', 'rrze-calendar')) . '</a>
        </li>
        <li class="date-next">
            <a href="#" title="' . esc_attr(__('Go to next day', 'rrze-calendar')) . '" rel="nofollow" data-direction="next">' . esc_html(__('Next', 'rrze-calendar')) . ' »</a>
        </li>
    </ul>';
        $output .= '</div>';

        $IDs = [];
        $list = '<ul class="day-list">';
        $hasEvents = false;

        foreach ($eventsArray as $ts => $events) {
            $eventStart = (int) $ts;

            foreach ($events as $event) {
                $eventEnd = (int) ($event['end'] ?? 0);
                if ($eventEnd <= 0) {
                    continue;
                }

                // Compare using WP-local dates (YYYY-mm-dd)
                $eventStartDate = wp_date('Y-m-d', $eventStart, $tz);
                $eventEndDate   = wp_date('Y-m-d', $eventEnd, $tz);

                if ($calDayStr < $eventStartDate || $calDayStr > $eventEndDate) {
                    continue;
                }

                $meta = get_post_meta((int) $event['id']);
                $isAllDay = (Utils::getMeta($meta, 'all-day') === 'on');

                $eventTitle = get_the_title((int) $event['id']);
                $eventURL   = get_the_permalink((int) $event['id']);
                $eventTitleHtml = '<a href="' . esc_url($eventURL) . '">' . esc_html($eventTitle) . '</a>';

                // Date/Time (display in WP TZ)
                if ($eventStartDate === $eventEndDate && !$isAllDay) {
                    $timeText = '<span class="event-date">'
                        . esc_html(wp_date('H:i', $eventStart, $tz) . ' - ' . wp_date('H:i', $eventEnd, $tz))
                        . '</span>';
                } elseif ($eventStartDate === $eventEndDate && $isAllDay) {
                    $timeText = '<span class="event-date">' . esc_html(__('All Day', 'rrze-calendar')) . '</span>';
                } else {
                    $timeText = '<span class="event-date">'
                        . esc_html(wp_date(get_option('date_format'), $eventStart, $tz) . ' - ' . wp_date(get_option('date_format'), $eventEnd, $tz))
                        . '</span>';
                }

                // Location
                $location = (string) Utils::getMeta($meta, 'location');
                $locationText = '';
                if ($location !== '') {
                    $location = Shortcode::filterContent($location);
                    $locationText = '<br />' . $location; // assumes filterContent returns safe HTML
                }

                $list .= '<li>' . $timeText . '<br />' . $eventTitleHtml . $locationText . '</li>';

                $hasEvents = true;
                $IDs[] = (int) $event['id'];
            }
        }

        $list .= '</ul>';

        if ($hasEvents) {
            $output .= $list;
            if ($aboLink) {
                $output .= do_shortcode(
                    '[button link="' . esc_url(Export::makeIcsLink(['ids' => array_values(array_unique($IDs))])) . '"]'
                        . __('Add to calendar', 'rrze-calendar')
                        . '[/button]'
                );
            }
        } else {
            $output .= '<p>' . esc_html(__('There are no events scheduled for this day.', 'rrze-calendar')) . '</p>';
        }

        return $output;
    }

    /**
     * Render mini calendar month view with availability information for external use
     *
     * @param integer $year
     * @param integer $month
     * @param array $eventsArray
     * @param bool $showYear
     * @param array $taxQuery
     * @return string
     */
    private static function renderMonthCalendarMini($year, $month, $eventsArray = [], $showYear = false, $taxQuery = [])
    {
        $tz = wp_timezone();
        $startOfWeek = (int) get_option('start_of_week', 0); // 0=Sunday

        $year  = (int) $year;
        $month = (int) $month;

        $firstOfMonth = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);

        // PHP: N = 1..7 (Mon..Sun). Convert to 0..6 (Sun..Sat) to match WP start_of_week logic
        $weekdayN = (int) $firstOfMonth->format('N'); // 1..7
        $weekday0 = $weekdayN % 7;                    // 0=Sun..6=Sat

        // Shift based on start_of_week (0=Sun..6=Sat)
        $first_day_in_month = ($weekday0 - $startOfWeek + 7) % 7;

        $month_days = (int) $firstOfMonth->format('t');

        $month_names = Utils::getMonthNames('full');
        $month_name = $month_names[$month - 1] ?? (string) $month;

        if ($showYear) {
            $month_name .= ' ' . $year;
            $monthTitle = esc_html($month_name);
        } else {
            $monthTitle = '<a href="?cal-year=' . esc_attr((string) $year) . '&cal-month=' . esc_attr(str_pad((string) $month, 2, '0', STR_PAD_LEFT)) . '">' . esc_html($month_name) . '</a>';
        }

        $output  = '<div class="calendar-month mini">';
        $output .= '<table>';
        $output .= '<tr><th colspan="7">' . $monthTitle . '</th></tr>';
        $output .= '<tr class="days">';

        foreach (Utils::getDaysOfWeek('short') as $dayOfWeek) {
            $output .= '<td>' . esc_html($dayOfWeek) . '</td>';
        }

        $output .= '</tr><tr>';

        for ($i = 0; $i < $first_day_in_month; $i++) {
            $output .= '<td> </td>';
        }

        for ($day = 1; $day <= $month_days; $day++) {
            $pos = ($day + $first_day_in_month) % 7;

            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $class = 'has-no-events';
            $linkOpen = '';
            $linkClose = '';

            // Determine if any event overlaps this date (compare as WP-local dates)
            foreach ($eventsArray as $ts => $events) {
                $eventStart = (int) $ts;

                foreach ($events as $event) {
                    $eventEnd = (int) ($event['end'] ?? 0);
                    if ($eventEnd <= 0) {
                        continue;
                    }

                    $eventStartDate = wp_date('Y-m-d', $eventStart, $tz);
                    $eventEndDate   = wp_date('Y-m-d', $eventEnd, $tz);

                    if ($dateStr >= $eventStartDate && $dateStr <= $eventEndDate) {
                        $linkOpen = '<a href="?cal-year=' . esc_attr((string) $year) . '&cal-month=' . esc_attr(str_pad((string) $month, 2, '0', STR_PAD_LEFT)) . '&cal-day=' . esc_attr(str_pad((string) $day, 2, '0', STR_PAD_LEFT)) . '" title="' . esc_attr(__('View Details', 'rrze-calendar')) . '">';
                        $linkClose = '</a>';
                        $class = 'has-events';
                        break 2;
                    }
                }
            }

            $output .= '<td class="' . esc_attr($class) . '">' . $linkOpen . esc_html((string) $day) . $linkClose . '</td>';

            if ($pos === 0 && $day !== $month_days) {
                $output .= '</tr><tr>';
            }
        }

        $output .= '</tr></table></div>';

        return $output;
    }

    /**
     * Render Full Calendar Month View with event details
     *
     * @param integer $year
     * @param integer $month
     * @param array $eventsArray
     * @param bool $paging
     * @param array $taxQuery
     * @param bool $aboLink
     * @return string
     */
    private static function renderMonthCalendarFull($year, $month, $eventsArray = [], $paging = true, $taxQuery = [], $aboLink = false)
    {
        $tz = wp_timezone();

        $startOfWeek = (int) get_option('start_of_week', 0); // 0=Sunday
        $year  = (int) $year;
        $month = (int) $month;

        $firstOfMonth = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);

        // weekday handling (0=Sun..6=Sat) aligned with start_of_week
        $weekdayN = (int) $firstOfMonth->format('N'); // 1..7 Mon..Sun
        $weekday0 = $weekdayN % 7;                    // 0..6 Sun..Sat
        $first_day_in_month = ($weekday0 - $startOfWeek + 7) % 7;

        $month_days = (int) $firstOfMonth->format('t');

        $month_names = Utils::getMonthNames('full');
        $month_name = $month_names[$month - 1] ?? (string) $month;

        $day_names = Utils::getDaysOfWeek('full');
        $IDs = [];

        $taxQueryBase64 = base64_encode(wp_json_encode($taxQuery));

        $output = '<div class="calendar-header"><h2 class="title-year">' . esc_html($month_name . ' ' . $year) . '</h2>';

        if ($paging) {
            $output .= '<ul class="calendar-pager">
            <li class="date-prev">
                <a href="#" title="' . esc_attr(__('Go to previous month', 'rrze-calendar')) . '" rel="nofollow" data-direction="prev" data-taxquery="' . esc_attr($taxQueryBase64) . '">« ' . esc_html(__('Previous', 'rrze-calendar')) . '</a>
            </li>
            <li class="date-next">
                <a href="#" title="' . esc_attr(__('Go to next month', 'rrze-calendar')) . '" rel="nofollow" data-direction="next" data-taxquery="' . esc_attr($taxQueryBase64) . '">' . esc_html(__('Next', 'rrze-calendar')) . ' »</a>
            </li>
        </ul>';
        }

        $output .= '</div>';
        $output .= '<div class="calendar-month full">';
        $output .= '<div class="days">';

        $gridIndex = 1;
        foreach ($day_names as $day_name) {
            $output .= '<div style="grid-column-start: day-' . $gridIndex . '; grid-column-end: span 1; grid-row-start: date; grid-row-end: span 1;" class="day-names">' . esc_html($day_name) . '</div>';
            $gridIndex++;
        }
        $output .= '</div>';

        $output .= '<div class="week">';

        for ($i = 0; $i < $first_day_in_month; $i++) {
            $gridIndex = $i + 1;
            $output .= '<div class="empty-day" style="grid-column: day-' . $gridIndex . ' / day-' . $gridIndex . ';  grid-row: 1 / 6;" aria-hidden="true"> </div>';
        }

        $rowsOccupied = []; // [YYYY-mm-dd][row] => event_id

        for ($day = 1; $day <= $month_days; $day++) {
            $pos = ($day + $first_day_in_month) % 7;
            $col = ($pos === 0) ? 7 : $pos;

            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $output .= '<div class="day" style="grid-column: day-' . $col . ' / day-' . $col . '; grid-row: 1 / 2;" aria-hidden="true">' . (int) $day . '</div>';

            $week = '';
            $daysLeft = $month_days - $day + 1;
            $showMore = false;

            // Background
            $week .= '<div class="no-event" style="grid-column-start: day-' . $col . '; grid-column-end: span 1; grid-row-start: 2; grid-row-end: 6" aria-hidden="true"> </div>';

            foreach ($eventsArray as $ts => $events) {
                $eventStart = (int) $ts;

                foreach ($events as $event) {
                    $eventId  = (int) ($event['id'] ?? 0);
                    $eventEnd = (int) ($event['end'] ?? 0);
                    if ($eventId <= 0 || $eventEnd <= 0) {
                        continue;
                    }

                    // check max 3 rows for this day
                    if (isset($rowsOccupied[$dateStr]) && count($rowsOccupied[$dateStr]) >= 3) {
                        $showMore = true;
                    }

                    $eventStartDate = wp_date('Y-m-d', $eventStart, $tz);
                    $eventEndDate   = wp_date('Y-m-d', $eventEnd, $tz);

                    if ($dateStr < $eventStartDate || $dateStr > $eventEndDate) {
                        continue;
                    }

                    $meta = get_post_meta($eventId);
                    $isAllDay = (Utils::getMeta($meta, 'all-day') === 'on');

                    $eventTitle = '<a href="' . esc_url(get_the_permalink($eventId)) . '">' . esc_html(get_the_title($eventId)) . '</a>';

                    $categories = get_the_terms($eventId, CalendarEvent::TAX_CATEGORY);
                    $catColor = '';
                    if ($categories && !is_wp_error($categories)) {
                        $catID = $categories[0]->term_id;
                        $catColor = (string) get_term_meta($catID, 'color', true);
                    }
                    if ($catColor === '') {
                        $catColor = 'var(--color-primary-ci-hell, #003366)';
                    }

                    // location meta
                    $locationMeta = '';
                    $location = (string) Utils::getMeta($meta, 'location');
                    if ($location !== '') {
                        $locPlain = trim(strip_tags(Shortcode::filterContent($location)));
                        $locationMeta .= '<meta itemprop="location" content="' . esc_attr($locPlain) . '" />';
                    }
                    $vc_url = (string) Utils::getMeta($meta, 'vc-url');
                    if ($vc_url !== '') {
                        $locationMeta .= '<span itemprop="location" itemscope itemtype="https://schema.org/VirtualLocation"><meta itemprop="url" content="' . esc_url($vc_url) . '" /></span>';
                    }

                    $eventClasses = ['event'];

                    // --- STARTS TODAY ---
                    if ($dateStr === $eventStartDate) {
                        $eventClasses[] = 'event-start';
                        $eventClasses[] = 'event-end';

                        $span = (int) floor(($eventEnd - $eventStart) / DAY_IN_SECONDS + 1);
                        if ($span < 1) $span = 1;

                        $dateClasses = ['event-date'];
                        if ($span > 1 || $isAllDay) {
                            $timeOut = '';
                        } else {
                            $dateClasses[] = 'hide-desktop';
                            $timeOut = '<span class="event-time">' . esc_html(wp_date('H:i', $eventStart, $tz) . ' - ' . wp_date('H:i', $eventEnd, $tz)) . '<br /></span>';
                        }

                        if ($isAllDay && $eventStartDate === $eventEndDate) {
                            $dateClasses[] = 'hide-desktop';
                            $timeOut = '<span class="event-time">' . esc_html(__('All Day', 'rrze-calendar')) . '<br /></span>';
                        }

                        // trim to week
                        if (($col + $span) > 8) {
                            $span = 8 - $col + 1;
                            $eventClasses = array_values(array_diff($eventClasses, ['event-end']));
                        }

                        // trim to month
                        if ($span > $daysLeft) {
                            $span = $daysLeft;
                            $eventClasses = array_values(array_diff($eventClasses, ['event-end']));
                        }

                        // choose a free row for THIS date (fix rowsOccupied check)
                        $rowNum = 1;
                        for ($r = 1; $r <= 3; $r++) {
                            if (!isset($rowsOccupied[$dateStr][$r])) {
                                $rowNum = $r;

                                // occupy rows for each day in span
                                $startDayOfMonth = (int) substr($dateStr, 8, 2);
                                for ($j = 0; $j < $span; $j++) {
                                    $countDay = $startDayOfMonth + $j;
                                    $countDate = sprintf('%04d-%02d-%02d', $year, $month, $countDay);
                                    $rowsOccupied[$countDate][$r] = $eventId;
                                }
                                break;
                            }
                        }

                        if ($showMore) {
                            $week .= '<div class="more-events" style="grid-column: day-' . $col . ' / day-' . ($col + 1) . '; grid-row: 5 / 6;">'
                                . '<a href="?cal-year=' . (int) $year . '&cal-month=' . esc_attr(str_pad((string) $month, 2, '0', STR_PAD_LEFT)) . '&cal-day=' . esc_attr(str_pad((string) $day, 2, '0', STR_PAD_LEFT)) . '">'
                                . esc_html(__('More&hellip;', 'rrze-calendar'))
                                . '</a></div>';
                            continue 2;
                        }

                        $dateOut = ($eventStartDate === $eventEndDate)
                            ? wp_date('d.m.Y', $eventStart, $tz)
                            : wp_date('d.m.Y', $eventStart, $tz) . ' - ' . wp_date('d.m.Y', $eventEnd, $tz);

                        $thumbnail = get_the_post_thumbnail($eventId, 'medium');
                        $content   = (string) get_post_meta($eventId, 'description', true);
                        $excerpt   = strip_tags(do_shortcode($content));
                        if (strlen($excerpt) > 100) {
                            $excerpt = substr($excerpt, 0, 100);
                            $excerpt = '<span>' . esc_html(substr($excerpt, 0, (int) strrpos($excerpt, ' '))) . '&hellip;</span>';
                        } else {
                            $excerpt = esc_html($excerpt);
                        }

                        $week .= '<div itemtype="https://schema.org/Event" itemscope class="' . esc_attr(implode(' ', $eventClasses)) . '" style="grid-column: day-' . $col . ' / day-' . ($col + $span) . '; grid-row: ' . ($rowNum + 1) . ' / ' . ($rowNum + 2) . '; border-color: ' . esc_attr($catColor) . ';">'
                            . '<p><span class="' . esc_attr(implode(' ', $dateClasses)) . '">' . esc_html($dateOut) . '<br /></span>'
                            . $timeOut
                            . '<span itemprop="name" class="event-title">' . $eventTitle . '</span></p>'
                            . '<meta itemprop="startDate" content="' . esc_attr(wp_date('c', $eventStart, $tz)) . '">'
                            . '<meta itemprop="endDate" content="' . esc_attr(wp_date('c', $eventEnd, $tz)) . '">'
                            . $locationMeta
                            . '<div role="tooltip" aria-hidden="true">'
                            . ($thumbnail ? '<p style="margin: 0;">' . $thumbnail . '</p>' : '')
                            . '<div class="event-title">' . $eventTitle . '</div>'
                            . '<div class="event-date-time">' . esc_html($dateOut) . ', ' . $timeOut . '</div>'
                            . '<div itemprop="description" class="event-description">' . $excerpt . ' <a href="' . esc_url(get_the_permalink($eventId)) . '">' . esc_html(__('Read more', 'rrze-calendar')) . ' &raquo;</a></div>'
                            . '</div>'
                            . '</div>';

                        $IDs[] = $eventId;
                    }

                    // --- CONTINUES from past week/month ---
                    elseif (($col === 1 || $day === 1) && $dateStr > $eventStartDate && $dateStr <= $eventEndDate) {
                        // build local midnight timestamp for this date
                        $calMidTs = (new \DateTimeImmutable($dateStr . ' 00:00:00', $tz))->getTimestamp();

                        if ((($eventEnd - $calMidTs) / DAY_IN_SECONDS) < 0.25) {
                            continue;
                        }

                        $span = (int) floor(($eventEnd - $calMidTs) / DAY_IN_SECONDS + 1);
                        if ($span > 7) {
                            $span = 7;
                            $eventClasses[] = 'event-week';
                        } else {
                            $eventClasses[] = 'event-end';
                        }
                        if ($span > $daysLeft) {
                            $span = max(1, $daysLeft - 1);
                        }

                        $rowNum = 1;
                        for ($r = 1; $r <= 3; $r++) {
                            if (!isset($rowsOccupied[$dateStr][$r])) {
                                $rowNum = $r;

                                $startDayOfMonth = (int) substr($dateStr, 8, 2);
                                for ($j = 0; $j < $span; $j++) {
                                    $countDay = $startDayOfMonth + $j;
                                    $countDate = sprintf('%04d-%02d-%02d', $year, $month, $countDay);
                                    $rowsOccupied[$countDate][$r] = $eventId;
                                }
                                break;
                            }
                        }

                        $dateOut = ($eventStartDate === $eventEndDate)
                            ? wp_date('d.m.Y', $eventStart, $tz)
                            : wp_date('d.m.Y', $eventStart, $tz) . ' - ' . wp_date('d.m.Y', $eventEnd, $tz);

                        $timeOut = '<span class="event-time">' . esc_html(wp_date('H:i', $eventStart, $tz) . ' - ' . wp_date('H:i', $eventEnd, $tz)) . '<br /></span>';

                        $thumbnail = get_the_post_thumbnail($eventId, 'medium');
                        $content   = (string) get_post_meta($eventId, 'description', true);
                        $excerpt   = strip_tags($content);
                        if (strlen($excerpt) > 100) {
                            $excerpt = substr($excerpt, 0, 100);
                            $excerpt = '<span>' . esc_html(substr($excerpt, 0, (int) strrpos($excerpt, ' '))) . '&hellip;</span>';
                        } else {
                            $excerpt = esc_html($excerpt);
                        }

                        $week .= '<div itemtype="https://schema.org/Event" itemscope class="' . esc_attr(implode(' ', $eventClasses)) . '" style="grid-column: day-' . $col . ' / day-' . ($col + $span) . '; grid-row: ' . ($rowNum + 1) . ' / ' . ($rowNum + 2) . '; border-color: ' . esc_attr($catColor) . ';">'
                            . '<p><span class="event-date">' . esc_html($dateOut) . '<br /></span>'
                            . '<span itemprop="name" class="event-title">' . $eventTitle . '</span></p>'
                            . '<meta itemprop="startDate" content="' . esc_attr(wp_date('c', $eventStart, $tz)) . '">'
                            . '<meta itemprop="endDate" content="' . esc_attr(wp_date('c', $eventEnd, $tz)) . '">'
                            . $locationMeta
                            . '<div role="tooltip" aria-hidden="true">'
                            . ($thumbnail ? '<p style="margin: 0;">' . $thumbnail . '</p>' : '')
                            . '<div class="event-title">' . $eventTitle . '</div>'
                            . '<div class="event-date-time">' . esc_html($dateOut) . ', ' . $timeOut . '</div>'
                            . '<div itemprop="description" class="event-description">' . $excerpt . ' <a href="' . esc_url(get_the_permalink($eventId)) . '">' . esc_html(__('Read more', 'rrze-calendar')) . ' &raquo;</a></div>'
                            . '</div>'
                            . '</div>';

                        $IDs[] = $eventId;
                    }
                }
            }

            // End-of-month empty cells (same)
            if ($day === $month_days && $col < 7) {
                for ($i = ($col + 1); $i <= 7; $i++) {
                    $week .= '<div class="empty-day" style="grid-column: day-' . $i . ' / day-' . $i . ';  grid-row: 1 / 6;" aria-hidden="true"> </div>';
                }
            }

            $output .= $week;

            if ($pos === 0) {
                $rowsOccupied = []; // reset for next week, as in your original
                $output .= '</div><div class="week">';
            }
        }

        $output .= '</div>';

        if ($aboLink) {
            $output .= do_shortcode('[button link="' . esc_url(Export::makeIcsLink(['ids' => array_values(array_unique($IDs))])) . '"]' . __('Add to calendar', 'rrze-calendar') . '[/button]');
        }

        $output .= '</div>';

        return $output;
    }

    private static function renderSingleEvent($id) {}

    public static function ajaxUpdateCalendar()
    {
        check_ajax_referer('rrze-calendar-ajax-nonce');

        $tz = wp_timezone();

        $periodRaw = sanitize_text_field($_POST['period'] ?? '');
        $period = explode('-', $periodRaw);

        $layout = sanitize_text_field($_POST['layout'] ?? 'full');
        $aboLink = ((int) ($_POST['abolink'] ?? 0)) === 1;

        $direction = sanitize_text_field($_POST['direction'] ?? 'next');

        $year = 0;
        $month = '';
        $day = '';

        if (count($period) === 3) {
            // day view
            $base = new \DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', (int)$period[0], (int)$period[1], (int)$period[2]), $tz);
            $base = $base->modify($direction === 'prev' ? '-1 day' : '+1 day');

            $year  = (int) $base->format('Y');
            $month = (int) $base->format('m');
            $day   = (int) $base->format('d');

            $startObj = $base;
            $endObj   = $base->modify('+1 day')->modify('-1 second'); // end of day

        } elseif (count($period) === 2) {
            // month view
            $base = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', (int)$period[0], (int)$period[1]), $tz);
            $base = $base->modify($direction === 'prev' ? '-1 month' : '+1 month');

            $year  = (int) $base->format('Y');
            $month = (int) $base->format('m');
            $day   = '';

            $startObj = $base;
            $endObj   = $base->modify('last day of this month')->setTime(23, 59, 59);
        } else {
            // year view
            $baseYear = (int) ($period[0] ?? 0);
            $year = $baseYear + ($direction === 'prev' ? -1 : 1);

            $month = '';
            $day   = '';

            $startObj = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year), $tz);
            $endObj   = new \DateTimeImmutable(sprintf('%04d-12-31 23:59:59', $year), $tz);
        }

        $startTS = $startObj->getTimestamp();
        $endTS   = $endObj->getTimestamp();

        $args = [
            'post_type'      => CalendarEvent::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'event-lastdate',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'event-lastdate',
                    'value'   => $startTS,
                    'compare' => '>=',
                ],
            ],
        ];

        $taxQuery = [];
        $taxQueryBase64 = sanitize_text_field($_POST['taxquery'] ?? '');
        if ($taxQueryBase64 !== '') {
            $taxQueryJSON = base64_decode($taxQueryBase64, true);
            $decoded = $taxQueryJSON ? json_decode($taxQueryJSON, true) : null;
            if (is_array($decoded)) {
                $taxQuery = $decoded;
            }
        }

        if (!empty($taxQuery)) {
            $args['tax_query'] = array_merge(['relation' => 'AND'], $taxQuery);
        }

        $events = get_posts($args);

        $eventsArray = Utils::buildEventsArray(
            $events,
            wp_date('Y-m-d', $startTS, $tz),
            wp_date('Y-m-d', $endTS, $tz)
        );

        $output = self::buildCalendar($year, $month === '' ? '' : (int)$month, $day === '' ? '' : (int)$day, $eventsArray, $layout, true, $taxQuery, $aboLink);

        echo $output;
        wp_die();
    }
}
