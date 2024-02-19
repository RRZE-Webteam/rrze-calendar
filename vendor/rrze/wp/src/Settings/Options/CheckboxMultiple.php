<?php

namespace RRZE\WP\Settings\Options;

defined('ABSPATH') || exit;

class CheckboxMultiple extends Type
{
    public $template = 'checkbox-multiple';

    public function getNameAttribute()
    {
        $name = parent::getNameAttribute();

        return "{$name}[]";
    }

    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;
        if ($value === false) {
            $value = [$this->getArg('default')];
        }
        return $value;
    }

    public function sanitize($value)
    {
        return (array) $value;
    }
}
