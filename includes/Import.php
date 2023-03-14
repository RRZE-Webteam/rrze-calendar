<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use ICal\ICal;

class Import
{
    /**
     * [protected description]
     * @var integer
     */
    const TIMEOUT_IN_SECONDS = 14;

    protected $ical = null;

    const FEEDS_TABLE = 'rrze_calendar_feeds';
    const EVENTS_TABLE = 'rrze_calendar_events';

    /**
     * Import ical events by iCal URL
     * @param  string  $url iCal URl
     * @param  boolean $skipRecurrence
     * @return array|boolean
     */
    public function importEvents($url = '', $skipRecurrence = false)
    {
        if ($url == '') {
            // The iCal URL was not provided.'
            do_action(
                'rrze.log.error',
                '{plugin}: The iCal URL was not provided.',
                ['plugin' => 'rrze-calendar', 'method' => __METHOD__]
            );
            return false;
        }

        $icsUrl = str_replace('webcal://', 'http://', $url);
        $skipRecurrence = (bool) $skipRecurrence ? true : false;

        // Get ICS file contents
        $cache = true;
        $icsContent = Transients::getIcalCache($icsUrl);

        if ($icsContent === false) {
            $cache = false;
            $icsContent = self::urlGetContent($icsUrl);
            if (strpos((string) $icsContent, 'BEGIN:VCALENDAR') === 0) {
                Transients::setIcalCache($icsUrl, $icsContent);
            } else {
                $icsContent = '';
            }
        }

        if (!$cache) {
            $currentUser = wp_get_current_user();
            $UserLogin = $currentUser->user_login ?? '';
            $context = ['plugin' => 'rrze-calendar', 'method' => __METHOD__, 'icsUrl' => $url];
            if ($UserLogin) {
                $message = '{plugin}: FRMBU - Feed request made by a user.';
                $context = array_merge($context, ['user' => $UserLogin]);
            } else {
                $message = '{plugin}: FRMBSE - Feed request made by a scheduled event.';
            }
            do_action(
                'rrze.log.info',
                $message,
                $context
            );
        }

        if (empty($icsContent)) {
            // Unable to retrieve content from the provided iCal URL.
            do_action(
                'rrze.log.error',
                '{plugin}: Unable to retrieve content from the provided iCal URL.',
                ['plugin' => 'rrze-calendar', 'method' => __METHOD__, 'icsUrl' => $url]
            );
            if (!$UserLogin) {
                //self::deactivateFeed($url);
            }
            return false;
        }

        return $this->importEventsFromIcsContent($icsContent, $skipRecurrence);
    }

    /**
     * [importEventsFromIcsContent description]
     * @param  string  $icsContent [description]
     * @param  boolean $skipRecurrence
     * @return array|boolean      [description]
     */
    protected function importEventsFromIcsContent($icsContent, $skipRecurrence)
    {
        $memoryLimit = (int) str_replace('M', '', ini_get('memory_limit'));
        if ($memoryLimit < 512) {
            ini_set('memory_limit', '512M');
        }

        $startdate = 0;
        $pastdays = 0;
        $limitdays = 365;

        if ((!empty($startdate) && intval($startdate) > 20000000)) {
            $rangeStart = date(
                'Y/m/d',
                gmmktime(
                    0,
                    0,
                    0,
                    substr($startdate, 4, 2),
                    substr($startdate, 6, 2) - 1,
                    substr($startdate, 0, 4)
                )
            );
            $rangeEnd = date(
                'Y/m/d',
                gmmktime(
                    0,
                    0,
                    0,
                    substr($startdate, 4, 2),
                    substr($startdate, 6, 2) + $limitdays + 7,
                    substr($startdate, 0, 4)
                )
            );
        } else {
            if (!empty($pastdays)) {
                // Rolling "past days" start (from beginning of current month)
                $rangeStart = date('Y/m/d', gmmktime(0, 0, 0, date('n'), 0 - $pastdays, date('Y')));
            } else {
                // Middle of last month (to cover week view's "last week" option)
                $rangeStart = date('Y/m/d', gmmktime(0, 0, 0, date('n'), -15, date('Y')));
            }
            // Extend by "limitdays" + one week past current date
            $rangeEnd = date('Y/m/d', gmmktime(0, 0, 0, date('n'), date('j') + $limitdays + 7, date('Y')));
        }

        $dateTimeNow = new \DateTime();
        $filterDaysAfter = $dateTimeNow->diff(new \DateTime($rangeEnd))->format('%a');
        $filterDaysBefore = $dateTimeNow->diff(new \DateTime($rangeStart))->format('%a');

        $skipRecurrence = false;
        $tzid = get_option('timezone_string');
        $tz = Util::isValidTimezoneID($tzid) ? timezone_open($tzid) : wp_timezone();

        try {
            $this->ical = new ICal(
                false,
                [
                    'defaultSpan'                 => absint($limitdays) ? intval(ceil(absint($limitdays) / 365)) : 1,
                    'defaultTimeZone'             => $tz->getName(),
                    'defaultWeekStart'            => 'MO',
                    'disableCharacterReplacement' => true,
                    'filterDaysAfter'             => $filterDaysAfter,
                    'filterDaysBefore'            => $filterDaysBefore,
                    'replaceWindowsTimeZoneIds'   => true,
                    'skipRecurrence'              => $skipRecurrence
                ]
            );
            $this->ical->initString($icsContent);
        } catch (\Exception $exception) {
            do_action('rrze.log.error', ['exception' => $exception]);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw $exception;
            } else {
                return false;
            }
        }

        if ($events = $this->ical->events()) {
            return $events;
        }

        return false;
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

        $request = wp_safe_remote_get($url, $args);
        if (wp_remote_retrieve_response_code($request) != 200) {
            return false;
        }
        return $request['body'] ?? false;
    }

    protected static function getFeed($url)
    {
        global $wpdb;
        $sql = "SELECT * FROM " . $wpdb->prefix . self::FEEDS_TABLE . " WHERE url = %s";
        return $wpdb->get_row($wpdb->prepare($sql, $url), 'OBJECT');
    }

    protected function deactivateFeed($url)
    {
        global $wpdb;
        $feed = self::getFeed($url);
        if (is_object($feed)) {
            $wpdb->update($wpdb->prefix . self::FEEDS_TABLE, ['active' => 0], ['id' => $feed->id], ['%d']);
            $wpdb->delete($wpdb->prefix . self::EVENTS_TABLE, ['ical_feed_id' => $feed->id], ['%d']);
            $wpdb->query("DELETE e FROM " . $wpdb->prefix . self::EVENTS_TABLE . " e LEFT JOIN " . $wpdb->prefix . self::FEEDS_TABLE . " f ON e.ical_feed_id = f.id WHERE f.id IS NULL");
        }
    }

    public function iCalDateToUnixTimestamp($icalDate)
    {
        return $this->ical->iCalDateToUnixTimestamp($icalDate);
    }

    public function timeZoneStringToDateTimeZone($timeZoneString)
    {
        return $this->ical->timeZoneStringToDateTimeZone($timeZoneString);
    }
}
