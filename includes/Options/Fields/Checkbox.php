<?php

namespace RRZE\Calendar\Options\Fields;

defined('ABSPATH') || exit;

class Checkbox extends Field
{
    public $template = 'checkbox';

    public function getValueAttribute()
    {
        return '1';
    }

    public function isChecked()
    {
        return parent::getValueAttribute();
    }
}
