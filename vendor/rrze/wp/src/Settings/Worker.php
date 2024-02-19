<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;

class Worker
{
    protected static Builder $builder;

    public static function setBuilder($builder)
    {
        static::$builder = $builder;
    }

    public static function builder()
    {
        return static::$builder;
    }

    public static function add($handle, $callback)
    {
        static::builder()->add($handle, $callback);
    }

    public function remove($handle)
    {
        static::builder()->remove($handle);
    }

    public static function enqueue()
    {
        static::builder()->enqueue();
    }
}
