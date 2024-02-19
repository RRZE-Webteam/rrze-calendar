<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;

class Section
{
    public $tab;

    public $as_link;

    public $title;

    public $args;

    public $slug;

    public $description;

    public $options = [];

    public function __construct($tab, $title, $args = [])
    {
        $this->tab = $tab;
        $this->title = $title;
        $this->args = $args;
        $this->slug = $this->args['slug'] ?? sanitize_title($title);
        $this->description = $this->args['description'] ?? null;
        $this->as_link = $this->args['as_link'] ?? false;
    }

    public function addOption($type, $args = [])
    {
        $option = new Option($this, $type, $args);

        $this->options[] = $option;

        return $option;
    }
}
