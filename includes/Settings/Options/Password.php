<?php

namespace RRZE\Calendar\Settings\Options;

defined('ABSPATH') || exit;

use RRZE\Calendar\Settings\Encryption;

class Password extends Type
{
    public $template = 'password';

    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;

        return $value ? Encryption::decrypt($value) : null;
    }

    public function sanitize($value)
    {
        return Encryption::encrypt($value);
    }
}
