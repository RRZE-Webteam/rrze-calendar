<?php

/* ---------------------------------------------------------------------------
 * Custom Post Type 'calendar_feed'
 * ------------------------------------------------------------------------- */

namespace RRZE\Calendar\CPT;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\ICS\{Events, Metabox};

class CalendarFeed
{
    const POST_TYPE = 'calendar_feed';

    const FEED_URL = 'ics_feed_url';

    const FEED_DATETIME = 'ics_feed_datetime';

    const FEED_ERROR = 'ics_feed_error';

    const FEED_EVENTS_ITEMS = 'ical_events_items';

    const FEED_EVENTS_META = 'ical_events_meta';

    public static function init()
    {
        // Register Post Type.
        add_action('init', [__CLASS__, 'registerPostType']);
        // Post Type Columns.
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'postsColumns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'postsCustomColumns'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [__CLASS__, 'postSortableColumns']);
        // Register Metadata.
        add_action('init', [__CLASS__, 'registerMeta']);
        // Taxonomy Terms Fields.
        add_action(CalendarEvent::TAX_CATEGORY . '_add_form_fields', [__CLASS__, 'addFormFields']);
        add_action(CalendarEvent::TAX_CATEGORY . '_edit_form_fields', [__CLASS__, 'editFormFields'], 10, 2);
        add_action('created_' . CalendarEvent::TAX_CATEGORY, [__CLASS__, 'saveFormFields']);
        add_action('edited_' . CalendarEvent::TAX_CATEGORY, [__CLASS__, 'saveFormFields']);
        // Taxonomy Terms Custom Columns.
        add_filter('manage_edit-' . CalendarEvent::TAX_CATEGORY . '_columns', [__CLASS__, 'categoryColumns']);
        add_filter('manage_' . CalendarEvent::TAX_CATEGORY . '_custom_column', [__CLASS__, 'categoryCustomColumns'], 10, 3);
        // List Table Stuff.  
        add_action('pre_get_posts', [__CLASS__, 'maybeDisplayPublicArchivePosts']);
        add_action('template_redirect', [__CLASS__, 'maybeDisplayPublicPost']);
        add_filter('post_row_actions', [__CLASS__, 'displayViewOrPreviewLink']);
        add_filter('post_row_actions', [__CLASS__, 'removeQuickEditFields'], 10, 2);
        add_filter('months_dropdown_results', [__CLASS__, 'removeMonthsDropdown'], 10, 2);
        // Hide publishing actions.
        add_action('admin_head-post.php', [__CLASS__, 'hidePublishingActions']);
        add_action('admin_head-post-new.php', [__CLASS__, 'hidePublishingActions']);

        // Update Feed Items.
        add_action('save_post', [__CLASS__, 'save'], 10, 2);

        // Add Metabox.
        Metabox::init();
    }

    public static function registerPostType()
    {
        $labels = [
            'name'               => _x('ICS Feeds', 'post type general name', 'rrze-calendar'),
            'singular_name'      => _x('ICS Feed', 'post type singular name', 'rrze-calendar'),
            'menu_name'          => _x('ICS Feeds', 'admin menu', 'rrze-calendar'),
            'name_admin_bar'     => _x('ICS Feed', 'add new on admin bar', 'rrze-calendar'),
            'add_new'            => _x('Add New', 'popup', 'rrze-calendar'),
            'add_new_item'       => __('Add New ICS Feed', 'rrze-calendar'),
            'new_item'           => __('New ICS Feed', 'rrze-calendar'),
            'edit_item'          => __('Edit ICS Feed', 'rrze-calendar'),
            'view_item'          => __('View ICS Feed', 'rrze-calendar'),
            'all_items'          => __('ICS Feeds', 'rrze-calendar'),
            'search_items'       => __('Search Feeds', 'rrze-calendar'),
            'parent_item_colon'  => __('Parent Feeds:', 'rrze-calendar'),
            'not_found'          => __('No ics feeds found.', 'rrze-calendar'),
            'not_found_in_trash' => __('No ics feeds found in Trash.', 'rrze-calendar')
        ];

        $args = [
            'labels'              => $labels,
            'label'              => __('ICS Feeds', 'rrze-calendar'),
            'hierarchical'        => false,
            'public'              => false,
            'show_ui'             => true,
            'show_in_rest'        => true,
            //'show_in_menu'        => true,
            'show_in_menu'        => 'edit.php?post_type=' . CalendarEvent::POST_TYPE,
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'delete_with_user'    => false,
            'supports'            => ['title'],
            'menu_icon'           => 'dashicons-calendar-alt',
            'capability_type'    => Capabilities::getCptCapabilityType(self::POST_TYPE),
            'capabilities'       => (array) Capabilities::getCptCaps(self::POST_TYPE),
            'map_meta_cap'       => Capabilities::getCptMapMetaCap(self::POST_TYPE),
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    public static function postsColumns($columns)
    {
        if (isset($columns['date'])) unset($columns['date']);
        $columns['taxonomy-rrze-calendar-category'] = __('Categories', 'rrze-calendar');
        $columns['taxonomy-rrze-calendar-tag'] = __('Tags', 'rrze-calendar');
        $columns['events'] = __('Events', 'rrze-calendar');
        $columns['updated'] = __('Last Updated', 'rrze-calendar');
        return $columns;
    }

    public static function postSortableColumns($columns)
    {
        $columns['updated'] = 'updated';
        return $columns;
    }

    public static function postsCustomColumns($column, $postId)
    {
        switch ($column) {
            case 'events':
                if (
                    get_post_status($postId) === 'publish'
                    && $meta = get_post_meta($postId, self::FEED_EVENTS_META, true)
                ) {
                    echo $meta['event_count'] ?: '&mdash;';
                } else {
                    echo '&mdash;';
                }
                break;
            case 'updated':
                $published = get_post_status($postId) === 'publish';
                $dt = get_post_meta($postId, self::FEED_DATETIME, true);
                $error = get_post_meta($postId, self::FEED_ERROR, true);
                if ($published && $dt) {
                    printf(
                        /* translators: 1: Post date, 2: Post time. */
                        '<abbr title="%1$s %2$s">%1$s</abbr>',
                        /* translators: Post date format. See https://www.php.net/manual/datetime.format.php */
                        get_date_from_gmt($dt, __('Y/m/d')),
                        /* translators: Post time format. See https://www.php.net/manual/datetime.format.php */
                        get_date_from_gmt($dt, __('g:i a'))
                    );
                } elseif ($published && $error) {
                    echo $error;
                } else {
                    echo '&mdash;';
                }
        }
    }

    public static function registerMeta()
    {
        register_meta(
            'post',
            self::FEED_URL,
            [
                'type'          => 'string',
                'description'   => __('Feed URL.', 'rrze-calendar'),
                'single'        => true,
                'show_in_rest'  => false
            ]
        );
    }

    public static function addFormFields($taxonomy)
    {
        echo '<div class="form-field">',
        '<label for="color">', __('Color', 'rrze-calendar'), '</label>',
        '<input type="text" name="color" class="color-picker" data-default-color="#041E42" value="#041E42">',
        '</div>';
    }

    public static function editFormFields($term, $taxonomy)
    {
        $value = Utils::sanitizeHexColor(get_term_meta($term->term_id, 'color', true));

        echo '<tr class="form-field">',
        '<th>', '<label for="color">', __('Color', 'rrze-calendar'), '</label>', '</th>',
        '<td>',
        '<input type="text" name="color" class="color-picker" data-default-color="#041E42" value="' . $value . '">',
        '</td>',
        '</tr>';
    }

    public static function saveFormFields(int $termId)
    {
        $color = $_POST['color'] ?? '';
        update_term_meta(
            $termId,
            'color',
            Utils::sanitizeHexColor($color)
        );
    }

    public static function categoryColumns($columns)
    {
        $newColumns = [];
        foreach ($columns as $key => $value) {
            if ($key == 'name') {
                $newColumns['name'] = $columns['name'];
                $newColumns['color'] = __('Color', 'rrze-calendar');
            } else {
                $newColumns[$key] = $value;
            }
        }
        return $newColumns;
    }

    public static function categoryCustomColumns($content, $columnName, $termId)
    {
        $term = get_term($termId, CalendarEvent::TAX_CATEGORY);
        switch ($columnName) {
            case 'color':
                $color = Utils::sanitizeHexColor((get_term_meta($term->term_id, 'color', true)));
                $content = '<div style="height: 20px; width: 30px; background-color: ' . $color . ';"></div>';
                break;
            default:
                break;
        }
        return $content;
    }

    public static function tagColumns($columns)
    {
        $newColumns = [];
        foreach ($columns as $key => $value) {
            if ($key == 'description') {
                continue;
            } else {
                $newColumns[$key] = $value;
            }
        }
        return $newColumns;
    }

    public static function getData(int $postId): array
    {
        $data = [];

        $post = get_post($postId);
        if (!$post) {
            return $data;
        }

        $data['id'] = $postId;

        $data['post_date_gmt'] = $post->post_date_gmt;
        $data['post_date'] = $post->post_date;
        $data['post_date_format'] = sprintf(
            __('%1$s at %2$s'),
            get_the_time(__('Y/m/d'), $post),
            get_the_time(__('g:i a'), $post)
        );

        $data['send_date_gmt'] = $data['post_date_gmt'];
        $data['send_date'] = $data['post_date'];
        $data['send_date_format'] = $data['post_date_format'];

        $data['title'] = $post->post_title;

        $data['tag_terms'] = self::getTermsTag($postId, CalendarEvent::TAX_TAG);

        $data['post_status'] = $post->post_status;

        return $data;
    }

    protected static function getTermsTag($postId, $taxonomy)
    {
        $terms = get_the_terms($postId, $taxonomy);
        if ($terms !== false && !is_wp_error($terms)) {
            return $terms;
        }
        return false;
    }

    public static function maybeDisplayPublicArchivePosts($query)
    {
        if (
            is_admin() ||
            !$query->is_main_query() ||
            (!is_tax(CalendarEvent::TAX_CATEGORY) &&
                !is_post_type_archive(self::POST_TYPE))
        ) {
            return;
        }

        if (is_tax(CalendarEvent::TAX_CATEGORY) || empty($query->get('post_type'))) {
            $query->set('post_type', ['post', self::POST_TYPE]);
        }

        $metaQuery = $query->get('meta_query', []);
        $metaQueryParams = [
            [
                'key'     => 'rrze_calendar_is_public',
                'value'   => true,
                'compare' => '=',
            ],
        ];

        if (is_tax(CalendarEvent::TAX_CATEGORY)) {
            $metaQueryParams['relation'] = 'OR';
            $metaQueryParams[] = [
                'key'     => 'rrze_calendar_is_public',
                'compare' => 'NOT EXISTS',
            ];
        }

        $metaQuery[] = $metaQueryParams;
        $query->set('meta_query', $metaQuery);
    }

    public static function maybeDisplayPublicPost()
    {
        if (
            current_user_can('edit_others_posts') ||
            !is_singular(self::POST_TYPE)
        ) {
            return;
        }

        $isPublic = get_post_meta(get_the_ID(), 'rrze_calendar_is_public', true);
        if (empty($isPublic)) {
            add_filter(
                'wpseo_title',
                function ($title) {
                    return str_replace(get_the_title(), __('Page not found', 'rrze-calendar'), $title);
                }
            );

            status_header(404);
            nocache_headers();
            include get_query_template('404');
            die();
        }
    }

    public static function displayViewOrPreviewLink($actions)
    {
        if ('publish' !== get_post_status() || self::POST_TYPE !== get_post_type()) {
            return $actions;
        }

        $isPublic = get_post_meta(get_the_ID(), 'rrze_calendar_is_public', true);

        if (empty($isPublic) && isset($actions['view'])) {
            $actions['view'] = sprintf(
                '<a href="%1$s" rel="bookmark" aria-label="%2$s">%3$s</a>',
                esc_url(get_the_permalink()),
                esc_attr(get_the_title()),
                __('Preview', 'rrze-calendar')
            );
        }

        return $actions;
    }

    public static function removeQuickEditFields($actions)
    {
        if (self::POST_TYPE === get_post_type()) {
            //unset($actions['inline hide-if-no-js']);
            echo '
                <style type="text/css">
                    .inline-edit-date,
                    .inline-edit-group{
                        display:none;
                    }
                </style>
            ';
        }
        return $actions;
    }

    public static function removeMonthsDropdown($months, $postType)
    {
        if ($postType == self::POST_TYPE) {
            $months = [];
        }
        return $months;
    }

    public static function hidePublishingActions()
    {
        global $post;
        if ($post->post_type == self::POST_TYPE) {
            echo '
                <style type="text/css">
                    .misc-pub-visibility,
                    .misc-pub-curtime{
                        display:none;
                    }
                </style>
            ';
        }
    }

    public static function save($postId, $post)
    {
        // Check the post type.
        if ($post->post_type != self::POST_TYPE) {
            return;
        }

        // Check if user has permissions to save data.
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Check if not an autosave.
        if (wp_is_post_autosave($postId)) {
            return;
        }

        // Check if not a revision.
        if (wp_is_post_revision($postId)) {
            return;
        }

        Events::updateItems($postId, false);
    }
}
