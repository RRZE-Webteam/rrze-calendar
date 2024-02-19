<?php

namespace RRZE\WP\Settings\Options;

defined('ABSPATH') || exit;

class Textarea extends Type
{
    public $template = 'textarea';

    public function sanitize($value)
    {
        return sanitize_textarea_field($value);
    }
}
