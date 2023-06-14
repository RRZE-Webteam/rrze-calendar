<?php

/* ---------------------------------------------------------------------------
 * Custom Post Type 'calendar_event'
 * ------------------------------------------------------------------------- */

namespace RRZE\Calendar\CPT;

defined('ABSPATH') || exit;

use RRZE\Calendar\Templates;
use RRZE\Calendar\Utils;

use function RRZE\Calendar\plugin;

class CalendarEvent
{
    const POST_TYPE = 'calendar_event';

    const TAX_CATEGORY = 'rrze-calendar-category'; // comes from version 1.x

    const TAX_TAG = 'rrze-calendar-tag'; // comes from version 1.x

    public static function init()
    {
        // Register Post Type.
        add_action('init', [__CLASS__, 'registerPostType']);
        // Register Taxonomies.
        add_action('init', [__CLASS__, 'registerCategory']);
        add_action('init', [__CLASS__, 'registerTag']);
        // CMB2 Fields
        add_action('cmb2_admin_init', [__CLASS__, 'eventFields']);
        add_filter('cmb2_render_select_weekdayofmonth', [__CLASS__, 'renderMonthDayField'], 10, 5);
        add_filter('cmb2_render_event_items', [__CLASS__, 'renderEventItemsField'], 10, 5);
        // Update Feed Items.
        add_action('save_post', [__CLASS__, 'save'], 10, 2);
        add_action('updated_post_meta', [__CLASS__, 'updatedMeta'], 10, 4);
        // Templates
        add_filter('single_template', [__CLASS__, 'includeSingleTemplate']);

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
            'supports'            => ['title', 'author', 'excerpt', 'thumbnail'],
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

    public static function eventFields()
    {
        global $wp_locale;

        // General Information
        $cmb_info = new_cmb2_box([
            'id' => 'my-event-calendar-event-info',
            'title' => __('General Information', 'rrze-calendar'),
            'object_types' => [self::POST_TYPE],
            'context' => 'normal',
            'priority' => 'high',
            'show_names' => true,
        ]);
        $cmb_info->add_field([
            'name' => __('Start', 'rrze-calendar'),
            //'desc'    => __('Date / Time', 'rrze-calendar'),
            'id' => 'start',
            'type' => 'text_datetime_timestamp',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'attributes' => array(
                // CMB2 checks for datepicker override data here:
                'data-datepicker' => json_encode(array(
                    'firstDay' => 1,
                    'dayNames' => Utils::getDaysOfWeek('short'),
                    'dayNamesMin' => Utils::getDaysOfWeek('min'),
                    'monthNamesShort' => Utils::getMonthNames('short'),
                    'yearRange' => '-1:+10',
                    'dateFormat' => 'dd.mm.yy',
                )),
                'data-timepicker' => json_encode(array(
                    'timeFormat' => 'HH:mm',
                )),
            ),
        ]);
        $cmb_info->add_field([
            'name' => __('End', 'rrze-calendar'),
            //'desc'    => __('Date / Time', 'rrze-calendar'),
            'id' => 'end',
            'type' => 'text_datetime_timestamp',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'attributes' => array(
                // CMB2 checks for datepicker override data here:
                'data-datepicker' => json_encode(array(
                    'firstDay' => 1,
                    'dayNames' => Utils::getDaysOfWeek('short'),
                    'dayNamesMin' => Utils::getDaysOfWeek('min'),
                    'monthNamesShort' => Utils::getMonthNames('short'),
                    'yearRange' => '-1:+10',
                    'dateFormat' => 'dd.mm.yy',
                )),
                'data-timepicker' => json_encode(array(
                    'timeFormat' => 'HH:mm',
                )),
            ),
        ]);
        $cmb_info->add_field([
            'name' => __('All day event', 'rrze-calendar'),
            'id' => 'all-day',
            'type' => 'checkbox',
        ]);
        $cmb_info->add_field(array(
            'name'    => esc_html__('Description', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id'      => 'description',
            'type'    => 'wysiwyg',
            'options' => array(
                'textarea_rows' => get_option('default_post_edit_rows', 12),
            ),
        ));
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
        $cmb_info->add_field(array(
            'name' => esc_html__('VC URL', 'rrze-calendar'),
            //'desc' => esc_html__( '', 'rrze-calendar' ),
            'id'   => 'vc-url',
            'type' => 'text_url',
        ));
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
        $cmb_info->add_field(array(
            'name' => esc_html__('Registration URL', 'rrze-calendar'),
            //'desc' => esc_html__( '', 'rrze-calendar' ),
            'id'   => 'registration-url',
            'type' => 'text_url',
        ));
        $cmb_info->add_field(array(
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
        ));
        $cmb_info->add_field([
            'name' => __('Featured Event', 'rrze-calendar'),
            //'desc'    => __('Show event on home page', 'rrze-calendar'),
            'id' => 'featured',
            'type' => 'checkbox',
        ]);

        // Schedule
        $cmb_schedule = new_cmb2_box([
            'id' => 'my-event-calendar-event-schedule',
            'title' => __('Repeating Event', 'rrze-calendar'),
            'object_types' => [self::POST_TYPE],
            'context' => 'normal',
            'priority' => 'high',
            'show_names' => true,
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
                'data-datepicker' => json_encode(array(
                    'firstDay' => 1,
                    'dayNames' => Utils::getDaysOfWeek('short'),
                    'dayNamesMin' => Utils::getDaysOfWeek('min'),
                    'monthNamesShort' => Utils::getMonthNames('short'),
                    'yearRange' => '-1:+10',
                )),
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
                'week'   => __('Weekly', 'rrze-calendar'),
                'month'     => __('Monthly', 'rrze-calendar'),
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
            $monthdays[$i] = $i . '.';
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
        $monthOptions = [
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
        ];
        $cmb_schedule->add_field([
            'name' => __('Repeats in', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'repeat-monthly-month',
            'type' => 'multicheck_inline',
            'options' => $monthOptions,
            'default' => array_keys($monthOptions),
            'classes'   => ['repeat', 'repeat-monthly'],
        ]);
        $cmb_schedule->add_field([
            'name' => __('Event Items', 'rrze-calendar'),
            //'desc'    => __('', 'rrze-calendar'),
            'id' => 'event-items',
            'type' => 'event_items',
            'classes'   => ['repeat'],
        ]);
    }

    public static function save($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($post_id) || !(get_post_type($post_id) === 'event')) {
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

    public static function updatedMeta($meta_id, $post_id, $meta_key = '', $meta_value = '')
    {
        //if ($meta_key =='_edit_lock') {
        $eventList = Utils::buildEventsList([get_post($post_id)], false);
        update_post_meta($post_id, 'event-items', $eventList);
        //}
    }

    public static function renderMonthDayField($field, $value, $object_id, $object_type, $field_type)
    {
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
            $optionsDaycount .= '<option value="' . $k . '" ' . selected($k, $value['daycount'], false) . '>' . $v . '</option>';
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
            $optionsWeekdays .= '<option value="' . $k . '" ' . selected($k, $value['day'], false) . '>' . $v . '</option>';
        }
?>
        <label for="<?php echo $field_type->_id('_daycount'); ?>'" class="screen-reader-text">Tageszähler</label>
        <?php echo $field_type->select(array(
            'name'    => $field_type->_name('[daycount]'),
            'id'      => $field_type->_id('_daycount'),
            'options' => $optionsDaycount,
            'desc'    => '',
        )); ?>
        </>
        <label for="<?php echo $field_type->_id('_day'); ?>'" class="screen-reader-text">Wochentag</label>
        <?php echo $field_type->select(array(
            'name'    => $field_type->_name('[day]'),
            'id'      => $field_type->_id('_day'),
            'options' => $optionsWeekdays,
            'desc'    => '',
        )); ?>
<?php
    }

    public static function renderEventItemsField($field, $value, $object_id, $object_type, $field_type)
    {
        $eventItems = get_post_meta($object_id, 'event-items', true);
        if (is_array($eventItems)) {
            echo '<ul class="event-items">';
            foreach ($eventItems as $TSstart_ID => $TSend) {
                $start = explode('#', $TSstart_ID)[0];
                $class = $start < time() ? 'past' : 'future';
                echo '<li class="'.$class.'">' . date_i18n(get_option('date_format'), $start) . '</li>';
                //echo '<li>' . date('Y-m-d', $start) . '</li>';
            }
            echo '</ul>';
            echo '<p class="description">' . __('Maximum +/- 1 year.', 'rrze-calendar') . '</p>';
        } else {
            _e('No events found.', 'rrze-calendar');
        }
    }

    public static function includeSingleTemplate($singleTemplate) {
        global $post;
        if ($post->post_type != 'calendar_event')
            return $singleTemplate;

        return Templates::getCptCalendarEventSingleTpl();
    }
}
