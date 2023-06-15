<?php

namespace RRZE\WP\Settings\Options;

class SelectMultiple extends OptionAbstract
{
    public $template = 'select-multiple';

    public function getNameAttribute()
    {
        $name = parent::getNameAttribute();

        return "{$name}[]";
    }

    public function sanitize($value)
    {
        return (array) $value;
    }
}
