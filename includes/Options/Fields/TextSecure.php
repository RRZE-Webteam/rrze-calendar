<?php

namespace RRZE\Calendar\Options\Fields;

defined('ABSPATH') || exit;

use RRZE\Calendar\Options\Encryption;

class TextSecure extends Field
{
    public $template = 'text-secure';

    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;

        return $value ? Encryption::decrypt($value) : null;
    }
        
    public function sanitize($value)
    {
        if (false !== stripos($value, '***')) {
            $value = $this->getValueAttribute();
        }
        $value = sanitize_text_field($value);
        return Encryption::encrypt($value);
    }
}
