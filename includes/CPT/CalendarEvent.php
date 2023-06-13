<?php

/* ---------------------------------------------------------------------------
 * Custom Post Type 'calendar_event'
 * ------------------------------------------------------------------------- */

namespace RRZE\Calendar\CPT;

defined('ABSPATH') || exit;

class CalendarEvent
{
    const POST_TYPE = 'calendar_event';

    const TAX_CATEGORY = 'rrze-calendar-category';

    const TAX_TAG = 'rrze-calendar-tag';

    public static function init()
    {
        // Register Post Type.
        add_action('init', [__CLASS__, 'registerPostType']);
        // Register Taxonomies.
        add_action('init', [__CLASS__, 'registerCategory']);
        add_action('init', [__CLASS__, 'registerTag']);
        // CMB2 Init (Metabox)
        //add_action('cmb2_admin_init', [__CLASS__, 'metabox']);
    }

    public static function registerPostType()
    {
        $labels = [
            'name'               => _x('Events', 'post type general name', 'rrze-calendar'),
            'singular_name'      => _x('Event', 'post type singular name', 'rrze-calendar'),
            'menu_name'          => _x('Calendar', 'admin menu', 'rrze-calendar'),
            'name_admin_bar'     => _x('Calendar Event', 'add new on admin bar', 'rrze-calendar'),
            'add_new'            => _x('Add New', 'popup', 'rrze-calendar'),
            'add_new_item'       => __('Add New Event', 'rrze-calendar'),
            'new_item'           => __('New Event', 'rrze-calendar'),
            'edit_item'          => __('Edit Event', 'rrze-calendar'),
            'view_item'          => __('View Event', 'rrze-calendar'),
            'all_items'          => __('All Events', 'rrze-calendar'),
            'search_items'       => __('Search Events', 'rrze-calendar'),
            'parent_item_colon'  => __('Parent Events:', 'rrze-calendar'),
            'not_found'          => __('No events found.', 'rrze-calendar'),
            'not_found_in_trash' => __('No events found in Trash.', 'rrze-calendar')
        ];

        $args = [
            'labels'              => $labels,
            'hierarchical'        => false,
            'public'              => true,
            'has_archive'         => false,
            'supports'            => ['title'],
            'menu_icon'           => 'dashicons-calendar-alt',
            'capability_type'    => Capabilities::getCptCapabilityType(self::POST_TYPE),
            'capabilities'       => (array) Capabilities::getCptCaps(self::POST_TYPE),
            'map_meta_cap'       => Capabilities::getCptMapMetaCap(self::POST_TYPE),
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    public static function registerCategory()
    {
        $labels = [
            'name'              => _x('Event Categories', 'Taxonomy general name', 'rrze-calendar'),
            'singular_name'     => _x('Event Category', 'Taxonomy singular name', 'rrze-calendar')
        ];
        $args = [
            'labels'            => $labels,
            'public'            => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => ['slug' => 'calendars', 'with_front' => false]
        ];
        register_taxonomy(self::TAX_CATEGORY, [self::POST_TYPE, CalendarFeed::POST_TYPE], $args);
    }

    public static function registerTag()
    {
        $labels = [
            'name'              => _x('Event Tags', 'Taxonomy general name', 'rrze-calendar'),
            'singular_name'     => _x('Event Tag', 'Taxonomy singular name', 'rrze-calendar')
        ];
        $args = [
            'labels'            => $labels,
            'public'            => false,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true
        ];
        if (!apply_filters('rrze_calendar_disable_tag', false)) {
            register_taxonomy(self::TAX_TAG, [self::POST_TYPE, CalendarFeed::POST_TYPE], $args);
        }
    }

    public function metabox()
    {
        $cmb = new_cmb2_box([
            'id' => 'calendar_event_metabox',
            'title' => __('Settings', 'rrze-calendar'),
            'object_types' => [self::POST_TYPE],
            'context' => 'normal',
            'priority' => 'high',
            'show_names' => true
        ]);

        $cmb->add_field([
            'name' => __('Expiration', 'rrze-calendar'),
            'id' => 'rrze_notices_expiration',
            'type' => 'text_datetime_timestamp',
            'date_format' => _x('Y/m/d', 'expiration date format', 'rrze-calendar'),
            'time_format' => _x('g:i a', 'expiration time format', 'rrze-calendar'),
            'attributes' => [
                'data-timepicker' => json_encode(
                    [
                        'timeFormat' => 'HH:mm',
                        'stepMinute' => 10
                    ]
                ),
                'required' => 'required'
            ]
        ]);
    }    
}
