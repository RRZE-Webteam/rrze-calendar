<?php

namespace RRZE\Calendar\Settings;

defined('ABSPATH') || exit;

class Template
{
    public static function include($fileName, $vars = [])
    {
        foreach ($vars as $name => $value) {
            ${$name} = $value;
        }

        $path = __DIR__ . "/templates/{$fileName}.php";
        if (!file_exists($path)) {
            return;
        }

        ob_start();

        include $path;

        echo apply_filters('rrze_calendar_settings_template_include', ob_get_clean(), $fileName, $vars);
    }
}
