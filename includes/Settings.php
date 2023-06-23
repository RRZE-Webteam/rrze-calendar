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
        $this->settings = new RRZEWPSettings(__('Calendar Settings', 'rrze-settings'), 'rrze-calendar');

        $this->settings->setCapability('manage_options')
            ->setOptionName(self::OPTION_NAME)
            ->setMenuTitle(__('Calendar', 'rrze-settings'))
            ->setMenuPosition(6)
            ->setMenuParentSlug('options-general.php');

        $this->settings->addSection(__('ICS Feed', 'rrze-calendar'));

        $this->settings->addOption('select', [
            'name' => 'schedule_recurrence',
            'label' => __('Schedule', 'rrze-calendar'),
            'description' => __('Choose the recurrence to check for new events.', 'rrze-calendar'),
            'options' => [
                'hourly'     => __('Hourly', 'rrze-calendar'),
                'twicedaily' => __('Twice daily', 'rrze-calendar'),
                'daily'      => __('Daily', 'rrze-calendar')
            ]
        ]);

        $this->settings->build();
    }

    public function getOption($option)
    {
        $this->settings->getOption($option);
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
