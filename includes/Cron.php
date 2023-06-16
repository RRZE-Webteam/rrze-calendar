<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\ICS\Events;

class Cron
{
    const ACTION_HOOK = 'rrze_calendar_schedule_event';

    public static function init()
    {
        add_action(self::ACTION_HOOK, [__CLASS__, 'runEvents']);
        add_action('init', [__CLASS__, 'activateScheduledEvents']);
    }

    /**
     * activateScheduledEvents
     * Activate all scheduled events.
     */
    public static function activateScheduledEvents()
    {
        $options = (object) get_option(Settings::OPTION_NAME);
        if (false === wp_next_scheduled(self::ACTION_HOOK)) {
            wp_schedule_event(
                time(),
                $options->schedule_recurrence,
                self::ACTION_HOOK,
                [],
                true
            );
        }
    }

    /**
     * runEvents
     * Run the scheduled events.
     */
    public static function runEvents()
    {
        Events::updateFeedsItems();
    }

    /**
     * clearSchedule
     * Clear all scheduled events.
     */
    public static function clearSchedule()
    {
        wp_clear_scheduled_hook(self::ACTION_HOOK);
    }
}
