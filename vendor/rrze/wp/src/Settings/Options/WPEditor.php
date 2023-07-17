<?php

namespace RRZE\WP\Settings\Options;

class WPEditor extends OptionAbstract
{
    public $template = 'wp-editor';

    public function sanitize($value)
    {
        return $value;
    }
}
