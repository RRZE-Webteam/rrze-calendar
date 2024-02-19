<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;

class Error
{
    public $settings;

    public $error;

    public function __construct($settings)
    {
        $this->error = new \WP_Error;
        $this->settings = $settings;
    }

    public function getAll()
    {
        global $wp_settings_error;

        return $wp_settings_error[$this->settings->optionName] ?? false;
    }

    public function get($key)
    {
        $errors = $this->getAll();

        if (! is_wp_error($errors)) {
            return;
        }

        return $errors->get_error_message($key);
    }

    public function add($key, $message)
    {
        global $wp_settings_error;

        $this->error->add($key, $message);

        $wp_settings_error[$this->settings->optionName] = $this->error;
    }
}
