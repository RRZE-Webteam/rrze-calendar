<?php

namespace RRZE\WP\Settings\Options;

class Checkbox extends OptionAbstract
{
    public $template = 'checkbox';

    public function getValueAttribute()
    {
        return '1';
    }

    public function is_checked()
    {
        return parent::getValueAttribute();
    }
}
