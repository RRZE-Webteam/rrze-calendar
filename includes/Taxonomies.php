<?php

namespace FAU\Calendar;

defined('ABSPATH') || exit;

class Taxonomies
{
    /**
     * [TAXONOMY_CAT_KEY description]
     * @var string
     */
    const TAXONOMY_CAT_KEY = 'fau-calendar-category';

    /**
     * [TAXONOMY_TAG_KEY description]
     * @var string
     */
    const TAXONOMY_TAG_KEY = 'fau-calendar-tag';

    /**
     * [__construct description]
     */
    public function __construct()
    {
        add_action('init', [$this, 'registerTaxonomies']);
    }

    public function registerTaxonomies()
    {
        $args = [
            'public' => false,
            'rewrite' => false,
        ];

        register_taxonomy(static::TAXONOMY_CAT_KEY, 'post', $args);
        register_taxonomy(static::TAXONOMY_TAG_KEY, 'post', $args);
    }

    public static function getCategories($args = array())
    {
        if (!isset($args['hide_empty'])) {
            $args['hide_empty'] = 0;
        }

        $category_terms = getTerms(static::TAXONOMY_CAT_KEY, $args);
        if (is_wp_error($category_terms) || empty($category_terms)) {
            return array();
        }

        $categories = array();
        foreach ($category_terms as $category_term) {
            if ($category = self::getCategoryBy('id', $category_term->term_id)) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    public static function getTags($args = array())
    {
        if (!isset($args['hide_empty'])) {
            $args['hide_empty'] = 0;
        }

        $tag_terms = getTerms(static::TAXONOMY_TAG_KEY, $args);
        if (is_wp_error($tag_terms) || empty($tag_terms)) {
            return array();
        }

        $tags = array();
        foreach ($tag_terms as $tag_term) {
            if ($tag = self::getTagBy('id', $tag_term->term_id)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    public static function getCategoryBy($field, $value)
    {
        $category = get_term_by($field, $value, static::TAXONOMY_CAT_KEY);

        if (is_wp_error($category) || empty($category)) {
            return null;
        }

        $category->feed_ids = array();
        $unencoded_description = Util::getUnencodedText($category->description);
        if (is_array($unencoded_description)) {
            foreach ($unencoded_description as $key => $value) {
                $category->$key = $value;
            }
        }

        return $category;
    }

    public static function getTagBy($field = '', $value = '')
    {
        $tag = get_term_by($field, $value, static::TAXONOMY_TAG_KEY);

        if (is_wp_error($tag) || empty($tag)) {
            return null;
        }

        $tag->feed_ids = array();
        $unencoded_description = Util::getUnencodedText($tag->description);
        if (is_array($unencoded_description)) {
            foreach ($unencoded_description as $key => $value) {
                $tag->$key = $value;
            }
        }

        return $tag;
    }

    public static function addCategory($args = [], $feedIds = [])
    {
        if (!isset($args['name'])) {
            return new WP_Error('invalid', __('Eine Kategorie muss einen Namen haben.', 'rrze-calendar'));
        }

        $name = $args['name'];
        $default = array(
            'name' => '',
            'slug' => sanitize_title($name),
            'description' => '',
        );
        $args = array_merge($default, $args);

        $args_to_encode = array(
            'description' => $args['description'],
            'feed_ids' => array_unique($feedIds),
        );

        $encoded_description = Util::getEncodedText($args_to_encode);
        $args['description'] = $encoded_description;
        $category = wp_insert_term($name, static::TAXONOMY_CAT_KEY, $args);

        if (is_wp_error($args)) {
            return $category;
        }

        return self::getCategoryBy('id', $category['term_id']);
    }

    public static function addTag($args = array(), $feedIds = array())
    {
        if (!isset($args['name'])) {
            return new WP_Error('invalid', __('Ein Schlagwort muss einen Namen haben.', 'rrze-calendar'));
        }

        $name = $args['name'];
        $default = array(
            'name' => '',
            'slug' => sanitize_title($name),
            'description' => '',
        );
        $args = array_merge($default, $args);

        $args_to_encode = array(
            'description' => $args['description'],
            'feed_ids' => array_unique($feedIds),
        );

        $encoded_description = Util::getEncodedText($args_to_encode);
        $args['description'] = $encoded_description;
        $tag = wp_insert_term($name, static::TAXONOMY_TAG_KEY, $args);

        if (is_wp_error($args)) {
            return $tag;
        }

        return self::getTagBy('id', $tag['term_id']);
    }

    public static function updateCategory($id, $args = null, $feeds = null)
    {
        $category = self::getCategoryBy('id', $id);
        if (!$category) {
            return new WP_Error('invalid', __('Die Kategorie existiert nicht.', 'rrze-calendar'));
        }

        $args_to_encode = array();
        $args_to_encode['feed_ids'] = !empty($feeds) && is_array($feeds) ? array_unique($feeds) : array_unique($category->feed_ids);
        $args_to_encode['description'] = isset($args['description']) ? $args['description'] : $category->description;

        $encoded_description = Util::getEncodedText($args_to_encode);
        $args['description'] = $encoded_description;

        $category = wp_update_term($id, static::TAXONOMY_CAT_KEY, $args);
        if (is_wp_error($category)) {
            return $category;
        }

        return self::getCategoryBy('id', $category['term_id']);
    }

    public static function updateTag($id, $args = null, $feeds = null)
    {
        $tag = self::getTagBy('id', $id);
        if (!$tag) {
            return new WP_Error('invalid', __('Das Schlagwort existiert nicht.', 'rrze-calendar'));
        }

        $args_to_encode = array();
        $args_to_encode['feed_ids'] = !empty($feeds) && is_array($feeds) ? array_unique($feeds) : array();
        $args_to_encode['description'] = isset($args['description']) ? $args['description'] : $tag->description;

        $encoded_description = Util::getEncodedText($args_to_encode);
        $args['description'] = $encoded_description;

        $tag = wp_update_term($id, static::TAXONOMY_TAG_KEY, $args);
        if (is_wp_error($tag)) {
            return $tag;
        }

        return self::getTagBy('id', $tag['term_id']);
    }

    public static function deleteCategory($category_id)
    {
        $category = self::getCategoryBy('id', $category_id);
        if (!$category) {
            return false;
        }

        $retval = wp_delete_term($category_id, static::TAXONOMY_CAT_KEY);
        return $retval;
    }

    public static function deleteTag($tag_id)
    {
        $tag = self::getTagBy('id', $tag_id);
        if (!$tag) {
            return false;
        }

        $retval = wp_delete_term($tag_id, static::TAXONOMY_TAG_KEY);
        return $retval;
    }

    public static function addFeedToCategory($feedId, $term_id)
    {
        $category = self::getCategoryBy('id', $term_id);
        if (!$category) {
            return false;
        }

        $category->feed_ids[] = $feedId;
        $retval = self::updateCategory($term_id, null, $category->feed_ids);

        if (is_wp_error($retval)) {
            return $retval;
        }

        return true;
    }

    public static function addFeedToTag($feedId, $term_id)
    {
        $tag = self::getTagBy('id', $term_id);
        if (!$tag) {
            return false;
        }

        $tag->feed_ids[] = $feedId;
        $retval = self::updateTag($term_id, null, $tag->feed_ids);

        if (is_wp_error($retval)) {
            return $retval;
        }

        return true;
    }

    public static function removeFeedFromCategory($feedId, $term_id)
    {
        $category = self::getCategoryBy('id', $term_id);
        if (!$category) {
            return false;
        }

        foreach ($category->feed_ids as $key => $v) {
            if ($v == $feedId) {
                unset($category->feed_ids[$key]);
            }
        }

        $retval = self::updateCategory($term_id, null, $category->feed_ids);

        if (is_wp_error($retval)) {
            return $retval;
        }

        return true;
    }

    public static function removeFeedFromTag($feedId, $term_id)
    {
        $tag = self::getTagBy('id', $term_id);
        if (!$tag) {
            return false;
        }

        foreach ($tag->feed_ids as $key => $v) {
            if ($v == $feedId) {
                unset($tag->feed_ids[$key]);
            }
        }

        $retval = self::updateTag($term_id, null, $tag->feed_ids);

        if (is_wp_error($retval)) {
            return $retval;
        }

        return true;
    }

    public static function getCategoryForFeed($feedId = 0)
    {
        $colors = Util::getColors();
        $allCategories = self::getCategories();

        if (!empty($allCategories)) {
            foreach ($allCategories as $category) {
                if (!in_array($feedId, $category->feed_ids)) {
                    continue;
                }

                $color = strtoupper(get_term_meta($category->term_id, 'color', true));
                $category->color = $color ? $color : '';
                $category->textcol = isset($colors[$color]) ? 'textcol-' . $colors[$color] : '';
                $category->bgcol = isset($colors[$color]) ? 'bgcol-' . $colors[$color] : '';

                return $category;
            }
        }

        return false;
    }

    public static function getTagsForFeed($feedId = 0, $idsOrObjects = 'ids')
    {
        $all_tags = self::getTags();

        if (!empty($all_tags)) {
            $tag_objects_or_ids = array();
            foreach ($all_tags as $tag) {
                if (!in_array($feedId, $tag->feed_ids)) {
                    continue;
                }

                if ($idsOrObjects == 'ids') {
                    $tag_objects_or_ids[] = (int) $tag->term_id;
                } elseif ($idsOrObjects == 'objects') {
                    $tag_objects_or_ids[] = $tag;
                }
            }
            return $tag_objects_or_ids;
        } else {
            return false;
        }
    }
}
