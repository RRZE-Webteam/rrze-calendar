<?php

namespace RRZE\Calendar\CPT;

defined('ABSPATH') || exit;

class Capabilities
{
    protected static function currentCptArgs()
    {
        return [
            'calendar_feed' => [
                'capability_type' => ['page', 'pages'],
                'capabilities' => [],
                'map_meta_cap' => true
            ]
        ];
    }

    protected static function defaultCptArgs()
    {
        return [
            'capability_type' => 'post',
            'capabilities' => [],
            'map_meta_cap' => false
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
