<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\{CalendarEvent, CalendarFeed};
use RRZE\Calendar\Shortcodes\Shortcode;
use RRZE\Calendar\ICS\Export;

/**
 * Main class
 * 
 * @package RRZE\Calendar
 */
class Main
{
    /**
     * Initialize the class, registering WordPress hooks
     * @return void
     */
    public function loaded()
    {
        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        // Register the 'CalendarEvent' and 'CalendarFeed' custom post types.
        add_action('init', fn() => [
            CalendarEvent::registerPostType(),
            CalendarFeed::registerPostType(),
        ]);

        add_action('init', [$this, 'createBlocks']);
        add_filter('block_categories_all', [$this, 'blockCategory'], 10, 2);

        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        settings()->loaded();

        CalendarEvent::init();
        CalendarFeed::init();

        Update::init();

        new Export();

        Shortcode::init();

        Cron::init();
    }

    /**
     * Add the settings link to the list of plugins.
     * 
     * @param array $links
     * @return array
     */
    public function settingsLink($links)
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=rrze-calendar'),
            __('Settings', 'rrze-calendar')
        );
        array_unshift($links, $settingsLink);
        return $links;
    }

    /**
     * Enqueue scripts and styles for the admin area.
     * 
     * @return void
     */
    public function adminEnqueueScripts()
    {
        $screen = get_current_screen();
        if (is_null($screen)) {
            return;
        }

        if (in_array($screen->post_type, [CalendarEvent::POST_TYPE, CalendarFeed::POST_TYPE])) {
            wp_enqueue_style(
                'rrze-calendar-admin',
                plugins_url('build/admin.style.css', plugin()->getBasename()),
                [],
                plugin()->getVersion(true)
            );

            $assetFile = include(plugin()->getPath('build') . 'admin.asset.php');
            $assetFile['dependencies'] = array_merge($assetFile['dependencies'], ['wp-color-picker']);
            wp_enqueue_script(
                'rrze-calendar-admin',
                plugins_url('build/admin.js', plugin()->getBasename()),
                $assetFile['dependencies'],
                plugin()->getVersion(true)
            );
        }
    }

    /**
     * Register custom Gutenberg blocks.
     *
     * This function registers custom Gutenberg blocks for the plugin and sets up script translations.
     *
     * @return void
     */
    public function createBlocks()
    {
        register_block_type(plugin()->getPath('build/block'));
        $scriptHandle = generate_block_asset_handle('rrze-calendar/calendar', 'editorScript');
        wp_set_script_translations(
            $scriptHandle,
            'rrze-calendar',
            plugin()->getPath('languages')
        );
    }

    /**
     * Adds custom block category if not already present.
     *
     * @param array   $categories Existing block categories.
     * @param \WP_Post $post       Current post object.
     * @return array Modified block categories.
     */
    public function blockCategory($categories, $post)
    {
        // Check if there is already a RRZE category present
        foreach ($categories as $category) {
            if (isset($category['slug']) && $category['slug'] === 'rrze') {
                return $categories;
            }
        }

        $custom_category = [
            'slug'  => 'rrze',
            'title' => __('RRZE', 'rrze-calendar'),
        ];

        // Add RRZE to the end of the categories array
        $categories[] = $custom_category;

        return $categories;
    }
}
