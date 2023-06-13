<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\CalendarFeed;
use RRZE\Calendar\Shortcodes\Shortcode;

class Main
{
    /**
     * __construct
     */
    public function __construct()
    {
        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
        add_action('wp_enqueue_scripts', [$this, 'wpEnqueueScripts']);

        Update::init();

        $settings = new Settings;
        $settings->onLoaded();

        CalendarFeed::init();

        Metaboxes::init();

        Events::init();

        Endpoint::init();

        Shortcode::init();

        Cron::init();
    }

    /**
     * Add the settings link to the list of plugins.
     *
     * @param array $links
     * @return void
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

    public function adminEnqueueScripts()
    {
        $screen = get_current_screen();
        if (is_null($screen)) {
            return;
        }
        if ($screen->post_type == CalendarFeed::POST_TYPE) {
            wp_enqueue_style(
                'rrze-calendar-admin',
                plugins_url('build/admin.css', plugin()->getBasename()),
                [],
                plugin()->getVersion(true)
            );
            wp_enqueue_script(
                'rrze-calendar-admin',
                plugins_url('build/admin.js', plugin()->getBasename()),
                ['jquery', 'wp-color-picker'],
                plugin()->getVersion(true)
            );
        }
    }

    public function wpEnqueueScripts()
    {
        wp_register_style(
            'rrze-calendar-sc-calendar',
            plugins_url('build/calendar.css', plugin()->getBasename()),
            [],
            plugin()->getVersion(true)
        );
        wp_register_script(
            'rrze-calendar-sc-calendar',
            plugins_url('build/calendar.js', plugin()->getBasename()),
            ['jquery'],
            plugin()->getVersion(true)
        );
        wp_localize_script('rrze-calendar-sc-calendar', 'rrze_calendar_i18n', array(
            'hide_past_events' => __('Hide past events', 'rrze-calendar'),
            'show_past_events' => __('Show past events', 'rrze-calendar'),
        ));
        wp_register_style(
            'rrze-calendar-sc-events',
            plugins_url('build/events.css', plugin()->getBasename()),
            [],
            plugin()->getVersion(true)
        );
    }
}