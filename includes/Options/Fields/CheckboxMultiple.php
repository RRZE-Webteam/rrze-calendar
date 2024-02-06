<?php

namespace RRZE\Calendar\Options\Fields;

defined('ABSPATH') || exit;

class CheckboxMultiple extends Field
{
    public $template = 'checkbox-multiple';

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
