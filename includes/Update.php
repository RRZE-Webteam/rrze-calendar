<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\{CalendarEvent, CalendarFeed};

class Update
{
    const VERSION_OPTION_NAME = 'rrze_calendar_version';

    const LEGACY_FEEDS_TABLE_NAME = 'rrze_calendar_feeds';
    const LEGACY_TAX_CAT_KEY = 'rrze-calendar-category';
    const LEGACY_TAX_TAG_KEY = 'rrze-calendar-tag';

    public static function init()
    {
        $version = get_option(static::VERSION_OPTION_NAME, '0');

        if (version_compare($version, '2.0.0', '<')) {
            add_action('init', [__CLASS__, 'deleteAllPosts']);
            add_action('init', [__CLASS__, 'updateToVersion200'], PHP_INT_MAX);
            update_option(static::VERSION_OPTION_NAME, '2.0.0');
        } elseif (version_compare($version, '2.0.1', '<')) {
            add_action('init', [__CLASS__, 'deleteAllPosts']);
            add_action('init', [__CLASS__, 'updateToVersion201'], PHP_INT_MAX);
            update_option(static::VERSION_OPTION_NAME, '2.0.1');
        }
    }

    public static function updateToVersion201()
    {
        self::updateToVersion200();
    }

    public static function updateToVersion200()
    {
        global $wpdb;

        $result = $wpdb->get_results(
            'SELECT * FROM ' . $wpdb->prefix . static::LEGACY_FEEDS_TABLE_NAME
        );

        if (!empty($result)) {
            $admins = [];
            $users = get_users(['role' => 'administrator']);
            foreach ($users as $user) {
                $admins[] = $user->ID;
            }
            $adminId = array_rand(array_flip($admins), 1);

            foreach ($result as $row) {
                $hierarchicalTax = [];
                $category = self::legacyGetFeedCategory($row->id);
                if ($category && isset($category->term_id)) {
                    $hierarchicalTax[] = $category->term_id;
                }

                $nonHierarchicalTerms = [];
                $tags = self::legacyGetFeedTags($row->id);
                if ($tags) {
                    $nonHierarchicalTerms = $tags;
                }

                $postArr = [
                    'post_author'   => $adminId,
                    'post_title'    => $row->title,
                    'post_content'  => '',
                    'post_status'   => $row->active ? 'publish' : 'draft',
                    'post_type'     => CalendarFeed::POST_TYPE,
                    'tax_input'     => [
                        CalendarEvent::TAX_CATEGORY => $hierarchicalTax,
                        CalendarEvent::TAX_TAG      => $nonHierarchicalTerms,
                    ],
                    'meta_input'    => [
                        CalendarFeed::FEED_URL => $row->url,
                    ]
                ];

                wp_insert_post($postArr);
            }
        }
        flush_rewrite_rules();
        Cron::clearSchedule();
    }

    private static function legacyGetFeedCategory($feedId)
    {
        $allCategories = self::legacyGetCategories();

        if (!empty($allCategories)) {
            foreach ($allCategories as $category) {
                if (empty($category->feed_ids) || !in_array($feedId, $category->feed_ids)) {
                    continue;
                }

                $color = strtoupper(get_term_meta($category->term_id, 'color', true));
                $category->color = $color ? $color : '';

                return $category;
            }
        }

        return false;
    }

    private static function legacyGetFeedTags($feedId)
    {
        $allTags = self::legacyGetTags();

        if (!empty($allTags)) {
            $tagIds = [];
            foreach ($allTags as $tag) {
                if (empty($tag->feed_ids) || !in_array($feedId, $tag->feed_ids)) {
                    continue;
                }
                $tagIds[] = (int) $tag->term_id;
            }
            return $tagIds;
        } else {
            return false;
        }
    }

    private static function legacyGetCategories($args = [])
    {

        if (!isset($args['hide_empty'])) {
            $args['hide_empty'] = 0;
        }

        $categoryTerms = get_terms(static::LEGACY_TAX_CAT_KEY, $args);
        if (is_wp_error($categoryTerms) || empty($categoryTerms)) {
            return [];
        }

        $categories = [];
        foreach ($categoryTerms as $categoryTerm) {
            if ($category = self::legacyGetCategoryBy('id', $categoryTerm->term_id)) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    private static function legacyGetTags($args = [])
    {

        if (!isset($args['hide_empty'])) {
            $args['hide_empty'] = 0;
        }

        $tagTerms = get_terms(static::LEGACY_TAX_TAG_KEY, $args);
        if (is_wp_error($tagTerms) || empty($tagTerms)) {
            return [];
        }

        $tags = [];
        foreach ($tagTerms as $tagTerm) {
            if ($tag = self::legacyGetTagBy('id', $tagTerm->term_id)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    private static function legacyGetCategoryBy($field, $value)
    {

        $category = get_term_by($field, $value, static::LEGACY_TAX_CAT_KEY);

        if (is_wp_error($category) || empty($category)) {
            return null;
        }

        $unencodedDescription = self::unencodedDescription($category->description);
        if (is_array($unencodedDescription)) {
            foreach ($unencodedDescription as $key => $value) {
                $category->$key = $value;
            }
        }

        return $category;
    }

    private static function legacyGetTagBy($field, $value)
    {

        $tag = get_term_by($field, $value, static::LEGACY_TAX_TAG_KEY);

        if (is_wp_error($tag) || empty($tag)) {
            return null;
        }

        $unencodedDescription = self::unencodedDescription($tag->description);
        if (is_array($unencodedDescription)) {
            foreach ($unencodedDescription as $key => $value) {
                $tag->$key = $value;
            }
        }

        return $tag;
    }

    private static function unencodedDescription($string)
    {
        return maybe_unserialize(base64_decode($string));
    }

    public static function deleteAllPosts()
    {
        self::deletePostsByPostMetaKey(CalendarEvent::POST_TYPE, 'ics_feed_id');
        self::deleteAllPostsOfCustomPostType(CalendarFeed::POST_TYPE);
    }

    private static function deleteAllPostsOfCustomPostType($postType)
    {
        $args = [
            'post_type' => $postType,
            'posts_per_page' => -1,
        ];

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $postId = get_the_ID();
                wp_delete_post($postId, true);
            }
        }

        wp_reset_postdata();
    }

    private static function deletePostsByPostMetaKey($postType, $metaKey)
    {
        $args = [
            'post_type' => $postType,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => $metaKey,
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $postId = get_the_ID();
                wp_delete_post($postId, true);
            }
        }

        wp_reset_postdata();
    }
}
