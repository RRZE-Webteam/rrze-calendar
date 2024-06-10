<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\CalendarEvent;
use RRule\RRule;
use function RRZE\Calendar\plugin;


class Export
{
    /**
     * @var string
     */
    private $vcalendar = '';

    /**
     * Export constructor.
     */
    public function __construct()
    {
        add_action('init', [$this, 'request']);
    }

    /**
     * Request
     */
    public function request()
    {
        $plugin = $_GET['ical-plugin'] ?? false;
        $action = $_GET['action'] ?? false;
        $filename = $_GET['filename'] ?? '';
        $urlHost = sanitize_title(parse_url(site_url(), PHP_URL_HOST));
        if (strpos($filename, $urlHost) === false) {
            $filename = $urlHost . '.ics';
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
     * Set
     * @param array $args
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
     * Get Events
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
            $icsMeta = !empty($meta['ics_event_meta'][0]) ? maybe_unserialize($meta['ics_event_meta'][0]) : null;
            $rrule = !empty($icsMeta['rrule']) ? maybe_unserialize($icsMeta['rrule']) : null;
            if (empty($rrule)) {
                $rrule = !empty($meta['event-rrule-args'][0]) ? json_decode($meta['event-rrule-args'][0], true) : null;
                if (!empty($rrule) && is_array($rrule)) {
                    $rruleObj = new RRule($rrule);
                    $rrule = $rruleObj->rfcString();
                    $rruleStartPos = strpos($rrule, 'RRULE:');
                    if ($rruleStartPos !== false) {
                        $rrule = substr($rrule, $rruleStartPos + strlen('RRULE:'));
                    }
                }
            }
            $args['rrule'] = !empty($rrule) ? $rrule : '';
            $data[$post->ID] = $args;
        }
        return $data;
    }

    /**
     * Build
     * @param array $data
     */
    private function build(array $data)
    {
        // Setup calendar
        $ics = new ICS($data);

        // Output .ics formatted text
        $this->vcalendar = $ics->build();
    }

    /**
     * Stream
     * @param string $filename
     */
    private function stream(string $filename)
    {
        $filename = $filename . '.ics';

        header('Content-Type: text/calendar; charset=' . get_option('blog_charset'), true);
        header('Content-Disposition: attachment; filename=' . $filename);
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
