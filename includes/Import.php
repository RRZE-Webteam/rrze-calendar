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
    const TIMEOUT_IN_SECONDS = 7;

    protected $ical = null;

    /**
     * Import ical events by iCal URL
     * @param  string  $url iCal URl
     * @param  boolean $skipRecurrence
     * @return array|boolean
     */
    public function importEvents($icalUrl = '', $skipRecurrence = false)
    {
        if ($icalUrl == '') {
            // The iCal URL was not provided.'
            do_action(
                'rrze.log.error',
                /* translators: {plugin}: Plugin name. */
                __('{plugin}: The iCal URL was not provided.', 'rrze-calendar'),
                ['plugin' => 'rrze-calendar', 'method' => __METHOD__]
            );
            return false;
        }

        $icalUrl = str_replace('webcal://', 'http://', $icalUrl);
        $skipRecurrence = (bool) $skipRecurrence ? true : false;

        // Get ICS file contents
        $icsContent = Transients::getIcalCache($icalUrl);
        if ($icsContent === false) {
            $icsContent = self::urlGetContent($icalUrl);
            if (strpos((string) $icsContent, 'BEGIN:VCALENDAR') === 0) {
                Transients::setIcalCache($icalUrl, $icsContent);
            } else {
                $icsContent = '';
            }
        }

        if (empty($icsContent)) {
            // Unable to retrieve content from the provided iCal URL.
            do_action(
                'rrze.log.error',
                /* translators: {plugin}: Plugin name. */
                __('{plugin}: Unable to retrieve content from the provided iCal URL.', 'rrze-calendar'),
                ['plugin' => 'rrze-calendar', 'method' => __METHOD__, 'icalUrl' => $icalUrl]
            );
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

        $limitdays = 365;
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

        $response = wp_safe_remote_get($url, $args);
        if (wp_remote_retrieve_response_code($response) != 200) {
            return false;
        }
        return $response['body'] ?? false;
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
