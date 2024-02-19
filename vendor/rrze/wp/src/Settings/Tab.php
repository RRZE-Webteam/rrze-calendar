<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;

class Tab
{
    public $settings;

    public $title;

    public $slug;

    public $sections = [];

    public function __construct($settings, $title, $slug = null)
    {
        $this->title = $title;
        $this->settings = $settings;

        if ($this->slug === null) {
            $this->slug = sanitize_title($title);
        }
    }

    public function addSection($title, $args = [])
    {
        $section = new Section($this, $title, $args);

        $this->sections[] = $section;

        return $section;
    }

    public function getSectionLinks()
    {
        return array_filter($this->sections, function ($section) {
            return $section->as_link;
        });
    }

    public function containsOnlySectionLinks()
    {
        return count($this->getSectionLinks()) === count($this->sections);
    }

    public function getSectionByName($name)
    {
        foreach ($this->sections as $section) {
            if ($section->slug == $name) {
                return $section;
            }
        }

        return false;
    }

    public function getActiveSection()
    {
        if (empty($this->getSectionLinks())) {
            return;
        }

        if (isset($_REQUEST['section'])) {
            return $this->getSectionByName($_REQUEST['section']);
        }

        if ($this->containsOnlySectionLinks()) {
            return $this->sections[0];
        }
    }

    public function getActiveSections()
    {
        if (!isset($_REQUEST['section']) && $this->containsOnlySectionLinks()) {
            return [$this->sections[0]];
        }

        return \array_filter($this->sections, function ($section) {
            if (isset($_REQUEST['section'])) {
                return $section->as_link && $_REQUEST['section'] == $section->slug;
            }

            return !$section->as_link;
        });
    }
}
