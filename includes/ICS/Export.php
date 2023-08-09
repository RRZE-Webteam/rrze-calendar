<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\CalendarEvent;
use function RRZE\Calendar\plugin;


class Export
{
    private $vcalendar = '';

    public function __construct()
    {
        add_action('init', [$this, 'request']);
    }

    public function request()
    {
        $plugin = $_GET['ical-plugin'] ?? false;
        $action = $_GET['action'] ?? false;

        if (
            $plugin === sanitize_title(plugin()->getSlug())
            && $action === 'export'
        ) {
            $args = [
                'postIds' => $_GET['ids'] ?: false,
                'categories' => $_GET['cats'] ?: false,
                'tags' => $_GET['tags'] ?: false
            ];
            $this->set($args);
            $this->stream();
        }
    }

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
                    'field'    => 'slug',
                    'terms'    => $categories
                ]
            ];
        }

        if (is_array($categories) && !empty($tags)) {
            $taxQuery = array_merge(
                $taxQuery,
                [
                    [
                        'taxonomy' => CalendarEvent::TAX_TAG,
                        'field'    => 'slug',
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
            $args = array_merge($args, ['post__in' => $postIds]);
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

    private function getEvents(array $posts)
    {
        $data = [];
        foreach ($posts as $post) {
            $meta = get_post_meta($post->ID, '', true);
            $data[$post->ID] = [
                'summary' => $post->post_title,
                'uid' => $meta['event-uid'],
                'description' => $meta['description'],
                'dstart' => $meta['start'],
                'dtend' => $meta['end']
            ];
        }
        return $data;
    }

    private function build(array $data)
    {
        // Setup calendar
        $ics = new ICS($data);

        // Output .ics formatted text
        $this->vcalendar = $ics->build();
    }

    private function stream()
    {
        $urlHost = parse_url(site_url(), PHP_URL_HOST);
        $urlPath = parse_url(site_url(), PHP_URL_PATH);
        $filename = sprintf(
            '%1$s%2$s.ics',
            $urlHost,
            $urlPath ? '-' . $urlPath : ''
        );

        header('Content-Type: text/calendar; charset=' . get_option('blog_charset'), true);
        header('Content-Disposition: attachment; filename=' . $filename);
        echo $this->vcalendar;
        exit;
    }

    public static function makeIcsLink(array $args)
    {
        $args['ids'] = $args['ids'] ?? false;
        $args['cats'] = $args['cats'] ?? false; // array of term id(s)
        $args['tags'] = $args['tags'] ?? false; // array of term id(s)
        $qArgs = [
            'ical-plugin' => plugin()->getSlug(),
            'action' => 'export'
            ];

        foreach ($args as $k => $v) {
            if ($v && is_array($v)) {
                $qArgs = array_merge($qArgs, [$k => implode(',', $v)]);
            }
        }

        return add_query_arg($qArgs, site_url());
    }
}
