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
    const TIMEOUT_IN_SECONDS = 5;

    protected $ical = null;

    /**
     * Import ical events by iCal URL
     * @param  string  $url iCal URl
     * @param  boolean $skipRecurrence
     * @return array/boolean
     */
    public function importEvents($icalUrl = '', $skipRecurrence = false)
    {
        if ($icalUrl == '') {
            // iCal URL was not provided.'
            return false;
        }

        $icalUrl = str_replace('webcal://', 'http://', $icalUrl);
        $skipRecurrence = (bool) $skipRecurrence ? true : false;

        $icsContent =  $this->getRemoteContent($icalUrl);

        if (false == $icsContent) {
            // Unable to retrieve content from the provided URL.
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

        try {
            $this->ical = new ICal(
                false,
                [
                    'defaultSpan'                 => 2,
                    'defaultTimeZone'             => 'UTC',
                    'defaultWeekStart'            => 'MO',
                    'disableCharacterReplacement' => false,
                    'filterDaysAfter'             => null,
                    'filterDaysBefore'            => null,
                    'replaceWindowsTimeZoneIds'   => true,
                    'skipRecurrence'              => $skipRecurrence
                ]
            );
            $this->ical->initString($icsContent);
        } catch (\Exception $e) {
            return false;
        }

        if ($events = $this->ical->events()) {
            return $events;
        }

        return false;
    }

    /**
     * Load content using wp_remote_get()
     * @param  string $icalUrl [description]
     * @return string/boolean  [description]
     */
    protected function getRemoteContent($icalUrl)
    {
        global $wp_version;
        
        $response = null;

        $args = array(
            'timeout'     => static::TIMEOUT_IN_SECONDS,
            'sslverify'   => false,
            'method'      => 'GET'
        );

        $response = wp_remote_get($icalUrl, $args);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
            // Unable to retrieve content from the provided URL.
            return false;
        }

        return $response['body'];
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
