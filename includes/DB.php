<?php

namespace FAU\Calendar;

defined('ABSPATH') || exit;

class DB
{
    /**
     * Feeds table name.
     *
     * @var string
     */
    const FEEDS_TABLE_NAME = 'fau_calendar_feeds';

    /**
     * Events table name.
     *
     * @var string
     */
    const EVENTS_TABLE_NAME = 'fau_calendar_events';

    /**
     * Events cache table name.
     *
     * @var string
     */
    const EVENTS_CACHE_TABLE_NAME = 'fau_calendar_events_cache';

    /**
     * Create db tables.
     *
     * @return void
     */
    public static function createDbTables()
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charsetCollate = $wpdb->get_charset_collate();

        dbDelta(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %1$s (
                id bigint(20) unsigned NOT NULL auto_increment,
                url varchar(255)  NOT NULL default \'\',
                title varchar(255)  NOT NULL default \'\',
                active tinyint(1) NOT NULL default 0,
                created datetime NOT NULL default \'0000-00-00 00:00:00\',
                modified datetime NOT NULL default \'0000-00-00 00:00:00\',
                PRIMARY KEY  (id),
                KEY url (url(191))) %2$s;',
                $wpdb->prefix . static::FEEDS_TABLE_NAME,
                $charsetCollate
            )
        );

        dbDelta(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %1$s (
                id bigint(20) unsigned NOT NULL auto_increment,
                start datetime NOT NULL default \'0000-00-00 00:00:00\',
                end datetime,
                allday tinyint(1) NOT NULL,
                recurrence_rules longtext,
                exception_rules longtext,
                recurrence_dates longtext,
                exception_dates longtext,
                summary longtext,
                description longtext,
                location varchar(255),
                slug varchar(200) NOT NULL default \'\',
                ical_feed_id bigint(20) unsigned NOT NULL default 0,
                ical_feed_url varchar(255),
                ical_uid varchar(255),
                ical_source_url varchar(255),
                PRIMARY KEY  (id),
                KEY slug (slug(191)),
                KEY ical_feed_id (ical_feed_id),
                KEY ical_uid (ical_uid)) %2$s;',
                $wpdb->prefix . static::EVENTS_TABLE_NAME,
                $charsetCollate
            )
        );

        dbDelta(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %1$s (
                id bigint(20) unsigned NOT NULL auto_increment,
                event_id bigint(20) NOT NULL default 0,
                start datetime NOT NULL default \'0000-00-00 00:00:00\',
                end datetime NOT NULL default \'0000-00-00 00:00:00\',
                ical_feed_id bigint(20) unsigned NOT NULL default 0,
                PRIMARY KEY  (id),
                KEY ical_feed_id (ical_feed_id)) %2$s;',
                $wpdb->prefix . static::EVENTS_CACHE_TABLE_NAME,
                $charsetCollate
            )
        );
    }

    /**
     * Flush cache.
     *
     * @return void
     */
    public static function flushCache()
    {
        global $wpdb;

        $wpdb->query(
            sprintf(
                'DELETE e FROM %1$s e
                LEFT JOIN %2$s f ON e.ical_feed_id = f.id
                WHERE f.id IS NULL',
                static::EVENTS_TABLE_NAME,
                static::FEEDS_TABLE_NAME
            )
        );

        $wpdb->query(
            sprintf(
                'DELETE ec FROM %1$s ec
                LEFT JOIN %2$s f ON ec.ical_feed_id = f.id
                WHERE f.id IS NULL',
                static::EVENTS_CACHE_TABLE_NAME,
                static::FEEDS_TABLE_NAME
            )
        );

        // rrze-cache plugin
        if (has_action('rrzecache_flush_cache')) {
            do_action('rrzecache_flush_cache');
        }
    }

    /**
     * Truncate db tables.
     *
     * @return void
     */
    public static function truncateDbTables()
    {
        global $wpdb;

        $wpdb->query(
            sprintf(
                'TRUNCATE TABLE %s;',
                $wpdb->prefix . static::FEEDS_TABLE_NAME
            )
        );

        $wpdb->query(
            sprintf(
                'TRUNCATE TABLE %s;',
                $wpdb->prefix . static::EVENTS_TABLE_NAME
            )
        );

        $wpdb->query(
            sprintf(
                'TRUNCATE TABLE %s;',
                $wpdb->prefix . static::EVENTS_CACHE_TABLE_NAME
            )
        );
    }

    /**
     * Drop all tables.
     *
     * @return void
     */
    public static function dropDbTables()
    {
        global $wpdb;

        $wpdb->query(
            sprintf(
                'DROP TABLE IF EXISTS %s;',
                $wpdb->prefix . static::FEEDS_TABLE_NAME
            )
        );

        $wpdb->query(
            sprintf(
                'DROP TABLE IF EXISTS %s;',
                $wpdb->prefix . static::EVENTS_TABLE_NAME
            )
        );

        $wpdb->query(
            sprintf(
                'DROP TABLE IF EXISTS %s;',
                $wpdb->prefix . static::EVENTS_CACHE_TABLE_NAME
            )
        );
    }

    /**
     * Truncate the events table.
     *
     * @return void
     */
    public static function truncateEventsTable()
    {
        global $wpdb;

        $wpdb->query(
            sprintf(
                'TRUNCATE TABLE %s;',
                $wpdb->prefix . static::EVENTS_TABLE_NAME
            )
        );
    }

    /**
     * Truncate the events cache table.
     *
     * @return void
     */
    public static function truncateEventsCacheTable()
    {
        global $wpdb;

        $wpdb->query(
            sprintf(
                'TRUNCATE TABLE %s;',
                $wpdb->prefix . static::EVENTS_CACHE_TABLE_NAME
            )
        );
    }

    /**
     * Get the feeds table name.
     *
     * @return string The feeds table name.
     */
    public static function getFeedsTableName(): string
    {
        return static::FEEDS_TABLE_NAME;
    }

    /**
     * Get the events table name.
     *
     * @return string The events table name.
     */
    public static function getEventsTableName(): string
    {
        return static::EVENTS_TABLE_NAME;
    }

    /**
     * Get the events cache table name.
     *
     * @return string The events cache table name.
     */
    public static function getEventsCacheTableName(): string
    {
        return static::EVENTS_CACHE_TABLE_NAME;
    }

    /**
     * Get a feed record.
     *
     * @param  integer $feedId [description]
     * @return object|null [description]
     */
    public static function getFeed($feedId = 0)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $wpdb->prefix . static::FEEDS_TABLE_NAME . ' WHERE id = %d',
                $feedId
            )
        );
    }

    /**
     * Get all feeds records.
     *
     * @param  string $outputType Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants. Default value: OBJECT.
     * @return array|object|null All feeds records, or null on failure.
     */
    public static function getFeeds($outputType = OBJECT)
    {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT * FROM ' . $wpdb->prefix . static::FEEDS_TABLE_NAME,
            $outputType
        );
    }

    /**
     * Get events records count.
     *
     * @param integer $feedId
     * @return string|null Feed count (as string), or null on failure.
     */
    public static function getEventsCount($feedId = 0)
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $wpdb->prefix . static::EVENTS_TABLE_NAME . ' WHERE ical_feed_id = %d',
                absint($feedId)
            )
        );
    }
}
