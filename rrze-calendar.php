<?php

/*
  Plugin Name: RRZE Calendar
  Plugin URI: https://github.com/RRZE-Webteam/rrze-calendar.git
  Version: 1.3.3
  Description: Import und Ausgabe der öffentlicher Veranstaltungen der FAU.
  Author: RRZE-Webteam
  Author URI: http://blogs.fau.de/webworking/
 */

/*
  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action('plugins_loaded', array('RRZE_Calendar', 'instance'));

register_activation_hook(__FILE__, array('RRZE_Calendar', 'activation'));
register_deactivation_hook(__FILE__, array('RRZE_Calendar', 'deactivation'));

// Sprachdateien werden eingebunden.
load_plugin_textdomain('rrze-calendar', FALSE, sprintf('%s/languages/', dirname(plugin_basename(__FILE__))));

class RRZE_Calendar {
    
    const version = '1.3.3';
    
    const feeds_table_name = 'rrze_calendar_feeds';
    const events_table_name = 'rrze_calendar_events';
    const events_cache_table_name = 'rrze_calendar_events_cache';
    
    const cron_hook = 'rrze_calendar';
    
    const option_name = 'rrze_calendar';
    const version_option_name = 'rrze_calendar_version';
    
    const php_version = '5.5'; // Minimal erforderliche PHP-Version
    const wp_version = '4.6'; // Minimal erforderliche WordPress-Version
    
    const taxonomy_cat_key = 'rrze-calendar-category';
    const taxonomy_tag_key = 'rrze-calendar-tag';
    
    const settings_error_transient = 'rrze-calendar-settings-error-';
    const settings_error_transient_expiration = 30;
    
    const notice_transient = 'rrze-calendar-notice-';
    const notice_transient_expiration = 30;
    
    public static $fau_colors = [
        '#003366' => 'default',
        '#A36B0D' => 'phil',
        '#8D1429' => 'rw',
        '#0381A2' => 'med',
        '#048767' => 'nat',
        '#6E7881' => 'tf'
        ];

    public static $plugin_file;
    
    public static $db_feeds_table;
    public static $db_events_table;
    public static $db_events_cache_table;
    public static $db_terms_table;
    public static $db_term_taxonomy_table;
    public static $db_term_relationships_table;
    public static $options;
    
    public static $messages;
        
    public static $schedule_event_recurrance;

    protected static $instance = NULL;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
        global $wpdb;

        self::$plugin_file = __FILE__;
                
        // Enthaltene Optionen.
        self::$options = self::get_options();
        
        self::db_setup();
        
        self::update_version();

        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }
        
        require_once(plugin_dir_path(self::$plugin_file) . 'includes/calendar-functions.php');        
        require_once(plugin_dir_path(self::$plugin_file) . 'includes/calendar-event.php');
        
        require_once(plugin_dir_path(self::$plugin_file) . 'includes/feeds-list-table.php');
        require_once(plugin_dir_path(self::$plugin_file) . 'includes/events-list-table.php');
        require_once(plugin_dir_path(self::$plugin_file) . 'includes/categories-list-table.php');
        require_once(plugin_dir_path(self::$plugin_file) . 'includes/tags-list-table.php');
        
        require_once(plugin_dir_path(self::$plugin_file) . 'includes/shortcodes/calendar/calendar.php');
        require_once(plugin_dir_path(self::$plugin_file) . 'includes/shortcodes/events/events.php');

        add_action('init', array(__CLASS__, 'add_endpoint'));
        add_action('template_redirect', array($this, 'endpoint_template_redirect'));
        
        add_action(self::cron_hook, array($this, 'cron_schedule_event_hook'));
        
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
        
        add_action('admin_menu', array($this, 'calendar_menu'));
           
        add_action('admin_init', array($this, 'admin_settings'));
        add_action('admin_init', array($this, 'admin_actions'));
        
        add_action('admin_notices', array($this, 'admin_notices'));

        add_filter('set-screen-option', array($this, 'list_table_set_option'), 10, 3);
        
        add_action('init', array($this, 'register_taxonomies'));
        
        add_action('admin_init', array($this, 'validate_add_category'));
        add_action('admin_init', array($this, 'validate_add_tag'));
        add_action('admin_init', array($this, 'validate_edit_category'));
        add_action('admin_init', array($this, 'validate_edit_tag'));
        add_action('admin_init', array($this, 'validate_delete_category'));
        add_action('admin_init', array($this, 'validate_delete_tag'));
        
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
            $settings_link = '<a href="' . self::options_url(array('page' => 'rrze-calendar-settings')) . '">' . esc_html(__('Einstellungen', 'rrze-calendar')) . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        });
    
        add_action('init', array($this, 'export_request'));
        
        self::$schedule_event_recurrance = [
            'hourly' => __('Stündlich', 'rrze-calendar'),
            'twicedaily' => __('Zweimal täglich', 'rrze-calendar'),
            'daily' => __('Täglich', 'rrze-calendar')
        ];
        
        self::$messages = [
            'nonce-failed' => __('Schummeln, was?', 'rrze-calendar'),
            'invalid-permissions' => __('Sie haben nicht die erforderlichen Rechte, um diese Aktion durchzuführen.', 'rrze-calendar'),
            'error-ocurred' => __('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.', 'rrze-calendar')
        ];        
    }

    /*
     * Wird durchgeführt wenn das Plugin aktiviert wird.
     * @return void
     */
    public static function activation() {
        // Überprüft die Systemanforderungen.
        self::system_requirements();
        
        // Enthaltene Optionen.
        self::$options = self::get_options();        
        
        self::db_setup();
        self::db_delta();

        self::cron_schedule_event_setup();
        
        self::add_endpoint();
        flush_rewrite_rules();
    }

    /*
     * Wird durchgeführt wenn das Plugin deaktiviert wird
     * @return void
     */
    public static function deactivation() {
        wp_clear_scheduled_hook(self::cron_hook);       
        flush_rewrite_rules();
    }

    /*
     * Überprüft die Systemanforderungen.
     * @return void
     */
    private static function system_requirements() {
        $error = '';

        // Überprüft die minimal erforderliche PHP-Version.
        if (version_compare(PHP_VERSION, self::php_version, '<')) {
            $error = sprintf(__('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', 'rrze-calendar'), PHP_VERSION, self::php_version);
        }

        // Überprüft die minimal erforderliche WP-Version.
        elseif (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
            $error = sprintf(__('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', 'rrze-calendar'), $GLOBALS['wp_version'], self::wp_version);
        }

        // Wenn die Überprüfung fehlschlägt, dann wird das Plugin automatisch deaktiviert.
        if (!empty($error)) {
            deactivate_plugins(plugin_basename(__FILE__), FALSE, TRUE);
            wp_die($error);
        }
    }

    /*
     * Aktualisierung des Plugins
     * @return void
     */
    private static function update_version() {
        $version = get_option(self::version_option_name, '0');
        
        if (version_compare($version, self::version, '<')) {
            self::db_update($version);            
            self::cron_schedule_event_setup();
        }
        
        update_option(self::version_option_name, self::version);
    }
        
    private static function cron_schedule_event_setup() {
        wp_clear_scheduled_hook(self::cron_hook);
        wp_schedule_event(time(), self::$options['schedule_event'], self::cron_hook);
    }
    
    private static function db_setup() {
        global $wpdb;
        
        self::$db_feeds_table = $wpdb->prefix . self::feeds_table_name;
        self::$db_events_table = $wpdb->prefix . self::events_table_name;
        self::$db_events_cache_table = $wpdb->prefix . self::events_cache_table_name;
    }
    
    private static function db_delta() {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE " . self::$db_feeds_table . " (
            id bigint(20) unsigned NOT NULL auto_increment,
            url varchar(255)  NOT NULL default '',
            title varchar(255)  NOT NULL default '',
            active tinyint(1) NOT NULL default '0',
            created datetime NOT NULL default '0000-00-00 00:00:00',
            modified datetime NOT NULL default '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY url (url(191))
            ) $charset_collate;";
        
        dbDelta($sql);

        $sql = "CREATE TABLE " . self::$db_events_table . " (
            id bigint(20) unsigned NOT NULL auto_increment,
            start datetime NOT NULL default '0000-00-00 00:00:00',
            end datetime,
            allday tinyint(1) NOT NULL,
            recurrence_rules longtext,
            exception_rules longtext,
            recurrence_dates longtext,
            exception_dates longtext,
            summary longtext,
            description longtext,
            location varchar(255),
            slug varchar(200) NOT NULL default '',
            ical_feed_id bigint(20) unsigned NOT NULL default 0,
            ical_feed_url varchar(255),
            ical_uid varchar(255),
            ical_source_url varchar(255),
            PRIMARY KEY  (id),
            KEY slug (slug(191)),
            KEY ical_feed_id (ical_feed_id),
            KEY ical_uid (ical_uid)
            ) $charset_collate;";
        
        dbDelta($sql);
        
        $sql = "CREATE TABLE " . self::$db_events_cache_table . " (
            id bigint(20) unsigned NOT NULL auto_increment,
            event_id bigint(20) NOT NULL default 0,
            start datetime NOT NULL default '0000-00-00 00:00:00',
            end datetime NOT NULL default '0000-00-00 00:00:00',
            ical_feed_id bigint(20) unsigned NOT NULL default 0,            
            PRIMARY KEY  (id),
            KEY ical_feed_id (ical_feed_id)
            ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    private static function db_update($version) {
        global $wpdb;
        
        if ($version < '1.1.3') {
            $wpdb->query("ALTER TABLE " . self::$db_feeds_table . " DROP INDEX url, ADD INDEX url (url(191))");
        }
    }
    
    private function get_feeds_data($output_type = OBJECT) {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM " . self::$db_feeds_table, $output_type);
    }
    
    private function get_events_data($output_type = OBJECT) {
        global $wpdb;
        
        return $wpdb->get_results("SELECT *, UNIX_TIMESTAMP(start) AS start, UNIX_TIMESTAMP(end) AS end FROM " . self::$db_events_table, $output_type);
    }
    
    /*
     * Standard Einstellungen werden definiert
     * @return array
     */
    private static function default_options() {
        $options = [
            'endpoint_slug' => 'events',
            'endpoint_name' => 'Events',
            'schedule_event' => 'hourly'
        ];

        return $options;
    }

    /*
     * Gibt die Einstellungen zurück.
     * @return object
     */
    private static function get_options() {
        $defaults = self::default_options();

        $options = (array) get_option(self::option_name);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return $options;
    }
    
    public static function add_endpoint() {
        add_rewrite_endpoint(self::$options['endpoint_slug'], EP_ROOT);
    }
    
    public function endpoint_template_redirect() {
        global $wp_query;
        
        if (!isset($wp_query->query_vars[self::$options['endpoint_slug']])) {
            return;
        }
        
        $slug = $wp_query->query_vars[self::$options['endpoint_slug']];
        $event = !empty($slug) ? $this->get_event_by_slug($slug) : NULL;
        
        if (empty($slug)) {
            if ($template = locate_template('rrze-calendar-events.php')) {
                $this->load_template($template);
            } else {
                wp_enqueue_style('rrze-calendar');
                $this->load_template(dirname(__FILE__) . '/includes/templates/events.php');
            }          
        } elseif (is_null($event)) {
            if ($template = locate_template('404.php')) {
                load_template($template);
            } else {
                wp_die(__('Termin nicht gefunden.','rrze-calendar'));
            }
        } else {       
            if ($template = locate_template('rrze-calendar-single-event.php')) {
                $this->load_template($template, $event);
            } else {
                wp_enqueue_style('rrze-calendar');
                $this->load_template(dirname(__FILE__) . '/includes/templates/single-event.php', $event);
            }
        }       
    }
    
    private function load_template($template, $event = NULL) {
        global $rrze_calendar_data, $rrze_calendar_endpoint_url, $rrze_calendar_endpoint_name;
        
        if (is_null($event)) {
            $timestamp = RRZE_Calendar_Functions::gmt_to_local(time());
            $events_result = self::get_events_relative_to($timestamp);
            $rrze_calendar_data = RRZE_Calendar_Functions::get_calendar_dates($events_result['events']);                
        } else {
            $rrze_calendar_data = $event;
        }

        $rrze_calendar_endpoint_url = self::endpoint_url();
        $endpoint_name = self::endpoint_name();
        $rrze_calendar_endpoint_name = mb_strtoupper(mb_substr($endpoint_name, 0, 1)) . mb_substr($endpoint_name, 1);
        
        require_once($template);
        exit();
    }
    
    public static function is_endpoint() {
        global $wp_query;
        return isset($wp_query->query_vars[self::$options['endpoint_slug']]);       
    }
 
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'rrze-calendar') === FALSE) {
            return;
        }

        wp_enqueue_script('jquery-listfilterizer');
        wp_enqueue_script('rrze-calendar-admin', plugins_url('js/rrze-calendar-admin.js', __FILE__), array('jquery', 'jquery-listfilterizer'), self::version, TRUE);

        wp_localize_script('rrze-calendar-admin', 'rrze_calendar_vars', array(
            'filters_label_1' => __('Alle', 'rrze-calendar'),
            'filters_label_2' => __('Ausgewählt', 'rrze-calendar'),
            'placeholder' => __('Suchen...', 'rrze-calendar'),
        ));
        
        wp_enqueue_style('rrze-calendar-admin', plugins_url('css/rrze-calendar-admin.css', __FILE__), array(), self::version);
        
        if (strpos($hook, 'rrze-calendar-categories') !== FALSE) {
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('color-picker', plugins_url('js/color-picker.js', __FILE__), array('jquery', 'wp-color-picker'), self::version, TRUE);
            wp_enqueue_style('color-picker', plugins_url('css/color-picker.css', __FILE__), array(), self::version);
        }        
    }

    public function wp_enqueue_scripts() {
        wp_register_style('rrze-calendar', plugins_url('css/rrze-calendar.css', __FILE__));
        
        wp_register_style('rrze-calendar-shortcode', plugins_url('includes/shortcodes/calendar/calendar.css', __FILE__));
        wp_register_style('rrze-calendar-hint', plugins_url('includes/shortcodes/calendar/titip.min.css', __FILE__));
        
        wp_register_script('rrze-calendar-listenansicht', plugins_url('includes/shortcodes/calendar/listenansicht.js', __FILE__), array('jquery'), FALSE, TRUE);
        wp_register_script('rrze-calendar-monatsansicht', plugins_url('includes/shortcodes/calendar/monatsansicht.js', __FILE__), array('jquery'), FALSE, TRUE);
        wp_register_script('rrze-calendar-wochenansicht', plugins_url('includes/shortcodes/calendar/wochenansicht.js', __FILE__), array('jquery'), FALSE, TRUE);
        wp_register_script('rrze-calendar-tagesansicht', plugins_url('includes/shortcodes/calendar/tagesansicht.js', __FILE__), array('jquery'), FALSE, TRUE);
    }
    
    public static function get_calendar_feed() {
        $feed_id = absint(self::get_param('feed-id', 0));
        return self::get_calendar($feed_id);
    }
    
    public static function get_calendar($feed_id = 0) {
        global $wpdb;
        
        return $wpdb->get_row("SELECT * FROM " . self::$db_feeds_table . " WHERE id = $feed_id");
    }
       
    public function calendar_menu() {
        $calendar_page = add_menu_page(__('Kalender', 'rrze-calendar'), __('Kalender', 'rrze-calendar'), 'manage_options', 'rrze-calendar', array($this, 'calendar_feeds_page'), 'dashicons-calendar-alt');
        add_submenu_page('rrze-calendar', __('Feeds', 'rrze-calendar'), __('Feeds', 'rrze-calendar'), 'manage_options', 'rrze-calendar', array($this, 'calendar_feeds_page'));
        add_action( "load-{$calendar_page}", array($this, 'load_calendar_page'));
        add_action( "load-{$calendar_page}", array($this, 'calendar_screen_options'));
                
        $events_page = add_submenu_page('rrze-calendar', __('Termine', 'rrze-calendar'), __('Termine', 'rrze-calendar'), 'manage_options', 'rrze-calendar-events', array($this, 'calendar_events_page'));
        add_action( "load-{$events_page}", array($this, 'load_events_page'));
        add_action( "load-{$events_page}", array($this, 'events_screen_options'));

        add_submenu_page('rrze-calendar', __('Kategorien', 'rrze-calendar'), __('Kategorien', 'rrze-calendar'), 'manage_options', 'rrze-calendar-categories', array($this, 'calendar_categories_page'));
        add_submenu_page('rrze-calendar', __('Schlagworte', 'rrze-calendar'), __('Schlagworte', 'rrze-calendar'), 'manage_options', 'rrze-calendar-tags', array($this, 'calendar_tags_page'));
        add_submenu_page('rrze-calendar', __('Einstellungen', 'rrze-calendar'), __('Einstellungen', 'rrze-calendar'), 'manage_options', 'rrze-calendar-settings', array($this, 'calendar_settings_page'));
    }
    
    public function load_calendar_page() {

    }
    
    public function load_events_page() {

    }
    
    public function calendar_screen_options() {
        new RRZE_Calendar_Feeds_List_Table();
        
        $option = 'per_page';
        $args = array(
            'label' => __('Einträge pro Seite:', 'rrze-calendar'),
            'default' => 20,
            'option' => 'feeds_per_page'
        );
        
        add_screen_option($option, $args);
    }
    
    public function events_screen_options() {
        new RRZE_Calendar_Events_List_Table();
        
        $option = 'per_page';
        $args = array(
            'label' => __('Einträge pro Seite:', 'rrze-calendar'),
            'default' => 20,
            'option' => 'events_per_page'
        );
        
        add_screen_option($option, $args);
    }
    
    public function list_table_set_option($status, $option, $value) {
        return $value;
    }
        
    public function admin_settings() {        
        add_settings_section('rrze-calendar-feed-new-section', FALSE, '__return_false', 'rrze-calendar-feed-new');       
        add_settings_field('url', __('Feed-URL', 'rrze-calendar'), array($this, 'url_field'), 'rrze-calendar-feed-new', 'rrze-calendar-feed-new-section');
        add_settings_field('title', __('Titel', 'rrze-calendar'), array($this, 'title_field'), 'rrze-calendar-feed-new', 'rrze-calendar-feed-new-section');
        add_settings_field('category', __('Kategorie', 'rrze-calendar'), array($this, 'category_field'), 'rrze-calendar-feed-new', 'rrze-calendar-feed-new-section');
        add_settings_field('tags', __('Schlagworte', 'rrze-calendar'), array($this, 'tags_field'), 'rrze-calendar-feed-new', 'rrze-calendar-feed-new-section');
                
        add_settings_section('rrze-calendar-feed-edit-section', FALSE, '__return_false', 'rrze-calendar-feed-edit');    
        add_settings_field('url', __('Feed-URL', 'rrze-calendar'), array($this, 'url_field'), 'rrze-calendar-feed-edit', 'rrze-calendar-feed-edit-section');
        add_settings_field('title', __('Titel', 'rrze-calendar'), array($this, 'title_field'), 'rrze-calendar-feed-edit', 'rrze-calendar-feed-edit-section');
        add_settings_field('category', __('Kategorie', 'rrze-calendar'), array($this, 'category_field'), 'rrze-calendar-feed-edit', 'rrze-calendar-feed-edit-section');
        add_settings_field('tags', __('Schlagworte', 'rrze-calendar'), array($this, 'tags_field'), 'rrze-calendar-feed-edit', 'rrze-calendar-feed-edit-section');
                
        add_settings_section('rrze-calendar-settings-section', FALSE, '__return_false', 'rrze-calendar-settings');
        add_settings_field('endpoint_slug', __('Endpoint-Titelform', 'rrze-calendar'), array($this, 'endpoint_slug_field'), 'rrze-calendar-settings', 'rrze-calendar-settings-section');
        add_settings_field('endpoint_name', __('Endpoint-Name', 'rrze-calendar'), array($this, 'endpoint_name_field'), 'rrze-calendar-settings', 'rrze-calendar-settings-section');        
        add_settings_field('schedule_event', __('Überprüfen auf neue Termine', 'rrze-calendar'), array($this, 'schedule_event_field'), 'rrze-calendar-settings', 'rrze-calendar-settings-section');        
    }
    
    public function url_field() {
        $settings_errors = $this->settings_errors();
        $calendar_feed = self::get_calendar_feed();
        $feed_id = !is_null($calendar_feed) ? $calendar_feed->id : '';
        $url = !is_null($calendar_feed) ? $calendar_feed->url : '';
        $readonly = !empty($url) ? ' readonly="readonly"' : '';
        if ($feed_id): ?>
        <input type="hidden" value="<?php echo $feed_id; ?>" name="<?php printf('%s[feed_id]', self::option_name); ?>">
        <?php endif; ?>
        <input class="regular-text <?php echo (isset($settings_errors['url']['value'])) ? 'field-invalid' : ''; ?>" type="text" value="<?php echo (isset($settings_errors['url']['value'])) ? $settings_errors['url']['value'] : $url; ?>" name="<?php printf('%s[url]', self::option_name); ?>"<?php echo $readonly; ?>>
        <?php
    }

    public function title_field() {
        $settings_errors = $this->settings_errors();
        $calendar_feed = self::get_calendar_feed();
        $title = !is_null($calendar_feed) ? $calendar_feed->title : '';
        ?>
        <input class="regular-text <?php echo (isset($settings_errors['title']['value'])) ? 'field-invalid' : ''; ?>" type="text" value="<?php echo (isset($settings_errors['title']['value'])) ? $settings_errors['title']['value'] : $title; ?>" name="<?php printf('%s[title]', self::option_name); ?>">
        <?php
    }    
        
    public function category_field() {
        $settings_error = $this->settings_errors();
        $calendar_feed = self::get_calendar_feed();
        $all_categories = self::get_categories();
        $category = !is_null($calendar_feed) ? self::get_category_for_feed($calendar_feed->id) : '';
        $category = isset($settings_error['category']) ? $settings_error['category'] : $category;
        ?>
        <select name="<?php printf('%s[category]', self::option_name); ?>">
            <option value="0"><?php _e('&mdash; Auswählen &mdash;', 'rrze-calendar'); ?></option>            
            <?php foreach ($all_categories as $value) : ?>
            <option value="<?php echo $value->term_id; ?>" <?php $category ? selected($value->term_id, $category->term_id) : ''; ?>><?php echo $value->name; ?></option>
            <?php endforeach; ?>
        </select>
        <?php   
    }
    
    public function tags_field() {
        $calendar_feed = self::get_calendar_feed();
        $tags = self::get_tags();
        $selected_tags = !is_null($calendar_feed) ? self::get_tags_for_feed($calendar_feed->id) : NULL;
        $this->select_form($tags, $selected_tags, 'term_id', 'name', 'slug');      
    }
    
    public function endpoint_slug_field() {
        $settings_error = $this->settings_errors();
        $endpoint_slug = isset($settings_errors['endpoint_slug']['value']) ? $settings_errors['endpoint_slug']['value'] : self::$options['endpoint_slug'];
        ?>
        <code><?php echo site_url(); ?>/</code>
        <input type="text" value="<?php echo $endpoint_slug; ?>" name="<?php printf('%s[endpoint_slug]', self::option_name); ?>">
        <?php
    }        
        
    public function endpoint_name_field() {
        $settings_error = $this->settings_errors();
        $endpoint_name = isset($settings_errors['endpoint_name']['value']) ? $settings_errors['endpoint_name']['value'] : self::$options['endpoint_name'];
        ?>
        <input type="text" value="<?php echo $endpoint_name; ?>" name="<?php printf('%s[endpoint_name]', self::option_name); ?>">
        <?php
    }        
    
    public function schedule_event_field() {
        $settings_error = $this->settings_errors();
        $schedule_event = isset($settings_error['schedule_event']['value']) ? $settings_error['schedule_event']['value'] : self::$options['schedule_event'];
        ?>
        <select name="<?php printf('%s[schedule_event]', self::option_name); ?>">
            <?php foreach (self::$schedule_event_recurrance as $key => $value) : ?>
                <option value="<?php echo $key; ?>" <?php selected($key, $schedule_event); ?>><?php echo $value; ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }
        
    public function calendar_feeds_page() {
        $action = self::get_param('action');
        $option_page = self::get_param('option_page');
        ?>
        <div class="wrap">
            <h2>
                <?php echo esc_html(__('Kalender &rsaquo; Feeds', 'rrze-calendar')); ?>
                <?php if (empty($action)): ?>
                <a href="<?php echo self::options_url(array('action' => 'new')); ?>" class="add-new-h2"><?php _e('Neuer Feed hinzufügen', 'rrze-calendar'); ?></a>
                <?php endif; ?>
            </h2>
            <?php
            if ($action == 'new' || $option_page == 'rrze-calendar-feed-new') {
                $this->feed_new();
            } elseif ($action == 'edit' || $option_page == 'rrze-calendar-feed-edit') {
                $this->feed_edit();
            } else {
                $this->feeds_page();
            }
            ?>
        </div>
        <?php
        $this->delete_settings_errors();
    }

    public function calendar_events_page() {
        ?>
        <div class="wrap">
            <h2>
                <?php echo esc_html(__('Kalender &rsaquo; Termine', 'rrze-calendar')); ?>
            </h2>
            <?php $this->events_page(); ?>
        </div>
        <?php
        $this->delete_settings_errors();
    }
    
    public function calendar_categories_page() {
        ?>
        <div class="wrap">
            <h2>
                <?php echo esc_html(__('Kalender &rsaquo; Kategorien', 'rrze-calendar')); ?>
            </h2>
            <?php $this->categories_page(); ?>
        </div>
        <?php
        $this->delete_settings_errors();
    }
    
    public function calendar_tags_page() {
        ?>
        <div class="wrap">
            <h2>
                <?php echo esc_html(__('Kalender &rsaquo; Schlagworte', 'rrze-calendar')); ?>
            </h2>
            <?php $this->tags_page(); ?>
        </div>
        <?php
        $this->delete_settings_errors();
    }
    
    public function calendar_settings_page() {
        ?>
        <div class="wrap">
            <h2>
                <?php echo esc_html(__('Kalender &rsaquo; Einstellungen', 'rrze-calendar')); ?>
            </h2>
            <?php $this->settings_page(); ?>
        </div>
        <?php
        $this->delete_settings_errors();
    }
    
    public function feeds_page() {
        $feeds = (array) $this->get_feeds_data(ARRAY_A);
        
        foreach ($feeds as $key => $feed) {
            $category = self::get_category_for_feed($feed['id']);
            if ($category) {
                $feeds[$key]['category'] = array('id' => $category->term_id, 'name' => $category->name, 'slug' => $category->slug);
            } else {
                $feeds[$key]['category'] = '';
            }
            
            $feed_tags = self::get_tags_for_feed($feed['id']);
            if ($feed_tags) {
                $tags = array();
                foreach ($feed_tags as $tag_id) {
                    $tag = self::get_tag_by('id', $tag_id);
                    $tags[] = array('id' => $tag_id, 'name' => $tag->name, 'slug' => $tag->slug);
                }
                $feeds[$key]['tags'] = $tags;
            } else {
                $feeds[$key]['tags'] = '';
            }
                        
            $feeds[$key]['events_count'] = $this->get_events_count($feed['id']);
        }
        
        $list_table = new RRZE_Calendar_Feeds_List_Table($feeds);
        $list_table->prepare_items();
        ?>
        <form method="get">
        <input type="hidden" name="page" value="rrze-calendar">
        <?php
        $list_table->search_box(__('Suche', 'rrze-calendar'), 'search_id');
        ?>
        </form>
        <form method="post">
        <?php
        $list_table->views();
        $list_table->display();
        ?>
        </form>
        <?php        
    }
    
    public function events_page() {
        $events = (array) $this->get_events_data(ARRAY_A);

        $list_table = new RRZE_Calendar_Events_List_Table($events);
        $list_table->prepare_items();        
        ?>
        <form method="get">
        <input type="hidden" name="page" value="rrze-calendar-events">
        <?php
        $list_table->search_box(__('Suche', 'rrze-calendar'), 'search_id');
        ?>
        </form>
        <form method="post">
        <?php
        $list_table->views();
        $list_table->display();
        ?>
        </form>
        <?php        
    }
    
    public function settings_page() {
        $option_page = self::get_param('option_page');
        $input = (array) self::get_param(self::option_name);
        $nonce = self::get_param('_wpnonce');
        
        if ($input && $option_page == 'rrze-calendar-settings') {           
            if (!wp_verify_nonce($nonce, 'rrze-calendar-settings-options')) {
                wp_die(self::$messages['nonce-failed']);
            }

            $this->validate_settings($input);
            
            if ($this->settings_errors()) {
                foreach ($this->settings_errors() as $error) {
                    if ($error['message']) {
                        $this->add_admin_notice($error['message']);
                    }
                }
                wp_redirect(self::options_url(array('page' => 'rrze-calendar-settings')));
                exit();
            }
            
            $this->add_admin_notice(__('Einstellungen gespeichert.'));
            wp_redirect(self::options_url(array('page' => 'rrze-calendar-settings')));      
            exit();
        }        
        ?>
        <form method="post">
        <?php
        settings_fields('rrze-calendar-settings');
        do_settings_sections('rrze-calendar-settings');
        submit_button();
        ?>
        </form>
        <?php
    }
          
    private function feed_new() {
        $option_page = self::get_param('option_page');
        $input = (array) self::get_param(self::option_name);
        $nonce = self::get_param('_wpnonce');        
        
        if ($input && $option_page == 'rrze-calendar-feed-new') {           
            if (!wp_verify_nonce($nonce, 'rrze-calendar-feed-new-options')) {
                wp_die(self::$messages['nonce-failed']);
            }
            
            $feed_id = $this->validate_new($input);
        
            if ($this->settings_errors()) {
                foreach ($this->settings_errors() as $error) {
                    if ($error['message']) {
                        $this->add_admin_notice($error['message']);
                    }
                }
                wp_redirect(self::options_url(array('action' => 'new')));
                exit();
            }
            
            $this->add_admin_notice(__('Der Feed wurde hinzugefügt.'));
            wp_redirect(self::options_url(array('action' => 'edit', 'feed-id' => $feed_id)));
            exit();
        }
        ?>
        <h2><?php echo esc_html(__('Neuer Feed hinzufügen', 'rrze-calendar')); ?></h2>
        <form action="<?php echo self::options_url(array('action' => 'new')); ?>" method="post">
        <?php
        settings_fields('rrze-calendar-feed-new');
        do_settings_sections('rrze-calendar-feed-new');
        submit_button(__('Neuer Feed hinzufügen', 'rrze-calendar'));
        ?>
        </form>
        <?php
    }
    
    private function feed_edit() {
        $option_page = self::get_param('option_page');
        $input = (array) self::get_param(self::option_name);
        $nonce = self::get_param('_wpnonce');
                
        if ($input && $option_page == 'rrze-calendar-feed-edit') {           
            if (!wp_verify_nonce($nonce, 'rrze-calendar-feed-edit-options')) {
                wp_die(self::$messages['nonce-failed']);
            }
            
            $feed_id = $this->validate_edit($input);
            
            if ($this->settings_errors()) {
                foreach ($this->settings_errors() as $error) {
                    if ($error['message']) {
                        $this->add_admin_notice($error['message']);
                    }
                }
                wp_redirect(self::options_url(array('action' => 'edit')));
                exit();
            }
            
            $this->add_admin_notice(__('Der Feed wurde aktualisiert.'));
            wp_redirect(self::options_url(array('action' => 'edit', 'feed-id' => $feed_id)));
            exit();
        }        
        ?>
        <h2><?php echo esc_html(__('Feed bearbeiten', 'rrze-calendar')); ?></h2>
        <form action="<?php echo self::options_url(array('action' => 'edit')) ?>" method="post">
        <?php
        settings_fields('rrze-calendar-feed-edit');
        do_settings_sections('rrze-calendar-feed-edit');
        submit_button(__('Änderungen übernehmen', 'rrze-calendar'));
        ?>
        </form>
        <?php
    }
    
    public function admin_actions() {
        $page = self::get_param('page', '');
        $action = self::get_param('action', '');
        $calendar_feed = self::get_calendar_feed();
        
        if($page == 'rrze-calendar' && !is_null($calendar_feed)) {
            switch ($action) {
                case 'update':
                    $this->feed_update($calendar_feed);
                    break;
                case 'delete':
                    $this->feed_delete($calendar_feed);
                    break;
                case 'activate':
                    $this->feed_activate($calendar_feed);
                    break;
                case 'deactivate':
                    $this->feed_activate($calendar_feed, 0);
                    break;               
            }
        }
    }
      
    public function feed_update($feed) {    
        if (is_object($feed) && $feed->active) {
            self::flush_feed($feed->id, FALSE);
            $this->parse_ics_feed($feed);
        }
                
        $this->add_admin_notice(__('Der Feed wurde aktualisiert.'));
        wp_redirect(self::options_url());
        exit();
    }
    
    public function feed_bulk_update($feed_ids) {    
        if(is_array($feed_ids)) {
            foreach ($feed_ids as $feed_id) {
                $feed = self::get_feed($feed_id);
                if ($feed && $feed->active) {
                    self::flush_feed($feed_id, FALSE);
                    $this->parse_ics_feed($feed);                   
                }
            }
        }

        wp_redirect(self::options_url());
        exit();
    }
    
    public function feed_delete($feed) {
        global $wpdb;
        
        if (is_object($feed)) {
            $result = $wpdb->delete(self::$db_feeds_table, array('id' => $feed->id, 'active' => 0), array('%d', '%d'));
            if ($result) {
                $all_categories = self::get_categories();
                foreach ($all_categories as $category) {
                    self::remove_feed_from_category($feed->id, $category->term_id);
                }
                $all_tags = self::get_tags();
                foreach ($all_tags as $tag) {
                    self::remove_feed_from_tag($feed->id, $tag->term_id);
                }                
            }            
        }
                
        $this->add_admin_notice(__('Der Feed wurde gelöscht.'));
        wp_redirect(self::options_url());
        exit();
    }
    
    public function feed_bulk_delete($feed_ids) {
        global $wpdb;
                
        if(is_array($feed_ids)) {
            foreach ($feed_ids as $feed_id) {
                $result = $wpdb->delete(self::$db_feeds_table, array('id' => $feed_id, 'active' => 0), array('%d', '%d'));
                if ($result) {
                    $all_categories = self::get_categories();
                    foreach ($all_categories as $category) {
                        self::remove_feed_from_category($feed_id, $category->term_id);
                    }
                    $all_tags = self::get_tags();
                    foreach ($all_tags as $tag) {
                        self::remove_feed_from_tag($feed_id, $tag->term_id);
                    }                    
                }
            }            
        }

        wp_redirect(self::options_url());
        exit();
    }
    
    public function feed_activate($feed, $activate = 1) {
        global $wpdb;

        if (is_object($feed)) {
            $wpdb->update(self::$db_feeds_table, array('active' => $activate), array('id' => $feed->id), array('%d'));
            self::flush_feed($feed->id, FALSE);
            if ($activate) {
                $this->parse_ics_feed($feed);
            }
        }
        
        if ($activate) {
            $this->add_admin_notice(__('Der Feed wurde aktiviert.'));
        } else {
            $this->add_admin_notice(__('Der Feed wurde deaktiviert.'));
        }
        wp_redirect(self::options_url());
        exit();
    }
    
    public function feed_bulk_activate($feed_ids, $activate = 1) {
        global $wpdb;

        if(is_array($feed_ids)) {            
            foreach ($feed_ids as $feed_id) {
                $wpdb->update(self::$db_feeds_table, array('active' => $activate), array('id' => $feed_id), array('%d'), array('%d'));
                self::flush_feed($feed_id, FALSE);
                if ($activate) {
                    $feed = self::get_feed($feed_id);
                    if ($feed) {
                        $this->parse_ics_feed($feed);                   
                    }
                }
            }
        }
        
        wp_redirect(self::options_url());
        exit();
    }
    
    public function categories_page() {      
        $category_id = absint(self::get_param('category-id'));
        $action = self::get_param('action');
        
        if ($category_id && $action == 'edit') :
            $category = self::get_category_by('id', $category_id);

            if (!$category) {
                echo '<div class="error"><p>' . __('Die Kategorie existiert nicht.', 'rrze-calendar') . '</p></div>';
                return;
            }
            
            $error_settings = $this->settings_errors();

            $name = isset($_POST['name']) ? stripslashes($_POST['name']) : $category->name;
            $description = isset($_POST['description']) ? stripslashes($_POST['description']) : $category->description;            
            $color = get_term_meta($category->term_id, 'color', TRUE);
            $feeds = self::get_feeds();
            ?>       
            <form method="post" action="<?php echo esc_url(RRZE_Calendar::options_url(array('page' => 'rrze-calendar-categories', 'action' => 'edit', 'category-id' => $category_id))); ?>">
                <table class="form-table">
                    <tbody>
                        <tr class="form-field form-required term-name-wrap <?php echo isset($error_settings['name']) ? 'form-invalid' : ''; ?>">
                            <th scope="row"><label for="name"><?php _e('Name', 'rrze-calendar'); ?></label></th>
                            <td>
                                <input type="text" aria-required="true" size="40" value="<?php echo esc_attr($name); ?>" id="name" name="name">
                                <p class="description"><?php _e('Der Name wird verwendet um die Kategorie zu identifizieren.', 'rrze-calendar'); ?></p>
                            </td>
                        </tr>
                        <tr class="form-field term-color-wrap">
                            <th scope="row"><label for="color"><?php _e('Farbe', 'rrze-calendar'); ?></label></th>
                            <td><input type="text" name="color" class="color-picker" data-default-color="<?php echo !empty($color) ? esc_attr($color) : ''; ?>" value="<?php echo !empty($color) ? esc_attr($color) : ''; ?>">
                        </tr>                        
                        <tr class="form-field term-description-wrap">
                            <th scope="row"><label for="description">Beschreibung</label></th>
                            <td>
                                <textarea class="large-text" cols="50" rows="5" id="description" name="description"><?php echo esc_html($description); ?></textarea>
                                <p class="description"><?php _e('Die Beschreibung ist für administrative Zwecke vorhanden.', 'rrze-calendar'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <input type="hidden" name="category_id" value="<?php echo esc_attr($category_id); ?>">
                <input type="hidden" name="page" value="rrze-calendar-categories">
                <input type="hidden" name="action" value="edit">
                <?php wp_nonce_field('edit-category'); ?>                
                <p class="submit"><?php submit_button(__('Aktualisieren', 'rrze-calendar'), 'primary', 'submit', FALSE); ?></p>
            </form>
            <?php
        else :
            $wp_list_table = new RRZE_Calendar_Categories_List_Table();
            $wp_list_table->prepare_items();
            $error_settings = $this->settings_errors();
            ?>
            <div id="col-right">
                <div class="col-wrap">
                    <?php $wp_list_table->display(); ?>
                </div>
            </div>
            <div id="col-left"><div class="col-wrap"><div class="form-wrap categories-wrap">
                <h2>
                    <?php _e('Neue Kategorie hinzufügen', 'rrze-calendar'); ?></a>
                </h2>
                <form class="add:the-list:" action="<?php echo esc_url(RRZE_Calendar::options_url(array('page' => 'rrze-calendar-categories'))); ?>" method="post" id="addcategory" name="addcategory">
                    <div class="form-field form-required <?php echo isset($error_settings['name']) ? 'form-invalid' : ''; ?>">
                        <label for="name"><?php _e('Name', 'rrze-calendar'); ?></label>
                        <input type="text" aria-required="true" id="name" name="name" maxlength="40" value="<?php if (!empty($_POST['name'])) echo esc_attr($_POST['name']); ?>">
                        <p class="description"><?php _e('Der Name wird verwendet um die Kategorie zu identifizieren.', 'rrze-calendar'); ?></p>                            
                    </div>
                    <div class="form-field">
                        <label for="color"><?php _e('Farbe', 'rrze-calendar'); ?></label>
                        <input type="text" name="color" class="color-picker" data-default-color="" value="">
                    </div>                    
                    <div class="form-field">
                        <label for="description"><?php _e('Beschreibung', 'rrze-calendar'); ?></label>
                        <textarea cols="40" rows="5" id="description" name="description"><?php if (!empty($_POST['description'])) echo esc_html($_POST['description']) ?></textarea>
                        <p class="description"><?php _e('Die Beschreibung ist für administrative Zwecke vorhanden.', 'rrze-calendar'); ?></p>
                    </div>
                    <input type="hidden" name="page" value="rrze-calendar-categories">
                    <input type="hidden" name="action" value="add">
                    <?php wp_nonce_field('add-category'); ?>
                    <p class="submit"><?php submit_button(__('Neue Kategorie hinzufügen', 'rrze-calendar'), 'primary', 'submit', FALSE); ?></p>
                </form>
            </div></div></div>
            <?php $wp_list_table->inline_edit(); ?>
        <?php
        endif;
    }
    
    public function tags_page() {
        $tag_id = absint(self::get_param('tag-id'));
        $action = self::get_param('action');
        
        if ($tag_id && $action == 'edit') :
            $tag = self::get_tag_by('id', $tag_id);

            if (!$tag) {
                echo '<div class="error"><p>' . __('Das Schlagwort existiert nicht.', 'rrze-calendar') . '</p></div>';
                return;
            }

            $error_settings = $this->settings_errors();
            
            $name = isset($_POST['name']) ? stripslashes($_POST['name']) : $tag->name;
            $description = isset($_POST['description']) ? stripslashes($_POST['description']) : $tag->description;            
            $color = get_term_meta($tag->term_id, 'color', TRUE);
            $feeds = self::get_feeds();
            ?>
            <form method="post" action="<?php echo esc_url(RRZE_Calendar::options_url(array('page' => 'rrze-calendar-tags', 'action' => 'edit', 'tag-id' => $tag_id))); ?>">
                <div id="col-right">
                    <div class="col-wrap">
                        <div id="rrze-calendar-taxonomy-feeds" class="wrap">
                            <h4><?php _e('Feeds', 'rrze-calendar'); ?></h4>
                            <?php $this->select_form($feeds, $tag->feed_ids, 'id', 'title', 'url'); ?>
                        </div>
                    </div>
                </div>
                <div id="col-left"><div class="col-wrap"><div class="form-wrap">
                    <div class="form-field form-required <?php echo isset($error_settings['name']) ? 'form-invalid' : ''; ?>">
                        <label for="name"><?php _e('Name', 'rrze-calendar'); ?></label>
                        <input name="name" id="name" type="text" value="<?php echo esc_attr($name); ?>" size="40" maxlength="40" aria-required="true">
                    </div>
                    <div class="form-field">
                        <label for="description"><?php _e('Beschreibung', 'rrze-calendar'); ?></label>
                        <textarea name="description" id="description" rows="5" cols="40"><?php echo esc_html($description); ?></textarea>
                    </div>
                    <input type="hidden" name="tag_id" value="<?php echo esc_attr($tag_id); ?>">
                    <input type="hidden" name="page" value="rrze-calendar-tags">
                    <input type="hidden" name="action" value="edit">
                    <?php wp_nonce_field('edit-tag'); ?>                    
                    <p class="submit"><?php submit_button(__('Aktualisieren', 'rrze-calendar'), 'primary', 'submit', FALSE); ?></p>
                </div></div></div>
            </form>
            <?php
        else :
            $wp_list_table = new RRZE_Calendar_Tags_List_Table();
            $wp_list_table->prepare_items();
            
            $error_settings = $this->settings_errors();
            ?>
            <div id="col-right">
                <div class="col-wrap">
                    <?php $wp_list_table->display(); ?>
                </div>
            </div>
            <div id="col-left"><div class="col-wrap"><div class="form-wrap categories-wrap">
                <h2>
                    <?php _e('Neues Schlagwort erstellen', 'rrze-calendar'); ?></a>
                </h2>
                <form class="add:the-list:" action="<?php echo esc_url(RRZE_Calendar::options_url(array('page' => 'rrze-calendar-tags'))); ?>" method="post" id="addtag" name="addtag">
                    <div class="form-field form-required <?php echo isset($error_settings['name']) ? 'form-invalid' : ''; ?>">
                        <label for="name"><?php _e('Name', 'rrze-calendar'); ?></label>
                        <input type="text" aria-required="true" id="name" name="name" maxlength="40" value="<?php if (!empty($_POST['name'])) echo esc_attr($_POST['name']); ?>">
                        <p class="description"><?php _e('Der Name wird verwendet um das Schlagwort zu identifizieren.', 'rrze-calendar'); ?></p>                    
                    </div>                   
                    <div class="form-field">
                        <label for="description"><?php _e('Beschreibung', 'rrze-calendar'); ?></label>
                        <textarea cols="40" rows="5" id="description" name="description"><?php if (!empty($_POST['description'])) echo esc_html($_POST['description']) ?></textarea>
                        <p class="description"><?php _e('Die Beschreibung ist für administrative Zwecke vorhanden.', 'rrze-calendar'); ?></p>                            
                    </div>
                    <input type="hidden" name="page" value="rrze-calendar-tags">
                    <input type="hidden" name="action" value="add">
                    <?php wp_nonce_field('add-tag'); ?>
                    <p class="submit"><?php submit_button(__('Neues Schlagwort erstellen', 'rrze-calendar'), 'primary', 'submit', FALSE); ?></p>
                </form>
            </div></div></div>
            <?php $wp_list_table->inline_edit(); ?>
        <?php
        endif;
    }
    
    public function validate_settings($input) {        
        if (empty(trim($input['endpoint_slug']))) {
            $this->add_settings_error('endpoint_slug');
        }        
                
        $endpoint_slug = preg_replace("/[^a-zA-Z0-9]+/", "", trim($input['endpoint_slug']));
        if (empty($endpoint_slug)) {
            $this->add_settings_error('endpoint_slug');
        }        
        
        if (empty(trim($input['endpoint_name']))) {
            $this->add_settings_error('endpoint_name');
        }
        
        if (empty(trim($input['schedule_event'])) || !array_key_exists(trim($input['schedule_event']), self::$schedule_event_recurrance)) {
            $this->add_settings_error('schedule_event');
        }
        
        if($this->settings_errors()) {
            return;
        }        
        
        self::$options['endpoint_slug'] = $endpoint_slug;
        self::add_endpoint();
        flush_rewrite_rules();
        
        self::$options['endpoint_name'] = trim(self::$options['endpoint_name']);               
        self::$options['schedule_event'] = $input['schedule_event'];      
    }
    
    private function validate_new($input) {
        global $wpdb;

        $input = (array) $input;
        
        $url = !empty(trim($input['url'])) ? strtolower(trim(urldecode($input['url']), '/\\')) : '';
        
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        $feed_url =  $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::$db_feeds_table . " WHERE url = %s", $url));

        if (!$url) {
            $this->add_settings_error('url');
        } elseif ($url && !filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED)) {
            $this->add_settings_error('url', $url, __('Der URL ist ungültig.', 'rrze-calendar'));
        } elseif ($url && !is_null($feed_url)) {
            $this->add_settings_error('url', $url, __('URL existiert bereits.', 'rrze-calendar'));
        }
        
        $title = !empty(trim($input['title'])) ? trim($input['title']) : '';
        
        if (!$title) {
            $this->add_settings_error('title');
        }
        
        if ($this->settings_errors()) {
            return FALSE;
        }        
                
        $current_time = current_time('mysql');
        
        $new_input = array(
            'url' => $url,
            'title' => $title,
            'active' => 1,            
            'created' => $current_time,
            'modified' => $current_time
        );
        
        global $wpdb;

        $rows_affected = $wpdb->insert(self::$db_feeds_table, $new_input, array('%s', '%s', '%d', '%s', '%s'));
        
        if ($rows_affected !== FALSE) {
            $feed_id = $wpdb->insert_id;
            $category_term_id = isset($input['category']) ? absint($input['category']) : '';
            $all_categories = self::get_categories();
            foreach ($all_categories as $category) {
                if ($category_term_id == $category->term_id) {
                    self::add_feed_to_category($feed_id, $category->term_id);
                }
            }

            $tags = isset($_POST['rrze_calendar_selected']) ? array_map('intval', (array) $_POST['rrze_calendar_selected']) : array();
            $all_tags = self::get_tags();
            foreach ($all_tags as $tag) {
                if (in_array($tag->term_id, $tags)) {
                    self::add_feed_to_tag($feed_id, $tag->term_id);
                }
            }
            
            return $feed_id;
        }
        
        return FALSE;
    }
    
    private function validate_edit($input) {
        $feed_id = isset($input['feed_id']) ? absint($input['feed_id']) : 0;
        
        $feed = self::get_calendar($feed_id);
        
        if (is_null($feed)) {
            return;
        }
        
        $title = !empty(trim($input['title'])) ? trim($input['title']) : '';        
        
        if (!$title) {
            $this->add_settings_error('title');
        }
        
        if ($this->settings_errors()) {
            return FALSE;
        }        
        
        $current_time = current_time('mysql');
        
        $new_input = array(
            'title' => $title,
            'modified' => $current_time           
        );

        global $wpdb;

        $rows_affected = $wpdb->update(self::$db_feeds_table, $new_input, array('id' => $feed_id), array('%s', '%s'), array('%d'));

        if ($rows_affected !== FALSE) {
            $category_term_id = isset($input['category']) ? absint($input['category']) : '';
            $all_categories = self::get_categories();

            foreach ($all_categories as $category) {
                if ($category_term_id == $category->term_id) {
                    self::add_feed_to_category($feed_id, $category->term_id);
                } else {             
                    self::remove_feed_from_category($feed_id, $category->term_id);
                }
            }

            $tags = isset($_POST['rrze_calendar_selected']) ? array_map('intval', (array) $_POST['rrze_calendar_selected']) : array();
            $all_tags = self::get_tags();
            foreach ($all_tags as $tag) {
                if (in_array($tag->term_id, $tags)) {
                    self::add_feed_to_tag($feed_id, $tag->term_id);
                } else {             
                    self::remove_feed_from_tag($feed_id, $tag->term_id);
                }
            }
            
            return $feed_id;
        }
        
        return FALSE;
    }
    
    public function validate_add_category() {
        $submit = self::get_param('submit');
        $page = self::get_param('page');
        $action = self::get_param('action');
        $nonce = self::get_param('_wpnonce');
        
        $name = self::get_param('name');
        $description = self::get_param('description');
        $color = self::get_param('color');

        if (!$submit || $page != 'rrze-calendar-categories' || $action != 'add') {
            return;
        }

        if (!wp_verify_nonce($nonce, 'add-category')) {
            wp_die(self::$messages['nonce-failed']);
        }

        if (!current_user_can('manage_categories')) {
            wp_die(self::$messages['invalid-permissions']);
        }

        $name = $name ? strip_tags(trim($name)) : '';
        $description = $description ? strip_tags(trim($description)) : '';
        
        $color = $color && self::sanitize_hex_color($color) ? $color : '';

        if (empty($name)) {
            $this->add_settings_error('name');
        }

        if (self::get_category_by('name', $name)) {
            $this->add_settings_error('name', $name, __('Der Name wird bereits verwendet. Bitte wählen Sie einen anderen.', 'rrze-calendar'));
        }

        if ($this->settings_errors()) {
            return FALSE;
        }
        
        $args = array(
            'name' => $name,
            'description' => $description,
        );

        $category = self::add_category($args);
        if (is_wp_error($category)) {
            wp_die(self::$messages['error-ocurred']);
        }

        add_term_meta($category->term_id, 'color', $color, TRUE);
        
        $args = array(
            'page' => 'rrze-calendar-categories',
            'action' => 'edit',
            'category-id' => $category->term_id
        );
        
        $this->add_admin_notice(__('Die Kategorie wurde hinzugefügt.', 'rrze-calendar'));
        $redirect_url = self::options_url($args);
        wp_redirect($redirect_url);
        exit();
    }

    public function validate_add_tag() {
        $submit = self::get_param('submit');
        $page = self::get_param('page');
        $action = self::get_param('action');
        $nonce = self::get_param('_wpnonce');

        $name = self::get_param('name');
        $description = self::get_param('description');

        if (!$submit || $page != 'rrze-calendar-tags' || $action != 'add') {
            return;
        }

        if (!wp_verify_nonce($nonce, 'add-tag')) {
            wp_die(self::$messages['nonce-failed']);
        }

        if (!current_user_can('manage_categories')) {
            wp_die(self::$messages['invalid-permissions']);
        }

        $name = $name ? strip_tags(trim($name)) : '';
        $description = $description ? strip_tags(trim($description)) : '';

        if (empty($name)) {
            $this->add_settings_error('name');
        }

        if (self::get_tag_by('name', $name)) {
            $this->add_settings_error('name', $name, __('Der Name wird bereits verwendet. Bitte wählen Sie einen anderen.', 'rrze-calendar'));
        }

        if ($this->settings_errors()) {
            return FALSE;
        }

        $args = array(
            'name' => $name,
            'description' => $description,
        );

        $tag = self::add_tag($args);
        if (is_wp_error($category)) {
            wp_die(self::$messages['error-ocurred']);
        }

        $args = array(
            'page' => 'rrze-calendar-tags',
            'action' => 'edit',
            'tag-id' => $tag->term_id
        );
        
        $this->add_admin_notice(__('Das Schlagwort wurde hinzugefügt.', 'rrze-calendar'));
        $redirect_url = self::options_url($args);
        wp_redirect($redirect_url);
        exit();
    }
    
    public function validate_edit_category() {
        $submit = self::get_param('submit');
        $page = self::get_param('page');
        $action = self::get_param('action');
        $nonce = self::get_param('_wpnonce');
        
        $category_id = self::get_param('category_id');
        $name = self::get_param('name');
        $description = self::get_param('description');
        $color = self::get_param('color');

        if (!$submit || $page != 'rrze-calendar-categories' || $action != 'edit') {
            return;
        }

        if (!wp_verify_nonce($nonce, 'edit-category')) {
            wp_die(self::$messages['nonce-failed']);
        }

        if (!current_user_can('manage_categories')) {
            wp_die(self::$messages['invalid-permissions']);
        }

        $category_id = $category_id ? absint($_POST['category_id']) : 0;
        if (!$category_id || !$existing_category = self::get_category_by('id', $category_id)) {
            wp_die(self::$messages['error-ocurred']);
        }

        $name = $name ? strip_tags(trim($name)) : '';
        $description = $description ? strip_tags(trim($description)) : '';
        
        $color = $color && self::sanitize_hex_color($color) ? $color : '';

        if (empty($name)) {
            $this->add_settings_error('name');
        }

        $search_term = self::get_category_by('name', $name);
        if (is_object($search_term) && $search_term->term_id != $existing_category->term_id) {
            $this->add_settings_error('name', $name, __('Der Name wird bereits verwendet. Bitte wählen Sie einen anderen.', 'rrze-calendar'));
        }

        if ($this->settings_errors()) {
            return FALSE;
        }

        $args = array(
            'name' => $name,
            'description' => $description,
        );

        $feeds = isset($_POST['rrze_calendar_selected']) ? (array) $_POST['rrze_calendar_selected'] : array();
        $feeds = array_map('intval', $feeds);

        $category = self::update_category($existing_category->term_id, $args, $feeds);

        if (is_wp_error($category)) {
            wp_die(self::$messages['error-ocurred']);
        }
        
        update_term_meta($existing_category->term_id, 'color', $color);

        $args = array(
            'page' => 'rrze-calendar-categories'
        );
        
        $this->add_admin_notice(__('Die Kategorie wurde aktualisiert.', 'rrze-calendar'));
        $redirect_url = self::options_url($args);
        wp_redirect($redirect_url);
        exit();
    }

    public function validate_edit_tag() {
        $submit = self::get_param('submit');
        $page = self::get_param('page');
        $action = self::get_param('action');
        $nonce = self::get_param('_wpnonce');
        
        $tag_id = self::get_param('tag_id');
        $name = self::get_param('name');
        $description = self::get_param('description');

        if (!$submit || $page != 'rrze-calendar-tags' || $action != 'edit') {
            return;
        }

        if (!wp_verify_nonce($nonce, 'edit-tag')) {
            wp_die(self::$messages['nonce-failed']);
        }

        if (!current_user_can('manage_categories')) {
            wp_die(self::$messages['invalid-permissions']);
        }

        $tag_id = isset($_POST['tag_id']) ? absint($_POST['tag_id']) : 0;
        if (!$tag_id || !$existing_tag = self::get_tag_by('id', $tag_id)) {
            wp_die(self::$messages['error-ocurred']);
        }

        $name = isset($_POST['name']) ? strip_tags(trim($_POST['name'])) : '';
        $description = isset($_POST['description']) ? strip_tags(trim($_POST['description'])) : '';

        if (empty($name)) {
            $this->add_settings_error('name');
        }

        $search_term = self::get_tag_by('name', $name);
        if (is_object($search_term) && $search_term->term_id != $existing_tag->term_id) {
            $this->add_settings_error('name', $name, __('Der Name wird bereits verwendet. Bitte wählen Sie einen anderen.', 'rrze-calendar'));
        }

        if ($this->settings_errors()) {
            return FALSE;
        }

        $args = array(
            'name' => $name,
            'description' => $description,
        );

        $feeds = isset($_POST['rrze_calendar_selected']) ? (array) $_POST['rrze_calendar_selected'] : array();
        $feeds = array_map('intval', $feeds);

        $tag = self::update_tag($existing_tag->term_id, $args, $feeds);

        if (is_wp_error($tag)) {
            wp_die(self::$messages['error-ocurred']);
        }
        
        $args = array(
            'page' => 'rrze-calendar-tags'
        );
        
        $this->add_admin_notice(__('Das Schlagwort wurde aktualisiert.', 'rrze-calendar'));
        $redirect_url = self::options_url($args);
        wp_redirect($redirect_url);
        exit();
    }
    
    public function validate_delete_category() {
        $page = self::get_param('page');
        $action = self::get_param('action');
        $category_id = absint(self::get_param('category-id'));
        $nonce = self::get_param('nonce');
        
        if ($page != 'rrze-calendar-categories' || $action != 'delete' || !$category_id) {
            return;
        }

        if (!wp_verify_nonce($nonce, 'delete-category')) {
            wp_die(self::$messages['nonce-failed']);
        }

        if (!current_user_can('manage_categories')) {
            wp_die(self::$messages['invalid-permissions']);
        }

        if (!$category_id || !$existing_category = self::get_category_by('id', $category_id)) {
            wp_die(self::$messages['error-ocurred']);
        }
        
        $result = self::delete_category($existing_category->term_id);
        if (!$result || is_wp_error($result)) {
            wp_die(self::$messages['error-ocurred']);
        }
        
        delete_term_meta($existing_category->term_id, 'color');
        
        $this->add_admin_notice(__('Die Kategorie wurde gelöscht.', 'rrze-calendar'));
        $redirect_url = self::options_url(array('page' => 'rrze-calendar-categories'));
        wp_redirect($redirect_url);
        exit();
    }
        
    public function validate_delete_tag() {
        $page = self::get_param('page');
        $action = self::get_param('action');
        $tag_id = absint(self::get_param('tag-id'));
        $nonce = self::get_param('nonce');
        
        if ($page != 'rrze-calendar-tags' || $action != 'delete' || !$tag_id) {
            return;
        }

        if (!wp_verify_nonce($nonce, 'delete-tag')) {
            wp_die(self::$messages['nonce-failed']);
        }

        if (!current_user_can('manage_categories')) {
            wp_die(self::$messages['invalid-permissions']);
        }

        if (!$tag_id || !$existing_tag = self::get_tag_by('id', $tag_id)) {
            wp_die(self::$messages['error-ocurred']);
        }
        
        $result = self::delete_tag($existing_tag->term_id);
        if (!$result || is_wp_error($result)) {
            wp_die(self::$messages['error-ocurred']);
        }
        
        delete_term_meta($existing_tag->term_id, 'color');
        
        $this->add_admin_notice(__('Das Schlagwort wurde gelöscht.', 'rrze-calendar'));
        $redirect_url = self::options_url(array('page' => 'rrze-calendar-tags'));
        wp_redirect($redirect_url);
        exit();
    }
    
    public static function get_feeds() {
        global $wpdb;

        $sql = "SELECT * FROM " . self::$db_feeds_table;
        $feeds = $wpdb->get_results($sql);
        return $feeds;
    }
    
    public static function get_feed($id, $output_type = 'OBJECT') {
        global $wpdb;

        $id = isset($id) ? absint($id) : 0;
        $sql = "SELECT * FROM " . self::$db_feeds_table . " WHERE id = %d";
        $feed = $wpdb->get_row($wpdb->prepare($sql, $id), $output_type);
        return $feed;
    }
    
    private function get_event_by_slug($slug) {
        global $wpdb;

        $sql = "SELECT *, UNIX_TIMESTAMP(start) AS start, UNIX_TIMESTAMP(end) AS end FROM " . self::$db_events_table . " WHERE slug = %s";
        $data = $wpdb->get_row($wpdb->prepare($sql, $slug), ARRAY_A);
        if (is_null($data)) {
            return NULL;
        }
        
        $data['category'] = self::get_category_for_feed($data['ical_feed_id']);
        $data['tags'] = self::get_tags_for_feed($data['ical_feed_id'], 'objects');
        $data['feed'] = self::get_feed($data['ical_feed_id'], 'ARRAY_A');
        
        $event = new RRZE_Calendar_Event($data);
        return $event;
    }
    
    public function get_events_count($feed_id) {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM " . self::$db_events_table . " WHERE ical_feed_id = " . absint($feed_id);
        $count = $wpdb->get_var($sql);
        return $count;
    }
    
    public function error($message = '', $error = 0, $status = '') {
        $message = !empty($message) ? $message : __('Unbekannten Fehler', 'rrze-calendar');
        $status = !empty($status) ? $status : __('FEHLER', 'rrze-calendar');

        $this->response->respond(array('message' => $message), $error, $status);
    }
        
    public static function options_url($atts = array()) {
        $atts = array_merge(
            array(
                'page' => 'rrze-calendar'
            ), $atts
        );

        if (isset($atts['action'])) {
            switch ($atts['action']) {
                case 'delete-category':
                case 'delete-tag':
                    $atts['nonce'] = wp_create_nonce($atts['action']);
                    break;
                default:
                    break;
            }
        }
        
        return add_query_arg($atts, get_admin_url(NULL, 'admin.php'));
    }
    
    public static function endpoint_url($slug = '') {
        return site_url(self::$options['endpoint_slug'] . '/' . $slug);
    }
    
    public static function endpoint_name() {
        return self::$options['endpoint_slug'];
    }
    
    public static function webcal_url($atts = array()) {
        $atts = array_merge(
            array(
                'plugin' => 'rrze-calendar',
                'action' => 'export',
                'feed-ids' => '',
                'event-ids' => '',
                'cb' => rand()
            ), $atts
        );
        return add_query_arg($atts, site_url('/'));
    }
    
    public static function get_param($param, $default = '') {
        if (isset($_POST[$param])) {
            return $_POST[$param];
        }
        
        if (isset($_GET[$param])) {
            return $_GET[$param];
        }
        
        return $default;
    }
    
    public function register_taxonomies() {
        $args = array(
            'public' => FALSE,
            'rewrite' => FALSE,
        );

        register_taxonomy(self::taxonomy_cat_key, 'post', $args);
        register_taxonomy(self::taxonomy_tag_key, 'post', $args);
    }
    
    public static function get_categories($args = array()) {

        if (!isset($args['hide_empty'])) {
            $args['hide_empty'] = 0;
        }

        $category_terms = get_terms(self::taxonomy_cat_key, $args);
        if (is_wp_error($category_terms) || empty($category_terms)) {
            return FALSE;
        }

        $categories = array();
        foreach ($category_terms as $category_term) {
            $categories[] = self::get_category_by('id', $category_term->term_id);
        }

        return $categories;
    }
    
    public static function get_tags($args = array()) {

        if (!isset($args['hide_empty'])) {
            $args['hide_empty'] = 0;
        }

        $tag_terms = get_terms(self::taxonomy_tag_key, $args);
        if (is_wp_error($tag_terms) || empty($tag_terms)) {
            return FALSE;
        }

        $tags = array();
        foreach ($tag_terms as $tag_term) {
            $tags[] = self::get_tag_by('id', $tag_term->term_id);
        }

        return $tags;
    }
    
    public static function get_category_by($field, $value) {

        $category = get_term_by($field, $value, self::taxonomy_cat_key);

        if (is_wp_error($category) || empty($category)) {
            return $category;
        }

        $category->feed_ids = array();
        $unencoded_description = self::get_unencoded_description($category->description);
        if (is_array($unencoded_description)) {
            foreach ($unencoded_description as $key => $value) {
                $category->$key = $value;
            }
        }

        return $category;
    }
    
    public static function get_tag_by($field, $value) {

        $tag = get_term_by($field, $value, self::taxonomy_tag_key);

        if (is_wp_error($tag) || empty($tag)) {
            return $tag;
        }

        $tag->feed_ids = array();
        $unencoded_description = self::get_unencoded_description($tag->description);
        if (is_array($unencoded_description)) {
            foreach ($unencoded_description as $key => $value) {
                $tag->$key = $value;
            }
        }

        return $tag;
    }
    
    public static function add_category($args = array(), $feed_ids = array()) {
        if (!isset($args['name'])) {
            return new WP_Error('invalid', __('Eine Kategorie muss einen Namen haben.', 'rrze-calendar'));
        }

        $name = $args['name'];
        $default = array(
            'name' => '',
            'slug' => sanitize_title($name),
            'description' => '',
        );
        $args = array_merge($default, $args);

        $args_to_encode = array(
            'description' => $args['description'],
            'feed_ids' => array_unique($feed_ids),
        );
        
        $encoded_description = self::get_encoded_description($args_to_encode);
        $args['description'] = $encoded_description;
        $category = wp_insert_term($name, self::taxonomy_cat_key, $args);

        if (is_wp_error($args)) {
            return $category;
        }

        return self::get_category_by('id', $category['term_id']);
    }

    public static function add_tag($args = array(), $feed_ids = array()) {
        if (!isset($args['name'])) {
            return new WP_Error('invalid', __('Ein Schlagwort muss einen Namen haben.', 'rrze-calendar'));
        }

        $name = $args['name'];
        $default = array(
            'name' => '',
            'slug' => sanitize_title($name),
            'description' => '',
        );
        $args = array_merge($default, $args);

        $args_to_encode = array(
            'description' => $args['description'],
            'feed_ids' => array_unique($feed_ids),
        );
        
        $encoded_description = self::get_encoded_description($args_to_encode);
        $args['description'] = $encoded_description;
        $tag = wp_insert_term($name, self::taxonomy_tag_key, $args);

        if (is_wp_error($args)) {
            return $tag;
        }

        return self::get_tag_by('id', $tag['term_id']);
    }
    
    public static function update_category($id, $args = array(), $feeds = NULL) {
        $existing_category = self::get_category_by('id', $id);
        if (is_wp_error($existing_category)) {
            return new WP_Error('invalid', __('Die Kategorie existiert nicht.', 'rrze-calendar'));
        }

        $args_to_encode = array();
        $args_to_encode['feed_ids'] = (!empty($feeds) && is_array($feeds)) ? $feeds : $existing_category->feed_ids;
        $args_to_encode['feed_ids'] = array_unique($args_to_encode['feed_ids']);
        $args_to_encode['description'] = isset($args['description']) ? $args['description'] : $existing_category->description;

        $encoded_description = self::get_encoded_description($args_to_encode);
        $args['description'] = $encoded_description;

        $category = wp_update_term($id, self::taxonomy_cat_key, $args);
        if (is_wp_error($category)) {
            return $category;
        }

        return self::get_category_by('id', $category['term_id']);
    }

    public static function update_tag($id, $args = array(), $feeds = NULL) {
        $existing_tag = self::get_tag_by('id', $id);
        if (is_wp_error($existing_tag)) {
            return new WP_Error('invalid', __('Das Schlagwort existiert nicht.', 'rrze-calendar'));
        }

        $args_to_encode = array();
        $args_to_encode['feed_ids'] = (!empty($feeds) && is_array($feeds)) ? $feeds : $existing_tag->feed_ids;
        $args_to_encode['feed_ids'] = array_unique($args_to_encode['feed_ids']);        
        $args_to_encode['description'] = isset($args['description']) ? $args['description'] : $existing_tag->description;
        
        $encoded_description = self::get_encoded_description($args_to_encode);
        $args['description'] = $encoded_description;

        $tag = wp_update_term($id, self::taxonomy_tag_key, $args);
        if (is_wp_error($tag)) {
            return $tag;
        }

        return self::get_tag_by('id', $tag['term_id']);
    }
    
    public static function delete_category($id) {
        $existing_category = self::get_category_by('id', $id);
        if (!empty($existing_category->feed_ids)) {
            return FALSE;
        }
                
        $retval = wp_delete_term($id, self::taxonomy_cat_key);
        return $retval;
    }
    
    public static function delete_tag($id) {
        $existing_tag = self::get_tag_by('id', $id);
        if (!empty($existing_category->feed_ids)) {
            return FALSE;
        }
                
        $retval = wp_delete_term($id, self::taxonomy_tag_key);
        return $retval;
    }
    
    public static function add_feed_to_category($feed_id, $term_id) {
        $existing_category = self::get_category_by('id', $term_id);
        $existing_category->feed_ids[] = $feed_id;
        $retval = self::update_category($term_id, NULL, $existing_category->feed_ids);
        
        if (is_wp_error($retval)) {
            return $retval;
        }

        return TRUE;
    }

    public static function add_feed_to_tag($feed_id, $term_id) {
        $existing_tag = self::get_tag_by('id', $term_id);
        $existing_tag->feed_ids[] = $feed_id;
        $retval = self::update_tag($term_id, NULL, $existing_tag->feed_ids);
        
        if (is_wp_error($retval)) {
            return $retval;
        }

        return TRUE;
    }
    
    public static function remove_feed_from_category($feed_id, $term_id) {
        $existing_category = self::get_category_by('id', $term_id);
        
        foreach ($existing_category->feed_ids as $key => $v) {
            if ($v == $feed_id) {
                unset($existing_category->feed_ids[$key]);
            }
        }
        
        $retval = self::update_category($term_id, NULL, $existing_category->feed_ids);
        
        if (is_wp_error($retval)) {
            return $retval;
        }

        return TRUE;
    }
    
    public static function remove_feed_from_tag($feed_id, $term_id) {
        $existing_tag = self::get_tag_by('id', $term_id);
        
        foreach ($existing_tag->feed_ids as $key => $v) {
            if ($v == $feed_id) {
                unset($existing_tag->feed_ids[$key]);
            }
        }
        
        $retval = self::update_tag($term_id, NULL, $existing_tag->feed_ids);
        
        if (is_wp_error($retval)) {
            return $retval;
        }

        return TRUE;
    }
    
    public static function get_category_for_feed($feed_id) {
        $all_categories = self::get_categories();

        if (!empty($all_categories)) {
            foreach ($all_categories as $category) {
                if (!in_array($feed_id, $category->feed_ids)) {
                    continue;
                }

                $color = strtoupper(get_term_meta($category->term_id, 'color', TRUE));
                $category->color = $color ? $color : '';
                $category->textcol = isset(self::$fau_colors[$color]) ? 'textcol-' . self::$fau_colors[$color]  : '';
                $category->bgcol = isset(self::$fau_colors[$color]) ? 'bgcol-' . self::$fau_colors[$color]  : '';
                
                return $category;
            }

        }
        
        return FALSE;
    }
    
    public static function get_tags_for_feed($feed_id, $ids_or_objects = 'ids') {
        $all_tags = self::get_tags();
        
        if (!empty($all_tags)) {
            $tag_objects_or_ids = array();
            foreach ($all_tags as $tag) {
                if (!in_array($feed_id, $tag->feed_ids)) {
                    continue;
                }

                if ($ids_or_objects == 'ids') {
                    $tag_objects_or_ids[] = (int) $tag->term_id;
                } elseif ($ids_or_objects == 'objects') {
                    $tag_objects_or_ids[] = $tag;
                }
            }
            return $tag_objects_or_ids;
        } else {
            return FALSE;
        }
    }
    
    public static function get_encoded_description($args = array()) {
        return base64_encode(maybe_serialize($args));
    }
    
    public static function get_unencoded_description($string_to_unencode) {
        return maybe_unserialize(base64_decode($string_to_unencode));
    }
    
    public static function sanitize_hex_color($color) {
        if ('' === $color) {
            return '';
        }

        if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color)) {
            return $color;
        }
    }

    public function select_form($list = NULL, $selected = NULL, $id = NULL, $title = NULL, $subtitle = NULL) {
        if (!is_array($selected)) {
            $selected = array();
        }
        ?>
        <?php if (is_array($list) && count($list)) : ?>
            <ul class="rrze-calendar-select-list">
            <?php foreach ($list as $v) : ?>
                <?php $checked = in_array($v->$id, $selected) ? ' checked="checked"' : ''; ?>
                <li>
                    <label for="rrze-calendar-selected">
                        <input type="checkbox" id="<?php echo esc_attr('rrze-calendar-selected-' . $v->$id) ?>" name="rrze_calendar_selected[]" value="<?php echo esc_attr($v->$id); ?>"<?php echo $checked; ?>>
                        <span class="rrze-calendar-selected-title"><?php echo esc_html($v->$title); ?></span>
                        <span class="rrze-calendar-selected-subtitle"><?php echo esc_html($v->$subtitle); ?></span>
                    </label>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p><?php _e('Keine Elemente gefunden.', 'rrze-calendar'); ?></p>
        <?php endif; ?>
        <?php
    }
    
    public function cron_schedule_event_hook() {
        global $wpdb;

        $sql = "SELECT * FROM " . self::$db_feeds_table . " WHERE active <> 0";
        $feeds = $wpdb->get_results($sql);
        
        foreach ($feeds as $feed) {
            self::flush_feed($feed->id, FALSE);
            $this->parse_ics_feed($feed);
        }
    }
    
    public static function flush_feed($feed_id = NULL, $ajax = TRUE) {
        global $wpdb;

        if (!$feed_id) {
            $feed_id = isset($_REQUEST['feed-id']) ? (int) $_REQUEST['feed-id'] : 0;           
            $feed_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::$db_feeds_table . " WHERE id = %d", $feed_id));
        }

        if ($feed_id) {

            $count = $wpdb->delete(self::$db_events_cache_table, array('ical_feed_id' => $feed_id), array('%d'));
            
            if ($count !== FALSE) {
                $count = $wpdb->delete(self::$db_events_table, array('ical_feed_id' => $feed_id), array('%d'));
            }
            
            $output = array(
                'error' => $count !== FALSE ? FALSE : TRUE,
                'message' => $count !== FALSE ? sprintf(__('%d Termine gelöscht.', 'rrze-calendar'), $count) : __('Ein Fehler ist aufgetretten.', 'rrze-calendar'),
                'count' => (int) $count,
            );
            
        } else {
            $output = array(
                'error' => TRUE,
                'message' => __('Ungültige Feed-ID.', 'rrze-calendar')
            );
        }

        if ($ajax) {
            //$this->json_response($output);
        }
    }
    
    private function parse_ics_feed(&$feed) {
        global $wpdb;
        
        if (!defined('ICALCREATOR_VERSION')) {
            require_once(plugin_dir_path(RRZE_Calendar::$plugin_file) . 'includes/icalcreator.php');
        }
        
        $count = 0;
        $config = array('unique_id' => 'events');

        $v = new vcalendar(array(
            'unique_id' => $feed->url,
            'url' => $feed->url,
        ));

        if ($v->parse()) {
            $v->sort();
            $v->components = array_reverse($v->components);

            $timezone = $v->getProperty('X-WR-TIMEZONE');
            $timezone = $timezone[1];
            
            while ($e = $v->getComponent('vevent')) {
                $start = $e->getProperty('dtstart', 1, TRUE);
                $end = $e->getProperty('dtend', 1, TRUE);

                if (empty($end)) {
                    $end = $e->getProperty('duration', 1, TRUE, TRUE);
                    if (empty($end)) {
                        if (!isset($start['value']['hour'])) {
                            $end = array(
                                'year' => $start['value']['year'],
                                'month' => $start['value']['month'],
                                'day' => $start['value']['day'],
                                'hour' => 23,
                                'min' => 59,
                                'sec' => 59,
                                'tz' => $start['value']['tz']
                            );
                        } else {
                            $end = $start;
                        }
                    }
                }

                $allday = !isset($start['value']['hour']);

                $ms_allday = $e->getProperty('X-MICROSOFT-CDO-ALLDAYEVENT');
                if (!empty($ms_allday) && $ms_allday[1] == 'TRUE') {
                    $allday = TRUE;
                }

                $start = RRZE_Calendar_Functions::time_array_to_timestamp($start, $timezone);
                $end = RRZE_Calendar_Functions::time_array_to_timestamp($end, $timezone);

                if ($allday && $start === $end) {
                    $end += 24 * 60 * 60;
                }

                if ($allday) {
                    $start = RRZE_Calendar_Functions::gmt_to_local($start);
                    $start = RRZE_Calendar_Functions::gmgetdate($start);
                    $start = gmmktime(0, 0, 0, $start['mon'], $start['mday'], $start['year']);
                    $start = RRZE_Calendar_Functions::local_to_gmt($start);
                    $end = RRZE_Calendar_Functions::gmt_to_local($end);
                    $end = RRZE_Calendar_Functions::gmgetdate($end);
                    $end = gmmktime(0, 0, 0, $end['mon'], $end['mday'], $end['year']);
                    $end = RRZE_Calendar_Functions::local_to_gmt($end);
                }

                if ($rrule = $e->createRrule()) {
                    $rrule = trim(end(explode(':', $rrule)));
                }
                if ($exrule = $e->createExrule()) {
                    $exrule = trim(end(explode(':', $exrule)));
                }
                if ($rdate = $e->createRdate()) {
                    $rdate = trim(end(explode(':', $rdate)));
                }
                if ($exdate = $e->createExdate()) {
                    $exdate = trim(end(explode(':', $exdate)));
                }

                $data = array(
                    'start' => $start,
                    'end' => $end,
                    'allday' => $allday,
                    'recurrence_rules' => $rrule,
                    'exception_rules' => $exrule,
                    'recurrence_dates' => $rdate,
                    'exception_dates' => $exdate,
                    'summary' => $e->getProperty('summary'),
                    'description' => stripslashes(str_replace('\n', "\n", $e->getProperty('description'))),
                    'location' => $e->getProperty('location'),
                    'slug' => self::make_slug($e->getProperty('summary')),
                    'ical_feed_id' => $feed->id,
                    'ical_feed_url' => $feed->url,
                    'ical_uid' => $e->getProperty('uid'),
                    'ical_source_url' => $e->getProperty('url')
                );

                $event = new RRZE_Calendar_Event($data);

                $matching_event_id = $this->get_matching_event_id(
                    $data['ical_uid'], $data['ical_feed_url'], $data['start'], !empty($data['recurrence_rules'])
                );
                
                if (is_null($matching_event_id)) {
                    $row = $wpdb->insert(self::$db_events_table, $data, array('FROM_UNIXTIME(%d)', 'FROM_UNIXTIME(%d)', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'));
                    $event->id = $row !== FALSE ? $wpdb->insert_id : NULL;
                } else {
                    $event->id = $matching_event_id;
                    $this->delete_event_cache($matching_event_id);
                }
                
                if (is_null($event->id)) {
                    continue;
                }
                
                $this->cache_event($event);
                $count++;
            }
        }

        return $count;
    }
        
    public static function make_slug($str) {
        $slug = sanitize_title_with_dashes(remove_accents($str));
        return self::unique_slug($slug);
    }
    
    public static function unique_slug($slug) {
        global $wpdb;
        
        $query = $wpdb->prepare("SELECT slug FROM " . self::$db_events_table . " WHERE slug = %s", $slug);
        if ($wpdb->get_var($query)) {
            $num = 2;
            do {
                $alt_slug = $slug . "-$num";
                $num++;
                $slug_check = $wpdb->get_var( $wpdb->prepare("SELECT slug FROM " . self::$db_events_table . " WHERE slug = %s", $alt_slug));
            } while ($slug_check);
            $slug = $alt_slug;
        }
        
        return $slug;
    }
    
    public static function get_events_between($start_time, $end_time, $filter, $spanning = FALSE) {
        global $wpdb;

        $start_time = RRZE_Calendar_Functions::local_to_gmt($start_time);
        $end_time = RRZE_Calendar_Functions::local_to_gmt($end_time);

        $args = array($start_time, $end_time);

        self::get_filter_sql($filter);
        
        $query = $wpdb->prepare(
            "SELECT e.*, " .
            "UNIX_TIMESTAMP(i.start) AS start, " .
            "UNIX_TIMESTAMP(i.end) AS end, " .
            "IF(e.allday, e.allday, i.end = DATE_ADD(i.start, INTERVAL 1 DAY)) AS allday, " .
            "e.recurrence_rules, e.exception_rules, e.recurrence_dates, e.exception_dates, " .
            "e.summary, e.description, e.location, e.slug, " .
            "e.ical_feed_id, e.ical_feed_url, e.ical_source_url, e.ical_uid " .
            "FROM " . self::$db_events_table . " e " .
            "INNER JOIN " . self::$db_events_cache_table . " i ON e.id = i.event_id " .
            "WHERE " . ($spanning ? "i.end > FROM_UNIXTIME(%d) AND i.start < FROM_UNIXTIME(%d) " : "i.start >= FROM_UNIXTIME(%d) AND i.start < FROM_UNIXTIME(%d) ") .
            $filter['filter_where'] .
            "ORDER BY allday DESC, i.start ASC, summary ASC", $args);

        $events = $wpdb->get_results($query, ARRAY_A);

        foreach ($events as &$event) {
            $event['category'] = self::get_category_for_feed($event['ical_feed_id']);
            $event['tags'] = self::get_tags_for_feed($event['ical_feed_id'], 'objects');
            $event['feed'] = self::get_feed($event['ical_feed_id'], 'ARRAY_A');
            
            $event = new RRZE_Calendar_Event($event);
        }

        return $events;
    }
        
    public static function get_events_relative_to($time, $limit = 0, $page_offset = 0, $filter = array()) {
        global $wpdb;

        $bits = RRZE_Calendar_Functions::gmgetdate($time);

        $time = RRZE_Calendar_Functions::local_to_gmt($time);

        $args = array($time);

        if ($page_offset >= 0) {
            $first_record = $page_offset * $limit;
        } else {
            $first_record = (-$page_offset - 1) * $limit;
        }
        
        self::get_filter_sql($filter);

        $query = $wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS e.*, " .
            "UNIX_TIMESTAMP(i.start) AS start, " .
            "UNIX_TIMESTAMP(i.end) AS end, " .
            "IF(e.allday, e.allday, i.end = DATE_ADD(i.start, INTERVAL 1 DAY)) AS allday, " .
            "e.recurrence_rules, e.exception_rules, e.recurrence_dates, e.exception_dates, " .
            "e.summary, e.description, e.location, e.slug, " .
            "e.ical_feed_id, e.ical_feed_url, e.ical_source_url, e.ical_uid " .
            "FROM " . self::$db_events_table . " e " .
            "INNER JOIN " . self::$db_events_cache_table . " i ON e.id = i.event_id " .    
            "WHERE " .
            ($page_offset >= 0 ? "i.end >= FROM_UNIXTIME(%d) " : "i.start < FROM_UNIXTIME(%d) ") .
            $filter['filter_where'] .
            "GROUP BY i.event_id ORDER BY i.start " . ($page_offset >= 0 ? "ASC" : "DESC") .
            ($limit > 0 ? " LIMIT $first_record, $limit" : ""), $args);

        $events = $wpdb->get_results($query, ARRAY_A);

        if ($page_offset < 0) {
            $events = array_reverse($events);
        }

        foreach ($events as &$event) {
            $event['category'] = self::get_category_for_feed($event['ical_feed_id']);
            $event['tags'] = self::get_tags_for_feed($event['ical_feed_id'], 'objects');
            $event['feed'] = self::get_feed($event['ical_feed_id'], 'ARRAY_A');
            
            $event = new RRZE_Calendar_Event($event);
        }

        $more = $wpdb->get_var('SELECT FOUND_ROWS()') > $first_record + $limit;

        if ($page_offset > 0) {
            $prev = TRUE;
            $next = $more;
        } elseif ($page_offset < 0) {
            $prev = $more;
            $next = TRUE;
        } else {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) " .
                "FROM " . self::$db_events_table . " e " .
                "INNER JOIN " . self::$db_events_cache_table . " i ON e.id = i.event_id " .
                "WHERE i.start < FROM_UNIXTIME(%d) " .
                $filter['filter_where'], $args);
            $prev = $wpdb->get_var($query);
            $next = $more;
        }
        return array(
            'events' => $events,
            'prev' => $prev,
            'next' => $next,
        );
    }
    
    private static function get_filter_sql(&$filter) {
        global $wpdb;

        $filter['filter_where'] = '';

        $where_logic = "AND (";

        foreach ($filter as $filter_type => $filter_ids) {

            if ($filter_ids && is_array($filter_ids)) {
                switch ($filter_type) {
                    case 'feed_ids':
                        $filter['filter_where'] .= $where_logic . " e.ical_feed_id IN (" . implode(',', $filter_ids) . ") ";
                        $where_logic = "OR ";
                        break;
                    case 'event_ids':
                        $filter['filter_where'] .= $where_logic . " e.id IN (" . implode(',', $filter_ids) . ") ";
                        $where_logic = "OR ";
                        break;
                }
            }
        }

        if ($filter['filter_where'] != '') {
            $filter['filter_where'] .= ") ";
        }
    }
    
    private function get_rule_dates($start, $ics_rule) {
        require_once(plugin_dir_path(self::$plugin_file) . 'includes/calendar-rules.php');
        $rules = new RRZE_Calendar_Rules($ics_rule, $start, array(), array(), TRUE);
        return $rules->get_all_occurrences();
    }
    
    public function date_match_exdates($date, $ics_rule) {
        foreach (explode(",", $ics_rule) as $_date) {

            $_date_start = strtotime($_date);

            $_date_start = RRZE_Calendar_Functions::gmt_to_local($_date_start) - date('Z', $_date_start);
            if ($_date_start != FALSE) {

                $_date_end = $_date_start + (24 * 60 * 60) - 1;
                if ($date >= $_date_start && $date <= $_date_end) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }
    
    private function cache_event(&$event) {
        global $wpdb;
        
        $event->start = RRZE_Calendar_Functions::gmt_to_local($event->start) - date('Z', $event->start);
        $event->end = RRZE_Calendar_Functions::gmt_to_local($event->end) - date('Z', $event->end);

        $evs = array();
        $e = array(
            'event_id' => $event->id,
            'start' => $event->start,
            'end' => $event->end,
        );
        $duration = $event->get_duration();

        $tif = time() + 315569260;

        $evs[] = $e;

        $_start = $event->start;
        $_end = $event->end;

        if ($event->recurrence_rules) {
            $count = 0;
            $start = $event->start;
            $exrule = array();
            if ($event->exception_rules) {
                $exrule = $this->get_rule_dates($start, $event->exception_rules);
            }
            $rules = $event->get_rules($exrule);

            $rules->first_occurrence();
            while (($next = $rules->next_occurrence($start)) > 0 && $count < 1000) {
                $count++;
                $start = $next;
                $e['start'] = $start;
                $e['end'] = $start + $duration;
                $excluded = FALSE;

                if ($start > $tif) {
                    break;
                }

                if ($event->exception_dates) {
                    if ($this->date_match_exdates($start, $event->exception_dates)) {
                        $excluded = TRUE;
                    }
                }

                if ($excluded == FALSE) {
                    $evs[] = $e;
                }
            }
        }

        $evs_unique = array();
        foreach ($evs as $ev) {
            $evs_unique[md5(serialize($ev))] = $ev;
        }

        foreach ($evs_unique as $e) {
            $matching_event_id = $event->ical_uid ?
                $this->get_matching_event_id (
                    $event->ical_uid, $event->ical_feed_url, $start = RRZE_Calendar_Functions::local_to_gmt($e['start']) - date('Z', $e['start']), FALSE, $event->id
                ) : NULL;

            if (is_null($matching_event_id)) {
                $start = getdate($e['start']);
                $end = getdate($e['end']);
                
                $e['ical_feed_id'] = $event->ical_feed_id;
                
                $this->insert_event_in_cache($e);
            }
        }
    }
    
    public function get_matching_event_id($ical_uid, $ical_feed_url, $start, $has_recurrence = FALSE) {
        global $wpdb;

        $query = "SELECT id FROM " . self::$db_events_table . " WHERE ical_feed_url = %s
            AND ical_uid = %s
            AND start = FROM_UNIXTIME(%d)
            ($has_recurrence ? 'AND NOT ' : 'AND ') .
            (recurrence_rules IS NULL OR recurrence_rules = '')";

        return $wpdb->get_var($wpdb->prepare($query, array($ical_feed_url, $ical_uid, $start)));
    }
    
    private function insert_event_in_cache($event) {
        global $wpdb;

        $event['start'] = RRZE_Calendar_Functions::local_to_gmt($event['start']) + date('Z', $event['start']);
        $event['end'] = RRZE_Calendar_Functions::local_to_gmt($event['end']) + date('Z', $event['end']);
        
        $wpdb->insert(self::$db_events_cache_table, $event, array('%d', 'FROM_UNIXTIME(%d)', 'FROM_UNIXTIME(%d)', '%d'));
    }
    
    private function delete_event_cache($event_id) {
        global $wpdb;
        
        $wpdb->delete(self::$db_events_cache_table, array('event_id' => $event_id), array('%d'));
    }
        
    public function get_matching_events($start = FALSE, $end = FALSE, $filter = array()) {
        global $wpdb;

        $start_where_sql = '';
        $end_where_sql = '';
        $args = array();

        if ($start !== FALSE) {
            $start_where_sql = " AND (e.start >= FROM_UNIXTIME(%d) OR e.recurrence_rules != '')";
            $args[] = $start;
        }

        if ($end !== FALSE) {
            $end_where_sql = " AND (e.end <= FROM_UNIXTIME(%d) OR e.recurrence_rules != '')";
            $args[] = $end;
        }

        self::get_filter_sql($filter);

        $query = 
            "SELECT e.*, UNIX_TIMESTAMP(e.start) as start, UNIX_TIMESTAMP(e.end) as end, e.allday,
            e.recurrence_rules, e.exception_rules, e.recurrence_dates, e.exception_dates,
            e.summary, e.description, e.location, e.slug,
            e.ical_feed_id, e.ical_feed_url, e.ical_source_url, e.ical_uid " .
            "FROM " . self::$db_events_table . " e " .
            "WHERE 1 = 1 " .
            $filter['filter_where'] .
            $start_where_sql .
            $end_where_sql;

        $query = !empty($args) ? $wpdb->prepare($query, $args) : $query;
        $events = $wpdb->get_results($query, ARRAY_A);

        foreach ($events as &$event) {
            try {
                $event['category'] = self::get_category_for_feed($event['ical_feed_id']);
                $event['tags'] = self::get_tags_for_feed($event['ical_feed_id'], 'objects');
                $event['feed'] = self::get_feed($event['ical_feed_id'], 'ARRAY_A');
                
                $event = new RRZE_Calendar_Event($event);
            } catch (Event_Not_Found $n) {
                unset($event);
                continue;
            }

            if (empty($event->recurrence_rules)) {
                if ($start !== FALSE && $event->start < $start) {
                    unset($event);
                    continue;
                }
                if ($end !== FALSE && $ev->end < $end) {
                    unset($event);
                    continue;
                }
            }
        }

        return $events;
    }
    
    public function export_request() {
        $plugin = self::get_param('plugin');
        $action = self::get_param('action');

        if ($plugin == 'rrze-calendar' && $action == 'export') {
            if (!defined('ICALCREATOR_VERSION')) {
                require_once(plugin_dir_path(RRZE_Calendar::$plugin_file) . 'includes/icalcreator.php');
            }            
            require_once(plugin_dir_path(self::$plugin_file) . 'includes/calendar-export.php');            
            $event_explorer = RRZE_Calendar_Export::instance();
            $event_explorer->export_events();
        }
    }

    public function admin_notices() {        
        $this->display_admin_notices();
    }
    
    public function add_admin_notice($message, $class = 'updated') {
        $allowed_classes = array('error', 'updated');
        if (!in_array($class, $allowed_classes)) {
            $class = 'updated';
        }

        $transient = self::notice_transient . get_current_user_id();
        $transient_value = get_transient($transient);
        $notices = maybe_unserialize($transient_value ? $transient_value : array());
        $notices[$class][] = $message;

        set_transient($transient, $notices, self::notice_transient_expiration);
    }

    public function display_admin_notices() {
        $transient = self::notice_transient . get_current_user_id();
        $transient_value = get_transient($transient);
        $notices = maybe_unserialize($transient_value ? $transient_value : '');
        
        if (is_array($notices)) {
            foreach ($notices as $class => $messages) {
                foreach ($messages as $message) :
                    ?>
                    <div class="<?php echo $class; ?>">
                        <p><?php echo $message; ?></p>
                    </div>
                    <?php
                endforeach;
            }
        }
        
        delete_transient($transient);

    }
    
    public function add_settings_error($field, $value = '', $message = '') {
        $transient = self::settings_error_transient . get_current_user_id();
        $transient_value = get_transient($transient);
        $errors = maybe_unserialize($transient_value ? $transient_value : array());
        $errors[$field] = array('value' => $value, 'message' => $message);

        set_transient($transient, $errors, self::settings_error_transient_expiration);
    }
    
    public function settings_errors() {
        $transient = self::settings_error_transient . get_current_user_id();
        $transient_value = get_transient($transient);
        $errors = maybe_unserialize($transient_value ? $transient_value : '');
        
        if (is_array($errors)) {
            return $errors;
        }
        
        return FALSE;
    }
    
    public function delete_settings_errors() {     
        delete_transient(self::settings_error_transient . get_current_user_id());
    }
  
}

class Event_Not_Found extends Exception {
    
}
