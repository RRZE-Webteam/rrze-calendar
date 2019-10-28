<?php

namespace FAU\Calendar;

defined('ABSPATH') || exit;

use FAU\Calendar\Options;
use FAU\Calendar\Events;
use FAU\Calendar\Themes;

class Endpoint
{
    /**
     * [protected description]
     * @var object
     */
    protected $options;

    /**
     * [__construct description]
     */
    public function __construct()
    {
        $this->options = Options::getOptions();
        add_action('init', [$this, 'addEndpoint']);
        add_action('template_redirect', [$this, 'endpointTemplateRedirect']);
    }

    public function addEndpoint() {
        add_rewrite_endpoint($this->options->endpoint_slug, EP_ROOT);
    }

    public function endpointTemplateRedirect() {
        global $wp_query;

        if (!isset($wp_query->query_vars[$this->options->endpoint_slug])) {
            return;
        }

        $slug = $wp_query->query_vars[$this->options['endpoint_slug']];

        if (empty($slug)) {
            $currentLocalTimestamp = current_time('timestamp');
            $eventsResult = Events::getEventsRelativeTo($currentLocalTimestamp);
            $eventsData = Util::getCalendarDates($eventsResult);
        } else {
            $eventsData = Events::getEventBySlug($slug);
        }

        if (empty($eventsData)) {
            if ($template = locate_template('404.php')) {
                load_template($template);
            } else {
                wp_die(__('Event not found.', 'rrze-calendar'));
            }
        }

        $styleDir = $this->getStyleDir();

        if (empty($slug)) {
            include $styleDir . 'events.php';
        } else {
            include $styleDir . 'single-event.php';
        }

        exit();
    }

    public function isEndpoint() {
        global $wp_query;

        return isset($wp_query->query_vars[$this->options->endpoint_slug]);
    }

    public function endpointUrl($slug = '') {
        return site_url($this->options->endpoint_slug . '/' . $slug);
    }

    public function endpointSlug() {
        return $this->options->endpoint_slug;
    }

    protected function getStyleDir() {
        return Themes::getStyleDir();
    }
}
