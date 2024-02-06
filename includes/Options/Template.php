<?php

namespace RRZE\Calendar\Options;

defined('ABSPATH') || exit;

class Template
{
    public static function include($file, $variables = [])
    {
        foreach ($variables as $name => $value) {
            ${$name} = $value;
        }

        $path = __DIR__ . "/templates/{$file}.php";
        if (!file_exists($path)) {
            return;
        }

        ob_start();

        include $path;

        echo ob_get_clean();
    }
}
