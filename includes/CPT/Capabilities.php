<?php

declare(strict_types=1);

namespace RRZE\Calendar\CPT;

defined('ABSPATH') || exit;

/**
 * Centralized capability definitions for RRZE Calendar CPTs.
 *
 * This class does not assign capabilities to roles.
 * It only provides capability mappings and helpers for CPT registration
 * and capability inspection.
 */
final class Capabilities
{
    /**
     * Capability configuration for all supported CPTs.
     *
     * @return array<string, array{
     *     capability_type: string,
     *     capabilities: array<string, string>,
     *     map_meta_cap: bool
     * }>
     */
    protected static function currentCptArgs(): array
    {
        return [
            CalendarEvent::POST_TYPE => self::buildArgs(
                'post',
                [
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
                    'create_posts'           => 'edit_posts',
                ]
            ),
            CalendarFeed::POST_TYPE => self::buildArgs(
                'page',
                [
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
                    'create_posts'           => 'edit_pages',
                ]
            ),
        ];
    }

    /**
     * Default capability configuration used as fallback.
     *
     * @return array{
     *     capability_type: string,
     *     capabilities: array<string, string>,
     *     map_meta_cap: bool
     * }
     */
    protected static function defaultCptArgs(): array
    {
        return [
            'capability_type' => 'post',
            'capabilities'    => [],
            'map_meta_cap'    => true,
        ];
    }

    /**
     * Build CPT capability args.
     *
     * @param string $capabilityType Capability type for the CPT.
     * @param array<string, string> $capabilities Custom capability mapping.
     * @param bool $mapMetaCap Whether WordPress should map meta caps.
     * @return array{
     *     capability_type: string,
     *     capabilities: array<string, string>,
     *     map_meta_cap: bool
     * }
     */
    protected static function buildArgs(
        string $capabilityType,
        array $capabilities,
        bool $mapMetaCap = true
    ): array {
        return [
            'capability_type' => $capabilityType,
            'capabilities'    => $capabilities,
            'map_meta_cap'    => $mapMetaCap,
        ];
    }

    /**
     * Get capability args for a given CPT or fallback to defaults.
     *
     * @param string $cpt CPT name.
     * @return array{
     *     capability_type: string,
     *     capabilities: array<string, string>,
     *     map_meta_cap: bool
     * }
     */
    protected static function cptArgs(string $cpt): array
    {
        $current = self::currentCptArgs();

        return $current[$cpt] ?? self::defaultCptArgs();
    }

    /**
     * Get CPT args as object for get_post_type_capabilities().
     *
     * @param string $cpt CPT name.
     * @return object
     */
    protected static function getCptArgs(string $cpt): object
    {
        $cptArgs = self::cptArgs($cpt);

        return (object) [
            'capability_type' => $cptArgs['capability_type'],
            'capabilities'    => $cptArgs['capabilities'],
            'map_meta_cap'    => $cptArgs['map_meta_cap'],
        ];
    }

    /**
     * Get all current CPT capability args.
     *
     * @return array<string, array{
     *     capability_type: string,
     *     capabilities: array<string, string>,
     *     map_meta_cap: bool
     * }>
     */
    public static function getCurrentCptArgs(): array
    {
        return self::currentCptArgs();
    }

    /**
     * Get CPT capability type.
     *
     * @param string $cpt CPT name.
     * @return string
     */
    public static function getCptCapabilityType(string $cpt): string
    {
        return self::cptArgs($cpt)['capability_type'];
    }

    /**
     * Get CPT custom capabilities.
     *
     * @param string $cpt CPT name.
     * @return array<string, string>
     */
    public static function getCptCustomCaps(string $cpt): array
    {
        return self::cptArgs($cpt)['capabilities'];
    }

    /**
     * Get CPT map_meta_cap setting.
     *
     * @param string $cpt CPT name.
     * @return bool
     */
    public static function getCptMapMetaCap(string $cpt): bool
    {
        return self::cptArgs($cpt)['map_meta_cap'];
    }

    /**
     * Get resolved post type capabilities for a CPT.
     *
     * @param string $cpt CPT name.
     * @return object
     */
    public static function getCptCaps(string $cpt): object
    {
        return get_post_type_capabilities(self::getCptArgs($cpt));
    }
}
