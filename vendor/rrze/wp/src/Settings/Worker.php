<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;

class Worker
{
    protected static WorkBuilder $workBuilder;

    public static function setWorkBuilder($builder)
    {
        static::$workBuilder = $builder;
    }

    public static function builder()
    {
        return static::$workBuilder;
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
