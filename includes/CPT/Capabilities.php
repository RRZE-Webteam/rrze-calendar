<?php

namespace RRZE\Calendar\CPT;

defined('ABSPATH') || exit;

class Capabilities
{
    protected static function currentCptArgs()
    {
        return [
            CalendarEvent::POST_TYPE => [
                'capability_type' => CalendarEvent::POST_TYPE,
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
                    // // Primitive capabilities used within map_meta_cap()
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
                    // // Primitive capabilities used within map_meta_cap()
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

    protected static function defaultCptArgs()
    {
        return [
            'capability_type' => 'post',
            'capabilities' => [],
            'map_meta_cap' => true
        ];
    }

    protected static function cptArgs(string $cpt)
    {
        $current = self::currentCptArgs();
        $default = self::defaultCptArgs();
        return isset($current[$cpt]) ? $current[$cpt] : $default;
    }

    protected static function getCptArgs(string $cpt): object
    {
        $cptArgs = self::cptArgs($cpt);
        $args = [
            'capability_type' => $cptArgs['capability_type'],
            'capabilities' => $cptArgs['capabilities'],
            'map_meta_cap' => $cptArgs['map_meta_cap']
        ];
        return (object) $args;
    }

    public static function getCurrentCptArgs()
    {
        return self::currentCptArgs();
    }

    public static function getCptCapabilityType(string $cpt)
    {
        $cptArgs = self::cptArgs($cpt);
        return $cptArgs['capability_type'];
    }

    public static function getCptCustomCaps(string $cpt): array
    {
        $cptArgs = self::cptArgs($cpt);
        return $cptArgs['capabilities'];
    }

    public static function getCptMapMetaCap(string $cpt): bool
    {
        $cptArgs = self::cptArgs($cpt);
        return $cptArgs['map_meta_cap'];
    }

    public static function getCptCaps(string $cpt): object
    {
        return get_post_type_capabilities(self::getCptArgs($cpt));
    }
}
