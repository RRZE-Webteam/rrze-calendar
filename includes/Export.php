<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\CalendarEvent as Event;
use RRZE\Calendar\CPT\CalendarFeed as Feed;

use Jsvrcek\ICS\Model\Calendar;
use Jsvrcek\ICS\Model\CalendarEvent;
use Jsvrcek\ICS\Model\Relationship\Attendee;
use Jsvrcek\ICS\Model\Relationship\Organizer;

use Jsvrcek\ICS\Utility\Formatter;
use Jsvrcek\ICS\CalendarStream;
use Jsvrcek\ICS\CalendarExport;

class Export
{
    public static function getData(array $args)
    {
        $postIds = $rags['postIds'] ?? null;
        $categories = $rags['categories'] ?? null;
        $tags = $rags['tags'] ?? null;
        $taxQuery = null;

        if (!empty($categories)) {
            $taxQuery = [
                [
                    'taxonomy' => Event::TAX_CATEGORY,
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
                        'taxonomy' => Event::TAX_TAG,
                        'field'    => 'slug',
                        'terms'    => $tags
                    ]
                ]
            );
        }

        $args = [
            'fields'      => 'ids',
            'numberposts' => -1,
            'post_type'   => Feed::POST_TYPE,
            'post_status' => 'publish'
        ];

        if (!empty($taxQuery)) {
            $taxQuery = array_merge(['relation' => 'AND'], $taxQuery);
            $args = array_merge($args, ['tax_query' => $taxQuery]);
        }

        $postIds = get_posts($args);

        return Events::getItemsFromFeedIds($postIds, true);
    }

    // Export::stream(['postIds' => $postIds]);
    public static function stream(array $args)
    {
        $data = self::getData($args);
        $data2 = [];
        foreach ($data as $row) {
            if (isset($row['uid']) && !isset($data2[$row['uid']])) {
                $data2[$row['uid']] = $row;
            }
        }

        // Setup calendar.
        $calendar = new Calendar();
        $calendar->setProdId('-//My Company//Cool Calendar App//EN');

        foreach ($data2 as $row) {
            $event = new CalendarEvent();
            $event->setUid($row['uid'])
                ->setStart(new \DateTime($row['start_date']))
                ->setStart(new \DateTime($row['end_date']))
                ->setSummary($row['title'])
                ->setDescription($row['eventdesc']);

            if ($row['end_date']) {
                $event->setEnd(new \DateTime($row['end_date']));
            }
            if ($row['rrule']) {
                $event->setCustomProperties(['RRULE' => $row['rrule']]);
            }            
            $calendar->addEvent($event);
        }

        //setup exporter
        $calendarExport = new CalendarExport(new CalendarStream, new Formatter());
        $calendarExport->addCalendar($calendar);

        //output .ics formatted text
        //Utils::debug($calendarExport->getStream());
    }
}
