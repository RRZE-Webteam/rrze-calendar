<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\WP\Settings\Settings as RRZEWPSettings;

class Settings
{
    const OPTION_NAME = 'rrze_calendar';

    public static function init()
    {
        $settings = new RRZEWPSettings(__('Calendar Settings', 'rrze-settings'), 'rrze-calendar');

        $settings->setCapability('manage_options')
            ->setOptionName(self::OPTION_NAME)
            ->setMenuTitle(__('Calendar', 'rrze-settings'))
            ->setMenuPosition(6)
            ->setMenuParentSlug('options-general.php');

        $settings->addTab(__('ICS Feed', 'rrze-calendar'));

        $settings->addSection(__('Schedule', 'rrze-calendar'));

        $settings->addOption('select', [
            'name' => 'schedule_recurrence',
            'label' => __('Recurrence', 'rrze-calendar'),
            'description' => __('Choose the recurrence to check for new events.', 'rrze-calendar'),
            'options' => [
                'hourly'     => __('Hourly', 'rrze-calendar'),
                'twicedaily' => __('Twice daily', 'rrze-calendar'),
                'daily'      => __('Daily', 'rrze-calendar')
            ]
        ]);

        $settings->build();
    }
}
