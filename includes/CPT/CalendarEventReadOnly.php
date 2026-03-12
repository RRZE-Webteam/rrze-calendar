<?php

namespace RRZE\Calendar\CPT;

defined('ABSPATH') || exit;

/**
 * Make imported calendar events read-only.
 *
 * @package RRZE\Calendar\CPT
 */
class CalendarEventReadOnly
{
    public static function init(): void
    {
        // Core: make WP think user cannot edit those posts => title becomes plain text.
        add_filter('map_meta_cap', [__CLASS__, 'mapMetaCap'], 10, 4);

        // UX: remove row actions (edit/quick edit/trash).
        add_filter('post_row_actions', [__CLASS__, 'removeRowActions'], 10, 2);

        // Safety: block direct access to edit screen.
        add_action('load-post.php', [__CLASS__, 'blockEditScreen']);
    }

    /**
     * Check if a calendar event is imported from an ICS feed.
     */
    private static function isIcsImported($postId): bool
    {
        $postId = (int) $postId;

        if ($postId <= 0) {
            return false;
        }

        if (get_post_type($postId) !== CalendarEvent::POST_TYPE) {
            return false;
        }

        return (bool) get_post_meta($postId, 'ics_feed_id', true);
    }

    /**
     * Remove the ability to edit/delete imported events.
     *
     * This disables:
     * - title edit link in list table
     * - "Edit", "Quick edit"
     * - direct edit access (cap check fails)
     */
    public static function mapMetaCap($caps, $cap, $userId, $args): array
    {
        if (!is_array($caps)) {
            $caps = [];
        }

        // Handle only edit/delete capabilities for posts.
        if (!in_array($cap, ['edit_post', 'delete_post'], true)) {
            return $caps;
        }

        $postId = 0;

        if (is_array($args) && isset($args[0])) {
            $postId = (int) $args[0];
        }

        if ($postId <= 0) {
            return $caps;
        }

        if (!self::isIcsImported($postId)) {
            return $caps;
        }

        // Deny.
        return ['do_not_allow'];
    }

    /**
     * Remove row actions in admin list.
     */
    public static function removeRowActions(array $actions, \WP_Post $post): array
    {
        if ($post->post_type !== CalendarEvent::POST_TYPE) {
            return $actions;
        }

        if (!self::isIcsImported((int) $post->ID)) {
            return $actions;
        }

        unset($actions['edit']);
        unset($actions['inline hide-if-no-js']);

        // Optional: also hide trash.
        // unset($actions['trash']);

        return $actions;
    }

    /**
     * Block direct access to edit screen (post.php) for imported events.
     * Even though map_meta_cap should already block it, this gives a clearer message.
     */
    public static function blockEditScreen(): void
    {
        $postId = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if ($postId <= 0) {
            return;
        }

        if (!self::isIcsImported($postId)) {
            return;
        }

        wp_die(
            __('This event was imported from an ICS feed and cannot be edited.', 'rrze-calendar'),
            __('Read-only event', 'rrze-calendar'),
            ['response' => 403]
        );
    }
}
