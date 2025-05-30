<?php

/*
Plugin Name:        RRZE Calendar
Plugin URI:         https://github.com/RRZE-Webteam/rrze-calendar
Version:            2.4.1
Description:        Administration of local events and import of public events.
Author:             RRZE Webteam
Author URI:         https://blogs.fau.de/webworking/
License:            GNU General Public License Version 3
License URI:        https://www.gnu.org/licenses/gpl-3.0.html
Text Domain:        rrze-calendar
Domain Path:        /languages
Requires at least:  6.7
Requires PHP:       8.2
*/

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\CalendarEvent;
use RRZE\Calendar\CPT\CalendarFeed;

// Composer autoloader
require_once 'vendor/autoload.php';

// Load the plugin's text domain for localization.
add_action('init', fn() => load_plugin_textdomain('rrze-calendar', false, dirname(plugin_basename(__FILE__)) . '/languages'));

// Register activation hook for the plugin
register_activation_hook(__FILE__, __NAMESPACE__ . '\activation');

// Register deactivation hook for the plugin
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivation');

/**
 * Add an action hook for the 'plugins_loaded' hook.
 * This code hooks into the 'plugins_loaded' action hook to execute a callback function when
 * WordPress has fully loaded all active plugins and the theme's functions.php file.
 */
add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

/**
 * Activation callback function.
 * @return void
 */
function activation()
{
    settings()->loaded();
    // Register the 'CalendarEvent' and 'CalendarFeed' custom post types and flush rewrite rules.
    CalendarEvent::registerPostType();
    CalendarFeed::registerPostType();
    flush_rewrite_rules();
}

/**
 * Deactivation callback function.
 * This will ensure that the rewrite rules are properly flushed when the plugin is deactivated.
 * @return void
 */
function deactivation()
{
    flush_rewrite_rules();
}

/**
 * Instantiate Plugin class.
 * @return object Plugin
 */
function plugin()
{
    static $instance;
    if (null === $instance) {
        $instance = new Plugin(__FILE__);
    }
    return $instance;
}

/**
 * Instantiate Settings class.
 * @return object Settings
 */
function settings()
{
    static $instance;
    if (null === $instance) {
        $instance = new Settings();
    }
    return $instance;
}

/**
 * Check system requirements for the plugin.
 * This method checks if the server environment meets the minimum WordPress and PHP version requirements
 * for the plugin to function properly.
 * @return string An error message string if requirements are not met, or an empty string if requirements are satisfied.
 */
function systemRequirements(): string
{
    // Get the global WordPress version.
    global $wp_version;

    // Get the PHP version.
    $phpVersion = phpversion();

    // Initialize an error message string.
    $error = '';

    // Check if the WordPress version is compatible with the plugin's requirement.
    if (!is_wp_version_compatible(plugin()->getRequiresWP())) {
        $error = sprintf(
            /* translators: 1: Server WordPress version number, 2: Required WordPress version number. */
            __('The server is running WordPress version %1$s. The plugin requires at least WordPress version %2$s.', 'rrze-calendar'),
            $wp_version,
            plugin()->getRequiresWP()
        );
    } elseif (!is_php_version_compatible(plugin()->getRequiresPHP())) {
        // Check if the PHP version is compatible with the plugin's requirement.
        $error = sprintf(
            /* translators: 1: Server PHP version number, 2: Required PHP version number. */
            __('The server is running PHP version %1$s. The plugin requires at least PHP version %2$s.', 'rrze-calendar'),
            $phpVersion,
            plugin()->getRequiresPHP()
        );
    }

    // Return the error message string, which will be empty if requirements are satisfied.
    return $error;
}

/**
 * Handle the loading of the plugin.
 * This function is responsible for initializing the plugin, loading text domains for localization,
 * checking system requirements, and displaying error notices if necessary.
 * @return void
 */
function loaded()
{
    // Trigger the 'loaded' method of the main plugin instance.
    plugin()->loaded();

    // Check system requirements and store any error messages.
    if ($error = systemRequirements()) {
        // If there is an error, add an action to display an admin notice with the error message.
        add_action('admin_init', function () use ($error) {
            // Check if the current user has the capability to activate plugins.
            if (current_user_can('activate_plugins')) {
                // Get plugin data to retrieve the plugin's name.
                $pluginName = plugin()->getName();

                // Determine the admin notice tag based on network-wide activation.
                $tag = is_plugin_active_for_network(plugin()->getBaseName()) ? 'network_admin_notices' : 'admin_notices';

                // Add an action to display the admin notice.
                add_action($tag, function () use ($pluginName, $error) {
                    printf(
                        '<div class="notice notice-error"><p>' .
                            /* translators: 1: The plugin name, 2: The error string. */
                            esc_html__('Plugins: %1$s: %2$s', 'rrze-calendar') .
                            '</p></div>',
                        $pluginName,
                        $error
                    );
                });
            }
        });

        // Return to prevent further initialization if there is an error.
        return;
    }

    // If there are no errors, create an instance of the 'Main' class and trigger its 'loaded' method.
    (new Main)->loaded();

    add_action('init', __NAMESPACE__ . '\createBlocks');
    add_filter('block_categories_all', __NAMESPACE__ . '\rrze_block_category', 10, 2);
}

function createBlocks(): void {
    register_block_type( __DIR__ . '/build/block' );
    $script_handle_calendar = generate_block_asset_handle( 'rrze-calendar/calendar', 'editorScript' );
    wp_set_script_translations( $script_handle_calendar, 'rrze-calendar', plugin_dir_path( __FILE__ ) . 'languages' );
}

/**
 * Adds custom block category if not already present.
 *
 * @param array   $categories Existing block categories.
 * @param WP_Post $post       Current post object.
 * @return array Modified block categories.
 */
function rrze_block_category($categories, $post) {
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

// Register the Custom RRZE Category, if it is not set by another plugin
add_filter('block_categories_all', __NAMESPACE__ . '\rrze_block_category', 10, 2);
