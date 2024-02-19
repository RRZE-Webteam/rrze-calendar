<?php

namespace RRZE\WP\Settings\Options;

defined('ABSPATH') || exit;

use RRZE\WP\Settings\Template;

abstract class Type
{
    public $section;

    public $args = [];

    public $template;

    public function __construct($section, $args = [])
    {
        $this->section = $section;
        $this->args = $args;
    }

    public function render()
    {
        return Template::include('options/' . $this->template, ['option' => $this]);
    }

    public function hasError()
    {
        return $this->section->tab->settings->errors->get($this->getArg('name'));
    }

    public function sanitize($value)
    {
        return sanitize_text_field($value);
    }

    public function validate($value)
    {
        return true;
    }

    public function getArg($key, $fallback = null)
    {
        if (empty($this->args[$key])) {
            return $fallback;
        }

        if (is_callable($this->args[$key])) {
            return $this->args[$key]();
        }

        return $this->args[$key];
    }

    public function getLabel()
    {
        return esc_attr($this->getArg('label'));
    }

    public function getIdAttribute()
    {
        return $this->getArg('id', sanitize_title(str_replace('[', '_', $this->getNameAttribute())));
    }

    public function getName()
    {
        return $this->getArg('name');
    }

    public function getPlaceholderAttribute()
    {
        $placeholder = $this->getArg('placeholder') ?? null;

        return $placeholder ?: null;
    }

    public function getCss()
    {
        return $this->getArg('css', []);
    }

    public function getInputClassAttribute()
    {
        $class = $this->getCss()['input_class'] ?? null;

        return !empty($class) ? 'class="' . esc_attr($class) . '"' : null;
    }

    public function getLabelClassAttribute()
    {
        $class = $this->getCss()['label_class'] ?? null;

        return !empty($class) ? 'class="' . esc_attr($class) . '"' : null;
    }

    public function getNameAttribute()
    {
        return $this->section->tab->settings->optionName . '[' . $this->getArg('name') . ']';
    }

    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;

        return $value ? $value : $this->args['default'] ?? null;
    }
}
