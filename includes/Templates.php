<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

/**
 * Templates class
 * @package RRZE\Calendar
 */
class Templates
{
    /**
     * FAU & RRZE Themes
     * @var array
     */
    protected static $themes = [
        'fau' => [
            'FAU-Einrichtungen',
            'FAU-Einrichtungen-BETA',
            'FAU-Medfak',
            'FAU-RWFak',
            'FAU-Philfak',
            'FAU-Techfak',
            'FAU-Natfak'
        ],
        'fau-events' => [
            'FAU-Events',
            'FAU-Events-UTN',
        ],
        'rrze' => [
            'rrze-2019'
        ],
        'vendor' => [
            'Francesca-Child'
        ]
    ];

    /**
     * FAU Colors
     * @var array
     */
    protected static $fauColors = [
        '#041E42' => 'default', // FAU
        '#963B2F' => 'phil',    // Phil
        '#662938' => 'rw',      // RW
        '#003E61' => 'med',     // Med
        '#14462D' => 'nat',     // Nat
        '#204251' => 'tf'       // TF
    ];

    /**
     * Endpoint Template Path
     * @var string
     */
    protected static $endpointTplPath = 'templates/endpoint';

    /**
     * Endpoint Events Template
     * @var string
     */
    protected static $endpointEventsTpl = 'events.php';

    /**
     * Single Event Template
     * @var string
     */
    protected static $endpointSingleEventsTpl = 'single-event.php';

    /**
     * Shortcodes Template Path
     * @var string
     */
    protected static $shortcodesTplPath = 'templates/shortcodes';

    /**
     * Shortcode Events Template
     * @var string
     */
    protected static $shortcodeEventsTpl = 'events.php';

    /**
     * Shortcode Calendar Template
     * @var string
     */
    protected static $shortcodeCalendarTpl = 'calendar.php';

    /**
     * CPT Template Path
     * @var string
     */
    protected static $cptTplPath = 'templates/cpt';

    /**
     * CPT CalendarEvent Template
     * @var string
     */
    protected static $cptCalendarEventTpl = 'archive-event.php';

    /**
     * CPT CalendarEvent Single Template
     * @var string
     */
    protected static $cptCalendarEventSingleTpl = 'single-event.php';

    /**
     * Get Template Path
     * @var string
     */
    protected static function getTplPath(string $tpl): string
    {
        switch ($tpl) {
            case 'endpoint':
                $tpl = self::$endpointTplPath;
                break;
            case 'cpt':
                $tpl = self::$cptTplPath;
                break;
            case 'shortcode':
            default:
                $tpl = self::$shortcodesTplPath;
        }
        $currentTheme = wp_get_theme();
        $tplPath = '';
        foreach (self::getThemes() as $path => $theme) {
            if (in_array(strtolower($currentTheme->stylesheet), array_map('strtolower', $theme))) {
                $tplPath = plugin()->getPath($tpl . '/themes/' . $path);
                break;
            }
        }
        return is_dir($tplPath) ? $tplPath : plugin()->getPath($tpl);
    }

    /**
     * Get endpoint templates path
     * @return string Endpoint templates path
     */
    public static function getEndpointTplPath()
    {
        return self::getTplPath('endpoint');
    }

    /**
     * Get shortcodes templates path
     * @return string Shortcodes templates path
     */
    public static function getShortcodesTplPath()
    {
        return self::getTplPath('shortcodes');
    }

    /**
     * Get CPT templates path
     * @return string Shortcodes templates path
     */
    public static function getCptTplPath()
    {
        return self::getTplPath('cpt');
    }

    /**
     * Get the endpoint events template name
     * @return string Endpoint events template filename
     */
    public static function endpointEventsTpl()
    {
        return self::$endpointEventsTpl;
    }

    /**
     * Get the endpoint single event template name
     * @return string Endpoint single event template filename
     */
    public static function endpointSingleEventsTpl()
    {
        return self::$endpointSingleEventsTpl;
    }

    /**
     * Get the shortcode events template
     * @return string Endpoint events template
     */
    public static function getEndpointEventsTpl()
    {
        return self::getEndpointTplPath() . self::$endpointEventsTpl;
    }

    /**
     * Get the endpoint single event template
     * @return string Endpoint single event template
     */
    public static function getEndpointSingleEventsTpl()
    {
        return self::getEndpointTplPath() . self::$endpointSingleEventsTpl;
    }

    /**
     * Get Shortcode Events Template
     * @return string Shortcode events template
     */
    public static function getShortcodeEventsTpl()
    {
        return self::getShortcodesTplPath() . self::$shortcodeEventsTpl;
    }

    /**
     * Get Shortcode Calendar Template
     * @return string Shortcode calendar template
     */
    public static function getShortcodeCalendarTpl()
    {
        return self::getShortcodesTplPath() . self::$shortcodeCalendarTpl;
    }

    /**
     * Get CPT Events Template
     * @return string Shortcode events template
     */
    public static function getCptCalendarEventTpl()
    {
        return self::getCptTplPath() . self::$cptCalendarEventTpl;
    }

    /**
     * Get CPT Single Event Template
     * @return string Shortcode events template
     */
    public static function getCptCalendarEventSingleTpl()
    {
        return self::getCptTplPath() . self::$cptCalendarEventSingleTpl;
    }

    /**
     * Get FAU Colors
     * @return array FAU Colors
     */
    public static function getFauColors()
    {
        return apply_filters('rrze_calendar_fau_colors', self::$fauColors);
    }

    /**
     * Get Themes
     * @return array Themes
     */
    public static function getThemes()
    {
        return apply_filters('rrze_calendar_themes', self::$themes);
    }

    /*
     * Register Block Templates
     */
    public static function init(): void {
        add_action( 'init', [ self::class, 'register_block_templates' ] );
        add_filter( 'render_block_core/post-content', [ self::class, 'render_event_post_content' ], 10, 3 );
        add_filter( 'pre_render_block', [ self::class, 'render_event_archive_query' ], 10, 2 );
    }

    /**
     * Get the events archive content partial (shared by the classic and the block template)
     * @return string Path to the partial
     */
    public static function getCptArchiveEventContentTpl(): string {
        return plugin()->getPath( 'templates/cpt/parts' ) . 'archive-event-content.php';
    }

    /**
     * Replace the main query loop (core/query with inherit) on event archives
     * with the events archive content partial.
     *
     * Uses pre_render_block, so the query loop itself is not rendered.
     *
     * @param string|null $pre         Pre-rendered content (null = render normally).
     * @param array       $parsedBlock Parsed block.
     * @return string|null
     */
    public static function render_event_archive_query( $pre, $parsedBlock ) {
        if (
            null !== $pre
            || ( $parsedBlock['blockName'] ?? '' ) !== 'core/query'
            || empty( $parsedBlock['attrs']['query']['inherit'] )
            || ! ( is_post_type_archive( CPT\CalendarEvent::POST_TYPE ) || is_tax( CPT\CalendarEvent::TAX_CATEGORY ) )
        ) {
            return $pre;
        }

        $showTitle = false; // Title is rendered by core/query-title in the block template.

        ob_start();
        include self::getCptArchiveEventContentTpl();

        return '<div class="wp-block-query rrze-calendar-archive">' . ob_get_clean() . '</div>';
    }

    /**
     * Get the event content partial (shared by the classic and the block template)
     * @return string Path to the partial
     */
    public static function getCptSingleEventContentTpl(): string {
        return plugin()->getPath( 'templates/cpt/parts' ) . 'single-event-content.php';
    }

    /**
     * Replace the (empty) core/post-content output of events with the event content partial.
     *
     * @param string    $content  Rendered block content.
     * @param array     $block    Parsed block.
     * @param \WP_Block $instance Block instance.
     * @return string
     */
    public static function render_event_post_content( $content, $block, $instance ): string {
        $postId = (int) ( $instance->context['postId'] ?? get_the_ID() );
        if ( ! $postId || get_post_type( $postId ) !== CPT\CalendarEvent::POST_TYPE ) {
            return (string) $content;
        }

        wp_enqueue_style( 'rrze-calendar-sc-events' );
        wp_enqueue_style( 'dashicons' );

        $data      = CPT\CalendarEvent::getEventData( $postId );
        $showTitle = false; // Title is rendered by core/post-title in the block template.

        ob_start();
        include self::getCptSingleEventContentTpl();

        return '<div class="entry-content wp-block-post-content">' . ob_get_clean() . '</div>';
    }

    public static function register_block_templates(): void {

        if ( ! function_exists( 'register_block_template' ) ) {
            return;
        }

        $templates = [
            // The slug must match the template hierarchy: single-{post_type}, archive-{post_type}, taxonomy-{taxonomy}.
            'single-' . CPT\CalendarEvent::POST_TYPE => [
                'file'        => 'single-event.html',
                'title'       => __( 'Single Event', 'rrze-calendar' ),
                'description' => __( 'Displays a single event.', 'rrze-calendar' ),
                'post_types'  => [ CPT\CalendarEvent::POST_TYPE ],
            ],
            'archive-' . CPT\CalendarEvent::POST_TYPE => [
                'file'        => 'archive-event.html',
                'title'       => __( 'Event Archive', 'rrze-calendar' ),
                'description' => __( 'Displays the event archive.', 'rrze-calendar' ),
            ],
            'taxonomy-' . CPT\CalendarEvent::TAX_CATEGORY => [
                'file'        => 'archive-event.html',
                'title'       => __( 'Event Category Archive', 'rrze-calendar' ),
                'description' => __( 'Displays the events of an event category.', 'rrze-calendar' ),
            ],
        ];

        foreach ( $templates as $slug => $args ) {

            $file = plugin()->getPath( 'templates/block' ) . $args['file'];
            unset( $args['file'] );

            if ( ! is_readable( $file ) ) {
                continue;
            }

            register_block_template(
                'rrze-calendar//' . $slug,
                [
                    ...$args,
                    'content' => file_get_contents( $file ),
                ]
            );
        }
    }
}
