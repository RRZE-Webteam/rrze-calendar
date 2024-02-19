<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;

use RRZE\WP\Settings\Options\{
    Checkbox,
    CheckboxMultiple,
    Password,
    RadioGroup,
    Select,
    SelectMultiple,
    Text,
    Textarea
};

class Option
{
    /**
     * @var Section
     */
    public $section;

    /**
     * @var string
     */
    public $type;

    /**
     * @var array
     */
    public $args = [];

    /**
     * @var mixed
     */
    public $implementation;

    public function __construct(Section $section, string $type, array $args = [])
    {
        $this->section = $section;
        $this->type = $type;
        $this->args = $args;

        $typeMap = apply_filters('rrze_wp_settings_option_type_map', [
            'checkbox' => Checkbox::class,
            'checkbox-multiple' => CheckboxMultiple::class,
            'password' => Password::class,
            'radio-group' => RadioGroup::class,
            'select' => Select::class,
            'select-multiple' => SelectMultiple::class,
            'text' => Text::class,
            'textarea' => Textarea::class
        ]);

        if (isset($typeMap[$this->type])) {
            $this->implementation = new $typeMap[$this->type]($section, $args);
        } else {
            $this->implementation = null;
        }
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

        return is_null($this->implementation) ?: $this->implementation->sanitize($value);
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

        return is_null($this->implementation) ?: $this->implementation->validate($value);
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

        echo is_null($this->implementation) ?: $this->implementation->render();
    }
}
