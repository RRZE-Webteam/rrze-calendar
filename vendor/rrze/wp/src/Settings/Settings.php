<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;

class Settings
{
    public $title;

    public $menuTitle;

    public $slug;

    public $parentSlug;

    public $capability = 'manage_options';

    public $menuIcon;

    public $menuPosition;

    public $optionName;

    public $tabs = [];

    public $errors;

    public $flash;

    public function __construct($title, $slug = null)
    {
        $this->title = $title;
        $this->optionName = strtolower(str_replace('-', '_', sanitize_title($this->title)));
        $this->slug = $slug;

        if ($this->slug === null) {
            $this->slug = sanitize_title($title);
        }
    }

    public function setMenuParentSlug($slug)
    {
        $this->parentSlug = $slug;
        return $this;
    }

    public function setMenuTitle($title)
    {
        $this->menuTitle = $title;
        return $this;
    }

    public function getMenuTitle()
    {
        return $this->menuTitle ?? $this->title;
    }

    public function setCapability($capability)
    {
        $this->capability = $capability;
        return $this;
    }

    public function setOptionName($name)
    {
        $this->optionName = $name;
        return $this;
    }

    public function setMenuIcon($icon)
    {
        $this->menuIcon = $icon;
        return $this;
    }

    public function setMenuPosition($position)
    {
        $this->menuPosition = $position;
        return $this;
    }

    public function addToMenu()
    {
        if ($this->parentSlug) {
            add_submenu_page(
                $this->parentSlug,
                $this->title,
                $this->getMenuTitle(),
                $this->capability,
                $this->slug,
                [$this, 'render'],
                $this->menuPosition
            );
        } else {
            add_menu_page(
                $this->title,
                $this->getMenuTitle(),
                $this->capability,
                $this->slug,
                [$this, 'render'],
                $this->menuIcon,
                $this->menuPosition
            );
        }
    }

    public function build()
    {
        $this->errors = new Error($this);
        $this->flash = new Flash($this);

        add_action('admin_init', [$this, 'save'], 20);
        add_action('admin_menu', [$this, 'addToMenu'], 20);
        add_action('admin_head', [$this, 'styling'], 20);
    }

    public function isOnSettingsPage()
    {
        $screen = get_current_screen();
        if (is_null($screen)) {
            return false;
        }

        if ($screen->base === 'settings_page_' . $this->slug) {
            return true;
        }

        return false;
    }

    public function styling()
    {
        if (!$this->isOnSettingsPage()) {
            return;
        }

        echo '<style>.rrze-wp-settings-error {color: #d63638; margin: 5px 0;}</style>';
    }

    public function getTabBySlug($slug)
    {
        foreach ($this->tabs as $tab) {
            if ($tab->slug === $slug) {
                return $tab;
            }
        }

        return false;
    }

    public function getActiveTab()
    {
        $default = $this->tabs[0] ?? false;

        if (isset($_GET['tab'])) {
            return in_array($_GET['tab'], array_map(function ($tab) {
                return $tab->slug;
            }, $this->tabs)) ? $this->getTabBySlug($_GET['tab']) : $default;
        }

        return $default;
    }

    public function addTab($title, $slug = null)
    {
        $tab = new Tab($this, $title, $slug);

        $this->tabs[] = $tab;

        return $tab;
    }

    public function addSection($title, $args = [])
    {
        if (empty($this->tabs)) {
            $tab = $this->addTab(__('Unnamed tab', 'rrze-wp-settings'));
        } else {
            $tab = end($this->tabs);
        }

        return $tab->addSection($title, $args);
    }

    public function addOption($type, $args = [])
    {
        $tab = end($this->tabs);

        if (!$tab instanceof Tab) {
            return false;
        }

        $section = end($tab->sections);

        if (!$section instanceof Section) {
            return false;
        }

        return $section->addOption($type, $args);
    }

    public function shouldMakeTabs()
    {
        return count($this->tabs) > 1;
    }

    public function getUrl()
    {
        if ($this->parentSlug && strpos($this->parentSlug, '.php') !== false) {
            return add_query_arg('page', $this->slug, admin_url($this->parentSlug));
        }

        return admin_url("admin.php?page=$this->slug");
    }

    public function getFullUrl()
    {
        $params = [];

        if ($active_tab = $this->getActiveTab()) {
            $params['tab'] = $active_tab->slug;

            if ($active_section = $active_tab->getActiveSection()) {
                $params['section'] = $active_section->slug;
            }
        }

        return add_query_arg($params, $this->getUrl());
    }

    public function renderTabMenu()
    {
        if (!$this->shouldMakeTabs()) {
            return;
        }

        Template::include('tab-menu', ['settings' => $this]);
    }

    public function renderActiveSections()
    {
        Template::include('sections', ['settings' => $this]);
    }

    public function render()
    {
        Worker::setBuilder(new Builder);

        Worker::enqueue();

        Template::include('settings-page', ['settings' => $this]);
    }

    public function save()
    {
        if (
            !isset($_POST['rrze_wp_settings_save'])
            || !wp_verify_nonce(
                $_POST['rrze_wp_settings_save'],
                'rrze_wp_settings_save_' . $this->optionName
            )
        ) {
            return;
        }

        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have enough permissions to do that.', 'rrze-wp-settings'));
        }

        $currentOptions = $this->getOptions();
        $submittedOptions = apply_filters('rrze_wp_settings_new_options', $_POST[$this->optionName] ?? [], $currentOptions);
        $newOptions = $currentOptions;

        foreach ($this->getActiveTab()->getActiveSections() as $section) {
            foreach ($section->options as $option) {
                $value = $submittedOptions[$option->implementation->getName()] ?? null;

                $valid = $option->validate($value);

                if (!$valid) {
                    continue;
                }

                $value = apply_filters('rrze_wp_settings_new_option_' . $option->implementation->getName(), $option->sanitize($value), $option->implementation);

                $newOptions[$option->implementation->getName()] = $value;
            }
        }

        $this->updateOptions($newOptions);

        $this->flash->set('success', __('Settings saved.', 'rrze-wp-settings'));
    }

    public function defaultOptions()
    {
        $options = [];
        foreach ($this->tabs as $tab) {
            foreach ($tab->sections as $section) {
                foreach ($section->options as $option) {
                    $options[$option->args['name']] = $option->args['default'] ?? null;
                }
            }
        }

        return $options;
    }

    public function getOptions()
    {
        $defaults = $this->defaultOptions();
        $options = get_option($this->optionName, []);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return $options;
    }

    public function getOption($option)
    {
        $options = $this->getOptions();
        return $options[$option] ?? null;
    }

    public function updateOptions($options)
    {
        update_option($this->optionName, $options);
        do_action('rrze_wp_settings_after_update_option', $this->optionName, $options);
    }
}
