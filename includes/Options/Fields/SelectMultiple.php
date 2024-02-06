<?php

namespace RRZE\Calendar\Options\Fields;

defined('ABSPATH') || exit;

class SelectMultiple extends Field
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
