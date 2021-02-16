<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\DB;
use RRZE\Calendar\Util;

class Events
{
    public static function getEventsData($outputType = OBJECT)
    {
        global $wpdb;

        return $wpdb->get_results(
            sprintf(
                'SELECT e.*, UNIX_TIMESTAMP(start) AS start, UNIX_TIMESTAMP(end) AS end, f.id AS feed_id, f.title AS feed_title
                FROM %1$s e
                JOIN %2$s f ON e.ical_feed_id = f.id',
                DB::getEventsTableName(),
                DB::getFeedsTableName()
            ),
            $outputType
        );
    }

    public static function getEventBySlug($slug)
    {
        global $wpdb;

        $sql = "SELECT * FROM " . DB::getEventsTableName() . " WHERE slug = %s";
        $event = $wpdb->get_row($wpdb->prepare($sql, $slug), ARRAY_A);

        if (is_null($event)) {
            return null;
        }

        if (!empty($event['recurrence_rules'])) {
            $data['rrules_human_readable'] = self::rrules_human_readable($event['recurrence_rules']);
        }

        $event['category'] = Taxonomies::getCategoryForFeed($event['ical_feed_id']);
        $event['tags'] = Taxonomies::getTagsForFeed($event['ical_feed_id'], 'objects');
        $event['feed'] = Feeds::getFeed($event['ical_feed_id'], ARRAY_A);

        return new Event($event);
    }

    public static function getEventsCount($feed_id)
    {
        global $wpdb;

        return $wpdb->get_var(
            sprintf(
                'SELECT COUNT(*)
                FROM %s
                WHERE ical_feed_id = %d',
                DB::getEventsTableName(),
                absint($feed_id)
            )
        );
    }

    public static function getEventsRelativeTo($date, $limit = 0, $filter = array())
    {
        global $wpdb;

        $limit = absint($limit);

        Taxonomies::getFilterSql($filter);

        $query = $wpdb->prepare(
            "SELECT * " .
                "FROM " . DB::getEventsTableName() . " " .
                "WHERE start >= %s " .
                $filter['filter_where'] .
                "ORDER BY start ASC" . ($limit ? " LIMIT $limit" : ""),
            [$date]
        );

        $events = $wpdb->get_results($query, ARRAY_A);

        foreach ($events as &$event) {
            $event['category'] = Taxonomies::getCategoryForFeed($event['ical_feed_id']);
            $event['tags'] = Taxonomies::getTagsForFeed($event['ical_feed_id'], 'objects');
            $event['feed'] = Feeds::getFeed($event['ical_feed_id'], ARRAY_A);

            $event = new Event($event);
        }

        return $events;
    }

    public static function getEventsBetween($start, $end, $filter)
    {
        global $wpdb;

        Taxonomies::getFilterSql($filter);

        $query = $wpdb->prepare(
            "SELECT * " .
                "FROM " . DB::getEventsTableName() . " " .
                "WHERE start >= %s AND start < %s " .
                $filter['filter_where'] .
                "ORDER BY start ASC",
            [$start, $end]
        );

        $events = $wpdb->get_results($query, ARRAY_A);

        foreach ($events as &$event) {
            $event['category'] = Taxonomies::getCategoryForFeed($event['ical_feed_id']);
            $event['tags'] = Taxonomies::getTagsForFeed($event['ical_feed_id'], 'objects');
            $event['feed'] = Feeds::getFeed($event['ical_feed_id'], ARRAY_A);

            $event = new Event($event);
        }

        return $events;
    }

    public static function getFilterSql(&$filter)
    {
        global $wpdb;

        $filter['filter_where'] = '';

        $whereLogic = 'AND (';

        foreach ($filter as $filterType => $filterIds) {
            if ($filterIds && is_array($filterIds)) {
                switch ($filterType) {
                    case 'feed_ids':
                        $filter['filter_where'] .= $whereLogic . ' e.ical_feed_id IN (' . implode(',', $filterIds) . ') ';
                        $whereLogic = 'OR ';
                        break;
                    case 'event_ids':
                        $filter['filter_where'] .= $whereLogic . ' e.id IN (' . implode(',', $filterIds) . ') ';
                        $whereLogic = 'OR ';
                        break;
                }
            }
        }

        if ($filter['filter_where'] != '') {
            $filter['filter_where'] .= ') ';
        }
    }
}
