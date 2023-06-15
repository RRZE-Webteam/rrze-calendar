<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

class Endpoint
{
    /**
     * init
     */
    public static function init()
    {
        $options = (object) get_option(Settings::OPTION_NAME);
        if ($options->endpoint_enabled == 'on') {
            add_action('init', [__CLASS__, 'addEndpoint']);
            add_action('template_redirect', [__CLASS__, 'endpointTemplateRedirect']);
        }
    }

    public static function addEndpoint()
    {
        $options = (object) get_option(Settings::OPTION_NAME);
        add_rewrite_endpoint($options->endpoint_slug, EP_ROOT);
    }

    public static function endpointTemplateRedirect()
    {
        global $wp_query;
        $options = (object) get_option(Settings::OPTION_NAME);
        if (!isset($wp_query->query_vars[$options->endpoint_slug])) {
            return;
        }

        $data = null;
        $slug = $wp_query->query_vars[$options->endpoint_slug];

        if (empty($slug)) {
            $data = Events::getAllItems();
        } else {
            $data = Events::getEventBySlug($slug);
        }

        if (empty($data)) {
            if ($template = locate_template('404.php')) {
                load_template($template);
                exit;
            } else {
                wp_die(__('Event not found.', 'rrze-calendar'));
            }
        }

        if (empty($slug)) {
            if ($template = locate_template(Templates::endpointEventsTpl())) {
                load_template($template);
            } else {
                include Templates::getEndpointEventsTpl();
            }
        } else {
            if ($template = locate_template(Templates::endpointSingleEventsTpl())) {
                load_template($template);
            } else {
                include Templates::getEndpointSingleEventsTpl();
            }
        }

        exit;
    }

    public static function isEndpoint()
    {
        global $wp_query;
        $options = (object) get_option(Settings::OPTION_NAME);
        return isset($wp_query->query_vars[$options->endpoint_slug]);
    }

    public static function endpointUrl($slug = '')
    {
        $options = (object) get_option(Settings::OPTION_NAME);
        return site_url($options->endpoint_slug . '/' . $slug);
    }

    public static function endpointSlug()
    {
        $options = (object) get_option(Settings::OPTION_NAME);
        return $options->endpoint_slug;
    }

    public static function endpointTitle()
    {
        $options = (object) get_option(Settings::OPTION_NAME);
        return $options->endpoint_title;
    }
}
