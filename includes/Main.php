<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\{CalendarEvent, CalendarFeed};
use RRZE\Calendar\Shortcodes\Shortcode;
use RRZE\Calendar\ICS\Export;

/**
 * Main class
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
        // Register the 'CalendarEvent' and 'CalendarFeed' custom post types.
        add_action('init', fn() => [
            CalendarEvent::registerPostType(),
            CalendarFeed::registerPostType(),
        ]);

        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
        add_action('wp_enqueue_scripts', [$this, 'wpEnqueueScripts']);

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
     * Enqueue scripts and styles for the frontend.
     * @return void
     */
    public function wpEnqueueScripts()
    {
        wp_register_style(
            'rrze-calendar-sc-calendar',
            plugins_url('build/calendar.style.css', plugin()->getBasename()),
            [],
            plugin()->getVersion(true)
        );

        $assetFile = include(plugin()->getPath('build') . 'calendar.asset.php');
        wp_register_script(
            'rrze-calendar-sc-calendar',
            plugins_url('build/calendar.js', plugin()->getBasename()),
            $assetFile['dependencies'],
            plugin()->getVersion(true)
        );
        wp_localize_script('rrze-calendar-sc-calendar', 'rrze_calendar_i18n', array(
            'hide_past_events' => __('Hide past events', 'rrze-calendar'),
            'show_past_events' => __('Show past events', 'rrze-calendar'),
        ));
        wp_localize_script('rrze-calendar-sc-calendar', 'rrze_calendar_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rrze-calendar-ajax-nonce'),
        ]);

        wp_register_style(
            'rrze-calendar-sc-events',
            plugins_url('build/events.style.css', plugin()->getBasename()),
            [],
            plugin()->getVersion(true)
        );
    }
}
