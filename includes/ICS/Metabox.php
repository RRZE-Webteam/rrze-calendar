<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\CPT\CalendarFeed;

class Metabox
{
    public static function init()
    {
        add_action('add_meta_boxes', [__CLASS__, 'add']);
        add_action('save_post', [__CLASS__, 'save'], 10, 2);
        add_action('save_post', [__CLASS__, 'search'], 10, 2);
        add_action('post_updated', [__CLASS__, 'search'], 10, 2);
        add_action('admin_notices', [__CLASS__, 'adminNotices']);
    }

    public static function add()
    {
        add_meta_box(
            'rrze_calendar_metabox',
            __('Feed', 'rrze-calendar'),
            [__CLASS__, 'feed'],
            CalendarFeed::POST_TYPE,
            'advanced',
            'default'
        );

        add_meta_box(
            'rrze_calendar_list_table_metabox',
            __('Events', 'rrze-calendar'),
            [__CLASS__, 'events'],
            CalendarFeed::POST_TYPE,
            'advanced',
            'default'
        );
    }

    public static function feed($post)
    {
        // Get the CF values.
        $value = (string) get_post_meta($post->ID, CalendarFeed::FEED_URL, true);
        $valuePastDays = get_post_meta($post->ID, CalendarFeed::FEED_PAST_DAYS, true);
        if ($valuePastDays == '') {
            $valuePastDays = 36500;
        } else {
            $valuePastDays = intval($valuePastDays);
        }
        $valueInclude = (string) get_post_meta($post->ID, CalendarFeed::FEED_INCLUDE, true);
        $valueExclude = (string) get_post_meta($post->ID, CalendarFeed::FEED_EXCLUDE, true);

        // Add nonce for security and authentication.
        wp_nonce_field('rrze_calendar_metabox_action', 'rrze_calendar_metabox');

        // Render the form field.
        echo '<table class="form-table">';

        echo '<tr>',
        '<th><label for="' . CalendarFeed::FEED_URL . '">', __('ICS Feed URL', 'rrze-calendar'), '</label></th>';
        echo '<td>';
        echo '<input name="' . CalendarFeed::FEED_URL . '" type="text" id="rrze-calendar-feed-url" aria-describedby="', _e('ICS Feed Url', 'rrze-calendar'), '" class="large-text" value="', $value, '" autocomplete="off" />';
        echo '<p class="description">', _e('Enter the url of the ICS feed. This field is required.', 'rrze-calendar'), '</p>';
        echo '</td></tr>';

        echo '<tr>',
            '<th><label for="' . CalendarFeed::FEED_PAST_DAYS . '">', __('Past Events', 'rrze-calendar'), '</label></th>';
        echo '<td>';
        echo '<input name="' . CalendarFeed::FEED_PAST_DAYS . '" type="number" id="rrze-calendar-feed-past-days" aria-describedby="', _e('Past Events', 'rrze-calendar'), '" class="" value="', $valuePastDays, '" autocomplete="off" min="30" max="36500"/>';
        echo '<p class="description">', _e('For how many days in the past events are imported (max: 36500, min:30)? Older events are deleted from the website.', 'rrze-calendar'), '</p>';
        echo '</td></tr>';

        echo '<tr>',
            '<th><label for="' . CalendarFeed::FEED_INCLUDE . '">', __('Include Events', 'rrze-calendar'), '</label></th>';
        echo '<td>';
        echo '<input name="' . CalendarFeed::FEED_INCLUDE . '" type="text" id="rrze-calendar-feed-include" aria-describedby="', _e('Include Events', 'rrze-calendar'), '" class="large-text" value="', $valueInclude, '" autocomplete="off" />';
        echo '<p class="description">', _e('Enter a string to filter the import. Only events containing this string in the title will be imported.', 'rrze-calendar'), '</p>';
        echo '</td></tr>';

        echo '<tr>',
            '<th><label for="' . CalendarFeed::FEED_EXCLUDE . '">', __('Exclude Events', 'rrze-calendar'), '</label></th>';
        echo '<td>';
        echo '<input name="' . CalendarFeed::FEED_EXCLUDE . '" type="text" id="rrze-calendar-feed-exclude" aria-describedby="', _e('Exclude Events', 'rrze-calendar'), '" class="large-text" value="', $valueExclude, '" autocomplete="off" />';
        echo '<p class="description">', _e('Enter a string to exclude events from importing. Events containing this string in the title will not be imported.', 'rrze-calendar'), '</p>';
        echo '</td></tr>';

        echo '</table>';
    }

    public static function save($postId, $post)
    {
        // Check the post type.
        if ($post->post_type != CalendarFeed::POST_TYPE) {
            return;
        }
        
        // Add nonce for security and authentication.
        $nonceName = isset($_POST['rrze_calendar_metabox']) ? $_POST['rrze_calendar_metabox'] : '';
        $nonceAction = 'rrze_calendar_metabox_action';

        // Check if nonce is valid.
        if (!wp_verify_nonce($nonceName, $nonceAction)) {
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

        if (
            array_key_exists(CalendarFeed::FEED_URL, $_POST)
            && $url = Utils::validateUrl($_POST[CalendarFeed::FEED_URL])
        ) {
            update_post_meta(
                $postId,
                CalendarFeed::FEED_URL,
                $url
            );
        } else {
            add_filter('redirect_post_location', [__CLASS__, 'addNotice']);
        }

        if (array_key_exists(CalendarFeed::FEED_PAST_DAYS, $_POST)) {
            $feed_past_days = intval($_POST[CalendarFeed::FEED_PAST_DAYS]);
            if ($feed_past_days > 36500) {
               $feed_past_days = 36500;
            } elseif ($feed_past_days < 30) {
               $feed_past_days = 30;
            }
            update_post_meta(
                $postId,
                CalendarFeed::FEED_PAST_DAYS,
                $feed_past_days
            );
        }

        if (array_key_exists(CalendarFeed::FEED_INCLUDE, $_POST)) {
            update_post_meta(
                $postId,
                CalendarFeed::FEED_INCLUDE,
                sanitize_text_field($_POST[CalendarFeed::FEED_INCLUDE])
            );
        }

        if (array_key_exists(CalendarFeed::FEED_EXCLUDE, $_POST)) {
            update_post_meta(
                $postId,
                CalendarFeed::FEED_EXCLUDE,
                sanitize_text_field($_POST[CalendarFeed::FEED_EXCLUDE])
            );
        }
    }

    public static function search($postId, $post)
    {
        $postType = get_post_type($post);
        $referer = $_POST['_wp_http_referer'] ?? '';
        $searchTerm  = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
        if ($searchTerm == '') {
            $searchTerm = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        }
        if ($postType == CalendarFeed::POST_TYPE && $searchTerm != '') {
            wp_safe_redirect(add_query_arg('s', $searchTerm, $referer));
            exit;
        }
    }

    public static function addNotice($location)
    {
        remove_filter('redirect_post_location', [__CLASS__, 'addNotice']);
        return add_query_arg(array('rrze_calendar_update' => 'update'), $location);
    }

    public static function adminNotices()
    {
        if (!isset($_GET['rrze_calendar_update'])) {
            return;
        }

        echo '<div class="update notice notice-error is-dismissible">';
        echo '<p>', esc_html_e('Feed URL is not valid.', 'rrze-calendar'), '</p>';
        echo '</div>';
    }

    public static function events()
    {
        $events = new EventsListTable();
        $events->prepare_items();

        echo '<div class="wrap">';
        echo $events->search_box(__('Search Events', 'rrze-calendar'), 'title');
        echo $events->display();
        echo '</div>';
    }
}
