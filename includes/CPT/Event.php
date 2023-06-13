<?php

/* ---------------------------------------------------------------------------
 * Custom Post Type 'calendar_feed'
 * ------------------------------------------------------------------------- */

namespace RRZE\Calendar\CPT;

defined('ABSPATH') || exit;

require_once WP_CONTENT_DIR . '/plugins/rrze-calendar/vendor/cmb2/cmb2/init.php';

use RRZE\Calendar\Utils;

class Event {

    const POST_TYPE = 'event';

    public static function init() {
        // Register Post Type.
        add_action( 'init', [__CLASS__, 'registerPostType'] );
        // CMB2 Fields
        add_action( 'cmb2_admin_init', [__CLASS__, 'eventFields'] );
        add_filter( 'cmb2_render_select_weekdayofmonth', [__CLASS__, 'renderMonthDayField'], 10, 5 );
        add_filter( 'cmb2_render_event_items', [__CLASS__, 'renderEventItemsField'], 10, 5 );
        // Update Feed Items.
        add_action( 'save_post', [__CLASS__, 'save'], 10, 2 );
        add_action( 'updated_post_meta', [__CLASS__, 'updatedMeta'], 10, 4) ;

    }

    public static function registerPostType() {
        $labels = [
            'name' => _x('Events', 'Post type general name', 'rrze-calendar'),
            'singular_name' => _x('Event', 'Post type singular name', 'rrze-calendar'),
            'menu_name' => _x('Events', 'Admin Menu text', 'rrze-calendar'),
            'name_admin_bar' => _x('Event', 'Add New on Toolbar', 'rrze-calendar'),
            'add_new' => __('Add New Event', 'rrze-calendar'),
            'add_new_item' => __('Add New Event', 'rrze-calendar'),
            'new_item' => __('New Event', 'rrze-calendar'),
            'edit_item' => __('Edit Event', 'rrze-calendar'),
            'view_item' => __('View Event', 'rrze-calendar'),
            'all_items' => __('All Events', 'rrze-calendar'),
            'search_items' => __('Search Events', 'rrze-calendar'),
            'not_found' => __('No Events found.', 'rrze-calendar'),
            'not_found_in_trash' => __('No Events found in Trash.', 'rrze-calendar'),
            'featured_image' => _x('Event Logo', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'rrze-calendar'),
            'set_featured_image' => _x('Set event logo', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'rrze-calendar'),
            'remove_featured_image' => _x('Remove event logo', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'rrze-calendar'),
            'use_featured_image' => _x('Use as event logo', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'rrze-calendar'),
            'archives' => _x('Event archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'rrze-calendar'),
            'insert_into_item' => _x('Insert into event', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'rrze-calendar'),
            'uploaded_to_this_item' => _x('Uploaded to this event', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'rrze-calendar'),
            'filter_items_list' => _x('Filter events list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'rrze-calendar'),
            'items_list_navigation' => _x('Events list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'rrze-calendar'),
            'items_list' => _x('Events list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'rrze-calendar'),
        ];

        //$capabilities = CPT::makeCapabilities('event', 'events');
        $args = [
            'label' => __('Event', 'rrze-calendar'),
            'description' => __('Add and edit event informations', 'rrze-calendar'),
            'labels' => $labels,
            'supports' => ['title', 'author', 'excerpt', 'thumbnail'],
            'hierarchical' => TRUE,
            'public' => TRUE,
            'show_ui' => TRUE,
            //'show_in_menu'              => 'edit.php?post_type=event',
            'show_in_menu' => TRUE,
            'show_in_nav_menus' => TRUE,
            'show_in_admin_bar' => TRUE,
            'menu_icon' => 'dashicons-calendar-alt',
            'can_export' => TRUE,
            'has_archive' => TRUE,
            'exclude_from_search' => TRUE,
            'publicly_queryable' => TRUE,
            'delete_with_user' => FALSE,
            'show_in_rest' => FALSE,
            //'capabilities'              => $capabilities,
            'capability_type' => 'post',
            'map_meta_cap' => TRUE,
            'rewrite' => ['slug' => 'event']
        ];
        register_post_type(self::POST_TYPE, $args);
    }

    public static function eventFields() {

        global $wp_locale;

        // General Information
        $cmb_info = new_cmb2_box([
            'id' => 'my-event-calendar-event-info',
            'title' => __('General Information', 'rrze-calendar'),
            'object_types' => ['event'],
            'context' => 'normal',
            'priority' => 'high',
            'show_names' => true,
        ]);
        $cmb_info->add_field( array(
            'name'    => esc_html__( 'Description', 'rrze-calendar' ),
            //'desc'    => __('', 'rrze-calendar'),
            'id'      => 'description',
            'type'    => 'wysiwyg',
            'options' => array(
                'textarea_rows' => get_option('default_post_edit_rows', 12),
            ),
        ) );
        $cmb_info->add_field([
            'name' => __('Location', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'location',
            'type' => 'wysiwyg',
            'options' => array(
                'teeny' => true,
                'textarea_rows' => get_option('default_post_edit_rows', 5),
                'media_buttons' => false,
            ),
        ]);
        $cmb_info->add_field( array(
            'name' => esc_html__( 'VC URL', 'rrze-calendar' ),
            //'desc' => esc_html__( '', 'rrze-calendar' ),
            'id'   => 'vc-url',
            'type' => 'text_url',
        ) );
        $cmb_info->add_field([
            'name' => __('Prices', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'prices',
            'type'    => 'wysiwyg',
            'options' => array(
                'teeny' => true,
                'textarea_rows' => get_option('default_post_edit_rows', 5),
                'media_buttons' => false,
            ),
        ]);
        $cmb_info->add_field( array(
            'name' => esc_html__( 'Tickets URL', 'rrze-calendar' ),
            //'desc' => esc_html__( '', 'rrze-calendar' ),
            'id'   => 'tickets-url',
            'type' => 'text_url',
        ) );
        $cmb_info->add_field( array(
            'name'    => __('Downloads', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id'      => 'downloads',
            'type'    => 'file_list',
            // Optional:
            'options' => array(
                'url' => false, // Hide the text input for the url
            ),
            'text'    => array(
                'add_upload_file_text' => 'Add File' // Change upload button text. Default: "Add or Upload File"
            ),
            'query_args' => array(
                'type' => array(
                    'application/pdf',
                ),
            ),
            'preview_size' => 'large', // Image size to use when previewing in the admin.
        ) );
        $cmb_info->add_field([
            'name' => __('Featured Event', 'rrze-calendar'),
            //'desc'    => __('Show event on home page', 'rrze-calendar'),
            'id' => 'featured',
            'type' => 'checkbox',
        ]);

        // Schedule
        $cmb_schedule = new_cmb2_box([
            'id' => 'my-event-calendar-event-schedule',
            'title' => __('Schedule', 'rrze-calendar'),
            'object_types' => ['event'],
            'context' => 'normal',
            'priority' => 'high',
            'show_names' => true,
        ]);
        $cmb_schedule->add_field([
            'name' => __('Start', 'rrze-calendar'),
            //'desc'    => __('Date / Time', 'rrze-calendar'),
            'id' => 'start',
            'type' => 'text_datetime_timestamp',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'attributes' => array(
                // CMB2 checks for datepicker override data here:
                'data-datepicker' => json_encode( array(
                    'firstDay' => 1,
                    'dayNames' => Utils::getDaysOfWeek('short'),
                    'dayNamesMin' => Utils::getDaysOfWeek('min'),
                    'monthNamesShort' => Utils::getMonthNames('short'),
                    'yearRange' => '-1:+10',
                    'dateFormat'=> 'dd.mm.yy',
                ) ),
                'data-timepicker' => json_encode( array(
                    'timeFormat' => 'HH:mm',
                ) ),
            ),
        ]);
        $cmb_schedule->add_field([
            'name' => __('End', 'rrze-calendar'),
            //'desc'    => __('Date / Time', 'rrze-calendar'),
            'id' => 'end',
            'type' => 'text_datetime_timestamp',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'attributes' => array(
                // CMB2 checks for datepicker override data here:
                'data-datepicker' => json_encode( array(
                    'firstDay' => 1,
                    'dayNames' => Utils::getDaysOfWeek('short'),
                    'dayNamesMin' => Utils::getDaysOfWeek('min'),
                    'monthNamesShort' => Utils::getMonthNames('short'),
                    'yearRange' => '-1:+10',
                    'dateFormat'=> 'dd.mm.yy',
                ) ),
                'data-timepicker' => json_encode( array(
                    'timeFormat' => 'HH:mm',
                ) ),
            ),
        ]);

        $cmb_schedule->add_field([
            'name' => __('Repeat', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'repeat',
            'type' => 'checkbox',
        ]);
        $cmb_schedule->add_field([
            'name' => __('Repeat until', 'rrze-calendar'),
            'desc'    => __('(optional)', 'rrze-calendar'),
            'id' => 'repeat-lastdate',
            'type' => 'text_date_timestamp',
            'date_format' => 'd.m.Y',
            'attributes' => array(
                // CMB2 checks for datepicker override data here:
                'data-datepicker' => json_encode( array(
                    'firstDay' => 1,
                    'dayNames' => Utils::getDaysOfWeek('short'),
                    'dayNamesMin' => Utils::getDaysOfWeek('min'),
                    'monthNamesShort' => Utils::getMonthNames('short'),
                    'yearRange' => '-1:+10',
                ) ),
            ),
            'classes'   => ['repeat'],
        ]);
        $cmb_schedule->add_field([
            'name' => __('Repeat Interval', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'repeat-interval',
            'type' => 'select',
            'default'          => 'week',
            'options'          => [
                'week'   => __( 'Weekly', 'rrze-calendar' ),
                'month'     => __( 'Monthly', 'rrze-calendar' ),
            ],
            'classes'   => ['repeat'],
        ]);
        // repeat weekly
        $cmb_schedule->add_field([
            'name' => __('Repeats', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'repeat-weekly-interval',
            'type' => 'text_small',
            'attributes' => [
                'type' => 'number',
                'min' => '1',
            ],
            'before_field' => __('every', 'rrze-calendar') . ' ',
            'after_field' =>  ' ' . __('week(s)', 'rrze-calendar'),
            'classes'   => ['repeat', 'repeat-weekly'],
        ]);
        $cmb_schedule->add_field([
            'name' => __('Repeats on', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'repeat-weekly-day',
            'type' => 'multicheck_inline',
            'options' => [
                'monday' => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(1)),
                'tuesday' => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(2)),
                'wednesday' => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(3)),
                'thursday' => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(4)),
                'friday' => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(5)),
                'saturday' => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(6)),
                'sunday' => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(0)),
            ],
            'classes'   => ['repeat', 'repeat-weekly'],
        ]);
        $cmb_schedule->add_field([
            'name' => __('Exceptions', 'rrze-calendar'),
            'desc' => __('Enter dates to be skipped (format YYYY-MM-DD, e.g. 2023-12-31). One date per line.', 'rrze-calendar'),
            //'desc' => __('Hier können Sie Tage angeben, an denen die Veranstaltung ausnahmsweise ausfällt (Format YYYY-MM-DD, z.B. 2023-12-31). Ein Datum pro Zeile.', 'rrze-calendar'),
            'id' => 'exceptions',
            'type' => 'textarea_small',
            'classes'   => ['repeat'],
        ]);
        $cmb_schedule->add_field([
            'name' => __('Additions', 'rrze-calendar'),
            'desc' => __('Add additional dates (format YYYY-MM-DD, e.g. 2023-12-31). One date per line.', 'rrze-calendar'),
            'id' => 'additions',
            'type' => 'textarea_small',
            'classes'   => ['repeat'],
        ]);
        // repeat monthly
        $cmb_schedule->add_field([
            'name' => __('Each', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'repeat-monthly-type',
            'type' => 'radio',
            'options' => [
                'date' => __('Date', 'rrze-calendar'),
                'dow' => __('Weekday', 'rrze-calendar'),
            ],
            'classes'   => ['repeat', 'repeat-monthly'],
        ]);
        $monthdays = [];
        for ($i = 1; $i <= 31; $i++) {
            $monthdays[$i] = $i.'.';
        }
        $cmb_schedule->add_field([
            'name' => __('Repeats on', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'repeat-monthly-type-date',
            'type' => 'select',
            'options' => $monthdays,
            'before_field' => __('each', 'rrze-calendar') . ' ',
            'after_field' =>  ' ' . __('of month', 'rrze-calendar'),
            'classes'   => ['repeat', 'repeat-monthly', 'repeat-monthly-date'],
        ]);
        $cmb_schedule->add_field([
            'name' => __('Repeats on', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'repeat-monthly-type-dow',
            'type' => 'select_weekdayofmonth',
            'before_field' => __('each', 'rrze-calendar') . ' ',
            'after_field' =>  ' ' . __('of month', 'rrze-calendar'),
            'classes'   => ['repeat', 'repeat-monthly', 'repeat-monthly-dow'],
        ]);
        $cmb_schedule->add_field([
            'name' => __('Repeats on', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'repeat-monthly-month',
            'type' => 'multicheck_inline',
            'options' => [
                'jan' => $wp_locale->get_month_abbrev($wp_locale->get_month('01')),
                'feb' => $wp_locale->get_month_abbrev($wp_locale->get_month('02')),
                'mar' => $wp_locale->get_month_abbrev($wp_locale->get_month('03')),
                'apr' => $wp_locale->get_month_abbrev($wp_locale->get_month('04')),
                'may' => $wp_locale->get_month_abbrev($wp_locale->get_month('05')),
                'jun' => $wp_locale->get_month_abbrev($wp_locale->get_month('06')),
                'jul' => $wp_locale->get_month_abbrev($wp_locale->get_month('07')),
                'aug' => $wp_locale->get_month_abbrev($wp_locale->get_month('08')),
                'sep' => $wp_locale->get_month_abbrev($wp_locale->get_month('09')),
                'oct' => $wp_locale->get_month_abbrev($wp_locale->get_month('10')),
                'nov' => $wp_locale->get_month_abbrev($wp_locale->get_month('11')),
                'dec' => $wp_locale->get_month_abbrev($wp_locale->get_month('12')),
            ],
            'classes'   => ['repeat', 'repeat-monthly'],
        ]);
        $cmb_schedule->add_field([
            'name' => __('Upcoming Event Items', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'event-items',
            'type' => 'event_items',
            'classes'   => ['repeat'],
        ]);

    }

    public static function save( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $post_id ) || ! ( get_post_type( $post_id ) === 'event' ) ) {
            return $post_id;
        }

        $eventList = Utils::buildEventsList([get_post($post_id)], false);
        //var_dump($eventList);
        update_post_meta($post_id, 'event-items', $eventList);

        // unhook this function to prevent infinite looping
        /* remove_action( 'save_post', 'saveEvent' );

         $slugRaw = get_the_title($post_id);
         $eventDate = get_post_meta($post_id, 'my-event-calendar-event-date', true);
         if ($eventDate != '') {
             $slugRaw .= '-' . date('Y-m-d', $eventDate);
         }
         $slugRaw = sanitize_title($slugRaw);
         $postStatus = get_post_status($post_id);

         $slug = wp_unique_post_slug($slugRaw, $post_id, $postStatus, 'event', 0);
         // update the post slug
         wp_update_post( array(
             'ID' => $post_id,
             'post_name' => $slug // do your thing here
         ));

         // re-hook this function
         add_action( 'save_post', 'saveEvent' );*/

    }

    public static function updatedMeta($meta_id, $post_id, $meta_key='', $meta_value='') {
        //if ($meta_key =='_edit_lock') {
        $eventList = Utils::buildEventsList([get_post($post_id)], false);
        update_post_meta($post_id, 'event-items', $eventList);
        //}
    }

    public static function renderMonthDayField( $field, $value, $object_id, $object_type, $field_type ) {
        global $wp_locale;
        $value = wp_parse_args($value, array(
            'day' => '',
            'daycount' => '',
        ));
        $daycount = [
            '1' => __('first', 'rrze-calendar'),
            '2' => __('second', 'rrze-calendar'),
            '3' => __('third', 'rrze-calendar'),
            '4' => __('fourth', 'rrze-calendar'),
            '5' => __('fifth', 'rrze-calendar'),
        ];
        $optionsDaycount = '';
        foreach ($daycount as $k => $v) {
            $optionsDaycount .= '<option value="'.$k.'" ' . selected($k, $value['daycount'], false) . '>'.$v.'</option>';
        }
        $weekdays = [
            'mon' => $wp_locale->get_weekday(1),
            'tue' => $wp_locale->get_weekday(2),
            'wed' => $wp_locale->get_weekday(3),
            'thu' => $wp_locale->get_weekday(4),
            'fri' => $wp_locale->get_weekday(5),
            'sat' => $wp_locale->get_weekday(6),
            'sun' => $wp_locale->get_weekday(0),
        ];
        $optionsWeekdays = '';
        foreach ($weekdays as $k => $v) {
            $optionsWeekdays .= '<option value="'.$k.'" ' . selected($k, $value['day'], false) . '>'.$v.'</option>';
        }
        ?>
        <label for="<?php echo $field_type->_id( '_daycount' ); ?>'" class="screen-reader-text">Tageszähler</label>
        <?php echo $field_type->select( array(
            'name'    => $field_type->_name( '[daycount]' ),
            'id'      => $field_type->_id( '_daycount' ),
            'options' => $optionsDaycount,
            'desc'    => '',
        ) ); ?>
        </>
        <label for="<?php echo $field_type->_id( '_day' ); ?>'" class="screen-reader-text">Wochentag</label>
        <?php echo $field_type->select( array(
            'name'    => $field_type->_name( '[day]' ),
            'id'      => $field_type->_id( '_day' ),
            'options' => $optionsWeekdays,
            'desc'    => '',
        ) ); ?>
        <?php
    }

    public static function renderEventItemsField( $field, $value, $object_id, $object_type, $field_type ) {
        $eventItems = get_post_meta($object_id, 'event-items', true);
        if (is_array($eventItems)) {
            echo '<ul style="columns: 4 150px;">';
            foreach ($eventItems as $TSstart_ID => $TSend) {
                $start = explode('#', $TSstart_ID)[0];
                echo '<li>' . date_i18n(get_option('date_format'), $start) . '</li>';
                //echo '<li>' . date('Y-m-d', $start) . '</li>';
            }
            echo '</ul>';
            echo '<p class="description">' . __('Only event items of one year are listed, even if no end date is set.', 'rrze-calendar') . '</p>';
        } else {
            _e('No events found.', 'rrze-calendar');
        }
    }

}