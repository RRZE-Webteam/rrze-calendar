<?php

namespace RRZE\Calendar\CPT;

defined('ABSPATH') || exit;

/**
 * Capabilities class
 * @package RRZE\Calendar\CPT
 */
class Capabilities
{
    /**
     * Get current CPT args
     * @return array The current CPT args
     */
    protected static function currentCptArgs()
    {
        return [
            CalendarEvent::POST_TYPE => [
                'capability_type' => CalendarEvent::POST_TYPE,
                'capabilities' => [
                    // Meta capabilities
                    'edit_post'              => 'edit_post',
                    'read_post'              => 'read_post',
                    'delete_post'            => 'delete_post',
                    // Primitive capabilities used outside of map_meta_cap()
                    'edit_posts'             => 'edit_posts',
                    'edit_others_posts'      => 'edit_others_posts',
                    'publish_posts'          => 'publish_posts',
                    'read_private_posts'     => 'read_private_posts',
                    // Primitive capabilities used within map_meta_cap()
                    'read'                   => 'read',
                    'delete_posts'           => 'delete_posts',
                    'delete_private_posts'   => 'delete_private_posts',
                    'delete_published_posts' => 'delete_published_posts',
                    'delete_others_posts'    => 'delete_others_posts',
                    'edit_private_posts'     => 'edit_private_posts',
                    'edit_published_posts'   => 'edit_published_posts',
                    'create_posts'           => 'edit_posts'
                ],
                'map_meta_cap' => true
            ],
            CalendarFeed::POST_TYPE => [
                'capability_type' => CalendarFeed::POST_TYPE,
                'capabilities' => [
                    // Meta capabilities
                    'edit_post'              => 'edit_page',
                    'read_post'              => 'read_page',
                    'delete_post'            => 'delete_page',
                    // Primitive capabilities used outside of map_meta_cap()
                    'edit_posts'             => 'edit_pages',
                    'edit_others_posts'      => 'edit_others_pages',
                    'publish_posts'          => 'publish_pages',
                    'read_private_posts'     => 'read_private_pages',
                    // Primitive capabilities used within map_meta_cap()
                    'read'                   => 'read',
                    'delete_posts'           => 'delete_pages',
                    'delete_private_posts'   => 'delete_private_pages',
                    'delete_published_posts' => 'delete_published_pages',
                    'delete_others_posts'    => 'delete_others_pages',
                    'edit_private_posts'     => 'edit_private_pages',
                    'edit_published_posts'   => 'edit_published_pages',
                    'create_posts'           => 'edit_pages'
                ],
                'map_meta_cap' => true
            ]
        ];
    }

    /**
     * Get default CPT args
     * @return array The default CPT args
     */
    protected static function defaultCptArgs()
    {
        return [
            'capability_type' => 'post',
            'capabilities' => [],
            'map_meta_cap' => true
        ];
    }

    /**
     * Get CPT array args
     * @param string $cpt CPT name
     * @return array The CPT args
     */
    protected static function cptArgs(string $cpt)
    {
        $current = self::currentCptArgs();
        $default = self::defaultCptArgs();
        return isset($current[$cpt]) ? $current[$cpt] : $default;
    }

    /**
     * Get CPT object args
     * @param string $cpt CPT name
     * @return object The CPT object args
     */
    protected static function getCptArgs(string $cpt)
    {
        $cptArgs = self::cptArgs($cpt);
        $args = [
            'capability_type' => $cptArgs['capability_type'],
            'capabilities' => $cptArgs['capabilities'],
            'map_meta_cap' => $cptArgs['map_meta_cap']
        ];
        return (object) $args;
    }

    /**
     * Get current CPT args
     * @return array The current CPT args
     */
    public static function getCurrentCptArgs()
    {
        return self::currentCptArgs();
    }

    /**
     * Get CPT capability type
     * @param string $cpt CPT name
     * @return string|array The capability type
     */
    public static function getCptCapabilityType(string $cpt)
    {
        $cptArgs = self::cptArgs($cpt);
        return $cptArgs['capability_type'];
    }

    /**
     * Get CPT capabilities
     * @param string $cpt CPT name
     * @return string[] Array of the capabilities
     */
    public static function getCptCustomCaps(string $cpt)
    {
        $cptArgs = self::cptArgs($cpt);
        return $cptArgs['capabilities'];
    }

    /**
     * Get CPT map meta cap
     * @param string $cpt CPT name
     * @return bool Whether to use the internal default meta capability handling
     */
    public static function getCptMapMetaCap(string $cpt)
    {
        $cptArgs = self::cptArgs($cpt);
        return $cptArgs['map_meta_cap'];
    }

    /**
     * Get CPT capabilities
     * @param string $cpt CPT name
     * @return object Object with all the capabilities as member variables
     */
    public static function getCptCaps(string $cpt): object
    {
        return get_post_type_capabilities(self::getCptArgs($cpt));
    }
}
