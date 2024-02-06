<?php

namespace RRZE\Calendar\Options;

defined('ABSPATH') || exit;

use RRZE\Calendar\Options\Fields\{
    ButtonLink,
    Checkbox,
    CheckboxMultiple,
    Choices,
    Password,
    Select,
    SelectMultiple,
    Text,
    Textarea,
    TextSecure
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

        $typeMap = [
            'text' => Text::class,
            'checkbox' => Checkbox::class,
            'checkbox-multiple' => CheckboxMultiple::class,
            'choices' => Choices::class,
            'textarea' => Textarea::class,
            'password' => Password::class,
            'select' => Select::class,
            'select-multiple' => SelectMultiple::class,
            'text-secure' => TextSecure::class,
            'button-link' => ButtonLink::class
        ];

        $this->implementation = new $typeMap[$this->type]($section, $args);
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
