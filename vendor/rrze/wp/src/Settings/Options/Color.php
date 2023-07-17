<?php

namespace RRZE\WP\Settings\Options;

use RRZE\WP\Settings\Worker;

class Color extends OptionAbstract
{
    public $template = 'color';

    public function __construct($section, $args = [])
    {
        add_action('rrze_wp_settings_before_render_settings_page', [$this, 'enqueue']);

        parent::__construct($section, $args);
    }

    public function enqueue()
    {
        Worker::add('wp-color-picker', function () {
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_style('wp-color-picker');

            wp_add_inline_script('wp-color-picker', 'jQuery(function($){
                $(\'.wps-color-picker\').wpColorPicker();
            })');
        });
    }
}
