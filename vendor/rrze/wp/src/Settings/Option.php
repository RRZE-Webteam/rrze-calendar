<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;

use RRZE\WP\Settings\Options\{
    Checkbox,
    Choices,
    CodeEditor,
    Color,
    Select,
    SelectMultiple,
    Text,
    Textarea,
    WPEditor
};

class Option
{
    public $section;

    public $type;

    public $args = [];

    public $implementation;

    public function __construct($section, $type, $args = [])
    {
        $this->section = $section;
        $this->type = $type;
        $this->args = $args;

        $type_map = apply_filters('rrze_wp_settings_option_type_map', [
            'text' => Text::class,
            'checkbox' => Checkbox::class,
            'choices' => Choices::class,
            'textarea' => Textarea::class,
            'wp-editor' => WPEditor::class,
            'code-editor' => CodeEditor::class,
            'select' => Select::class,
            'select-multiple' => SelectMultiple::class,
            'color' => Color::class,
        ]);

        $this->implementation = new $type_map[$this->type]($section, $args);
    }

    public function getArg($key, $fallback = null)
    {
        return $this->args[$key] ?? $fallback;
    }

    public function sanitize($value)
    {
        if (is_callable($this->getArg('sanitize'))) {
            return $this->getArg('sanitize')($value);
        }

        return $this->implementation->sanitize($value);
    }

    public function validate($value)
    {
        if (is_array($this->getArg('validate'))) {
            foreach ($this->getArg('validate') as $validate) {
                if (!is_callable($validate['callback'])) {
                    continue;
                }

                $valid = $validate['callback']($value);

                if (!$valid) {
                    $this->section->tab->settings->errors->add($this->getArg('name'), $validate['feedback']);

                    return false;
                }
            }

            return true;
        }

        if (is_callable($this->getArg('validate'))) {
            return $this->getArg('validate')($value);
        }

        return $this->implementation->validate($value);
    }

    public function render()
    {
        if (is_callable($this->getArg('visible')) && $this->getArg('visible')() === false) {
            return;
        }

        if (is_callable($this->getArg('render'))) {
            echo $this->getArg('render')($this->implementation);

            return;
        }

        echo $this->implementation->render();
    }
}
