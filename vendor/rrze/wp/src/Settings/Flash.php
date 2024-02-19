<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;

class Flash
{
    public $settings;

    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    public function has()
    {
        global $wp_settings_flash;

        return $wp_settings_flash[$this->settings->optionName] ?? null;
    }

    public function set($status, $message)
    {
        global $wp_settings_flash;

        $wp_settings_flash[$this->settings->optionName] = compact('status', 'message');
    }
}
