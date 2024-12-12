<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\CalendarEvent;
use RRZE\Calendar\Settings\Settings as OptionsSettings;

class Settings
{
    const OPTION_NAME = 'rrze_calendar';

    protected $settings;

    public function __construct()
    {
        add_action('rrze_calendar_settings_after_update_option', [$this, 'flushRewriteRules']);
    }

    public function loaded()
    {
        $this->settings = new OptionsSettings(__('Calendar Settings', 'rrze-calendar'), 'rrze-calendar');

        $this->settings->setCapability('manage_options')
            ->setOptionName(self::OPTION_NAME)
            ->setMenuTitle(__('Calendar', 'rrze-calendar'))
            ->setMenuPosition(6)
            ->setMenuParentSlug('options-general.php');

        $sectionEvent = $this->settings->addSection(__('Events', 'rrze-calendar'));
        $sectionFeed = $this->settings->addSection(__('ICS Feed', 'rrze-calendar'));

        $sectionEvent->addOption('text', [
            'name' => 'endpoint_slug',
            'label' => __('Archive Slug', 'rrze-calendar'),
            'description' => __('Enter the archive slug that will display the event list.', 'rrze-calendar'),
            'default' => __('events', 'rrze-calendar'),
            'sanitize' => 'sanitize_title',
            'validate' => [
                [
                    'feedback' => __('The archive slug can have between 4 and 32 alphanumeric characters.', 'rrze-calendar'),
                    'callback' => [$this, 'validateEndpointSlug']
                ]
            ]
        ]);
        $sectionEvent->addOption('checkbox', [
            'name' => 'remove_duplicates',
            'label' => __('Remove Duplicates', 'rrze-calendar'),
            'description' => __('If the same event (i.e. same title, date and location) is present in multiple feeds, only show one of them.', 'rrze-calendar'),
        ]);

        $sectionFeed->addOption('select', [
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

    public function flushRewriteRules($optionName)
    {
        if ($optionName === self::OPTION_NAME) {
            // Register the 'CalendarEvent' CPT again and flush rewrite rules.
            CalendarEvent::registerPostType();
            flush_rewrite_rules();
        }
    }

    public function validateEndpointSlug($value)
    {
        if (mb_strlen(sanitize_title($value)) < 4) {
            return false;
        }
        return true;
    }

    public function getOption($option)
    {
        return $this->settings->getOption($option);
    }

    public function getOptions()
    {
        return $this->settings->getOptions();
    }
}
