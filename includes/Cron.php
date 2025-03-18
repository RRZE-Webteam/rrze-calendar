<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\ICS\Events;

/**
 * Cron class
 * @package RRZE\Calendar
 */
class Cron
{
    /**
     * The action hook name.
     */
    const ACTION_HOOK = 'rrze_calendar_schedule_event';

    /**
     * Initialize the class, registering WordPress hooks
     * @return void
     */
    public static function init()
    {
        add_action(self::ACTION_HOOK, [__CLASS__, 'runEvents']);
        add_action('init', [__CLASS__, 'activateScheduledEvents']);
    }

    /**
     * Activate all scheduled events.
     * @return void
     */
    public static function activateScheduledEvents()
    {
        $scheduleRecurrence = settings()->getOption('schedule_recurrence');
        if (!wp_next_scheduled(self::ACTION_HOOK) && $scheduleRecurrence) {
            wp_schedule_event(
                time(),
                $scheduleRecurrence,
                self::ACTION_HOOK
            );
        }
    }

    /**
     * Run the scheduled events.
     * @return void
     */
    public static function runEvents()
    {
        Events::updateFeedsItems();
    }

    /**
     * Clear all scheduled events.
     * @return void
     */
    public static function clearSchedule()
    {
        wp_clear_scheduled_hook(self::ACTION_HOOK);
    }
}
