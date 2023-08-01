<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\WP\Settings\Settings as RRZEWPSettings;

class Settings
{
    const OPTION_NAME = 'rrze_calendar';

    protected $settings;

    public function __construct()
    {
        $this->settings = new RRZEWPSettings(__('Calendar Settings', 'rrze-calendar'), 'rrze-calendar');

        $this->settings->setCapability('manage_options')
            ->setOptionName(self::OPTION_NAME)
            ->setMenuTitle(__('Calendar', 'rrze-calendar'))
            ->setMenuPosition(6)
            ->setMenuParentSlug('options-general.php');

        $this->settings->addSection(__('ICS Feed', 'rrze-calendar'));

        $this->settings->addOption('text', [
            'name' => 'endpoint_slug',
            'label' => __('Archive Slug', 'rrze-calendar'),
            'description' => __('Enter the archive slug that will display the event list.', 'rrze-calendar'),
            'default' => 'events',
            'sanitize' => 'sanitize_title',
            'validate' => [
                [
                    'feedback' => __('The archive slug can have between 4 and 32 alphanumeric characters.', 'rrze-calendar'),
                    'callback' => [$this, 'validateEndpointSlug']
                ]
            ]
        ]);

        $this->settings->addOption('select', [
            'name' => 'schedule_recurrence',
            'label' => __('Schedule', 'rrze-calendar'),
            'description' => __('Choose the recurrence to check for new events.', 'rrze-calendar'),
            'options' => [
                'hourly'     => __('Hourly', 'rrze-calendar'),
                'twicedaily' => __('Twice daily', 'rrze-calendar'),
                'daily'      => __('Daily', 'rrze-calendar')
            ],
            'default' => 'daily'
        ]);

        $this->settings->build();
    }

    public function validateEndpointSlug($value)
    {
        if ($validation = strlen(sanitize_title($value)) > 4) {
            flush_rewrite_rules();
        }
        return $validation;
    }

    public function getOption($option)
    {
        return $this->settings->getOption($option);
    }

    public function getOptions()
    {
        return $this->settings->getOptions();
    }

    /**
     * __call method
     * Method overloading.
     */
    public function __call(string $name, array $arguments)
    {
        if (!method_exists($this, $name)) {
            $message = sprintf('Call to undefined method %1$s::%2$s', __CLASS__, $name);
            do_action(
                'rrze.log.error',
                $message,
                [
                    'class' => __CLASS__,
                    'method' => $name,
                    'arguments' => $arguments
                ]
            );
            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw new \Exception($message);
            }
        }
    }
}
