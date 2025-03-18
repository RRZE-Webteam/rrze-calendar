<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\CPT\CalendarEvent;
use RRule\RRule;
use function RRZE\Calendar\plugin;

/**
 * Export class
 * @package RRZE\Calendar\ICS
 */
class Export
{
    /**
     * @var string
     */
    private $vcalendar = '';

    /**
     * Export constructor.
     * Register the 'init' action hook.
     * @return void
     */
    public function __construct()
    {
        add_action('init', [$this, 'request']);
    }

    /**
     * Request handler
     * @return void
     */
    public function request()
    {
        $plugin = $_GET['ical-plugin'] ?? false;
        $action = $_GET['action'] ?? false;
        $filename = $_GET['filename'] ?? '';
        $urlHost = sanitize_title(parse_url(site_url(), PHP_URL_HOST));
        if (strpos($filename, $urlHost) === false) {
            $filename = $urlHost;
        }

        if (
            $plugin === sanitize_title(plugin()->getSlug())
            && $action === 'export'
        ) {
            $postIds = $_GET['ids'] ?? '';
            $cats = $_GET['cats'] ?? '';
            $tags = $_GET['tags'] ?? '';
            $args = [
                'postIds' => array_filter(explode(',', $postIds)),
                'categories' => array_filter(explode(',', $cats)),
                'tags' => array_filter(explode(',', $tags)),
            ];

            $this->set($args);
            $this->stream($filename);
        }
    }

    /**
     * Set events
     * @param array $args
     * @return void
     */
    private function set(array $args)
    {
        $postIds = $args['postIds'] ?? null;
        $categories = $args['categories'] ?? null;
        $tags = $args['tags'] ?? null;
        $postIn = null;
        $taxQuery = null;
        $posts = null;

        if (is_array($postIds) && !empty($postIds)) {
            $postIn = $postIds;
        }

        if (is_array($categories) && !empty($categories)) {
            $taxQuery = [
                [
                    'taxonomy' => CalendarEvent::TAX_CATEGORY,
                    'field'    => 'id',
                    'terms'    => $categories
                ]
            ];
        }

        if (is_array($tags) && !empty($tags)) {
            $taxQuery = array_merge(
                $taxQuery,
                [
                    [
                        'taxonomy' => CalendarEvent::TAX_TAG,
                        'field'    => 'id',
                        'terms'    => $tags
                    ]
                ]
            );
        }

        $args = [
            'numberposts' => -1,
            'post_type'   => CalendarEvent::POST_TYPE,
            'post_status' => 'publish'
        ];

        if (!empty($postIn)) {
            $args = array_merge($args, ['post__in' => $postIn]);
        }
        if (!empty($taxQuery)) {
            $taxQuery = array_merge(['relation' => 'AND'], $taxQuery);
            $args = array_merge($args, ['tax_query' => $taxQuery]);
        }

        if ($postIn || $taxQuery) {
            $posts = get_posts($args);
            $data = $this->getEvents($posts);
            self::build(@$data);
        }
    }

    /**
     * Get events
     * @param array $posts
     * @return array
     */
    private function getEvents(array $posts)
    {
        $data = [];
        foreach ($posts as $post) {
            $meta = get_post_meta($post->ID, '', true);
            $args = [
                'summary' => $post->post_title,
                'uid' => $meta['event-uid'][0] ?? '',
                'description' => $meta['description'][0] ?? '',
                'dtstart' => get_gmt_from_date(date('Y-m-d H:i', $meta['start'][0]), 'Y-m-d H:i:s'),
                'dtend' => !empty($meta['end'][0]) ? get_gmt_from_date(date('Y-m-d H:i', $meta['end'][0]), 'Y-m-d H:i:s') : '',
                'location' => $meta['location'][0] ?? '',
            ];
            $icsMeta = Utils::getMeta($meta, 'ics_event_meta');
            $rrule = !empty($icsMeta['rrule']) ? maybe_unserialize($icsMeta['rrule']) : '';
            if (empty($rrule)) {
                // RRULE
                $rruleArgs = Utils::getMeta($meta, 'event-rrule-args');
                if ($decodedRruleArgs = json_decode($rruleArgs, true)) {
                    $rruleObj = new RRule($decodedRruleArgs);
                    $rrule = $rruleObj->rfcString();
                    $rruleStartPos = strpos($rrule, 'RRULE:');
                    if ($rruleStartPos !== false) {
                        $rrule = substr($rrule, $rruleStartPos + strlen('RRULE:'));
                    }
                }
                if ($rrule) {
                    // EXDATE (dates exceptions)
                    $exDate = [];
                    $exceptions = Utils::getMeta($meta, 'exceptions');
                    if (!empty($exceptions)) {
                        $exceptions = explode(',', $exceptions);
                        foreach ($exceptions as $datetimeStr) {
                            if (Utils::validateDate($datetimeStr)) {
                                $datetime = new \DateTime($datetimeStr);
                                $exDate[] = $datetime->format('Ymd');
                            }
                        }
                    }
                    // RDATE (dates additions)
                    $rDate = [];
                    $additions = Utils::getMeta($meta, 'additions');
                    if (!empty($additions)) {
                        $additions = explode(',', $additions);
                        foreach ($additions as $datetimeStr) {
                            if (Utils::validateDate($datetimeStr)) {
                                $datetime = new \DateTime($datetimeStr);
                                $rDate[] = $datetime->format('Ymd');
                            }
                        }
                    }
                    if (!empty($exDate)) {
                        $args['exdate;value=date'] = implode(',', $exDate);
                    }
                    if (!empty($rDate)) {
                        $args['rdate;value=date'] = implode(',', $rDate);
                    }
                }
            }
            $args['rrule'] = !empty($rrule) ? $rrule : '';

            $data[$post->ID] = $args;
        }
        return $data;
    }

    /**
     * Build calendar
     * @param array $data
     * @return void
     */
    private function build(array $data)
    {
        // Setup calendar
        $ics = new ICS($data);

        // Output .ics formatted text
        $this->vcalendar = $ics->build();
    }

    /**
     * Stream calendar
     * @param string $filename
     * @return void
     */
    private function stream(string $filename)
    {
        header('Content-Type: text/calendar; charset=' . get_option('blog_charset'), true);
        header('Content-Disposition: attachment; filename=' . $filename . '.ics');
        echo $this->vcalendar;
        exit;
    }

    /**
     * Make ICS Link
     * @param array $args
     * @return string
     */
    public static function makeIcsLink(array $args)
    {
        $url = home_url($_SERVER['REQUEST_URI']);
        $urlHost = parse_url($url, PHP_URL_HOST);
        $urlPath = parse_url($url, PHP_URL_PATH);
        $filename = sprintf(
            '%1$s%2$s',
            sanitize_title($urlHost),
            $urlPath ? '-' . sanitize_title($urlPath) : ''
        );

        $qArgs = [
            'ical-plugin' => plugin()->getSlug(),
            'action' => 'export',
            'filename' => $filename
        ];

        $args['ids'] = $args['ids'] ?? false;   // array of post id(s)
        $args['cats'] = $args['cats'] ?? false; // array of term id(s)
        $args['tags'] = $args['tags'] ?? false; // array of term id(s)        
        foreach ($args as $k => $v) {
            if ($v && is_array($v)) {
                $qArgs = array_merge($qArgs, [$k => implode(',', $v)]);
            }
        }

        return add_query_arg($qArgs, site_url());
    }
}
