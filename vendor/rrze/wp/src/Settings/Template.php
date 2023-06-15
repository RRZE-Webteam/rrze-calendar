<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;

class Template
{
    public static function include($file, $variables = [])
    {
        foreach ($variables as $name => $value) {
            ${$name} = $value;
        }

        $full_path = __DIR__ . "/templates/{$file}.php";

        if (!file_exists($full_path)) {
            return;
        }

        ob_start();

        include $full_path;

        echo apply_filters('rrze_wp_settings_template_render', ob_get_clean(), $file, $variables);
    }
}
