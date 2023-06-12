<?php

namespace RRZE\Calendar\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Calendar\Shortcodes\Calendar;
use RRZE\Calendar\Shortcodes\Events;

class Shortcode
{
    public static function init()
    {
        add_action('init', function () {
            Calendar::init();
            Events::init();
        });
    }

    public static function singleEventOutput(&$data)
    {
        $data = (object) $data;

        // Date & time format
        $dateFormat = get_option('date_format');
        $timeFormat = get_option('time_format');

        // Color
        $inline = !empty($data->cat_bgcolor) ? sprintf(' style="background-color:%1$s; color:%2$s"', $data->cat_bgcolor, $data->cat_color) : '';

        // Date/time
        $currentTime = current_time('timestamp');
        $isMultiday = !empty($data->is_multiday) ? true : false;
        $isAllday = !empty($data->is_allday) ? true : false;
        if ($isMultiday && $isAllday) {
            $startDate = strtotime($data->multiday['start_date']);
            $endDate = strtotime($data->multiday['end_date']);
        } elseif ($isMultiday && !$isAllday) {
            $startDate = strtotime($data->multiday['start_date'] . ' ' . $data->multiday['start_time']);
            $endDate = strtotime($data->multiday['end_date'] . ' ' . $data->multiday['end_time']);
        } elseif (!$isMultiday && !$isAllday) {
            $startDate = strtotime($data->start_date . ' ' . $data->start_time);
            $endDate = strtotime($data->start_date . ' ' . $data->end_time);
        } elseif (!$isMultiday && $isAllday) {
            $startDate = strtotime($data->start_date);
            $endDate = $startDate;
        }

        // Location/Organizer/Description
        $location = !empty($data->location) ? make_clickable($data->location) : '&nbsp;';
        $description = $data->description ?? __("No description", 'rrze-calendar');
        $description = make_clickable(nl2br(htmlspecialchars_decode($description)));
        $description = apply_filters('the_content', $description);
        $description = str_replace(']]>', ']]&gt;', $description);
?>
        <div class="rrze-calendar" itemscope itemtype="http://schema.org/Event">
            <h1 class="screen-reader-text" itemprop="name"><?php echo $data->title; ?></h1>
            <div class="event-detail-item">
                <div class="event-date" <?php echo $inline; ?>>
                    <span class="event-date-month"><?php echo date('M', $startDate < $currentTime ? $currentTime : $startDate); ?></span>
                    <span class="event-date-day"><?php echo date('d', $startDate < $currentTime ? $currentTime : $startDate); ?>
                </div>
                <div class="event-info event-id-<?php echo $data->post_id ?> <?php if ($isAllday) echo 'event-allday'; ?>">
                    <meta itemprop="startDate" content="<?php echo date('c', $startDate); ?>">
                    <meta itemprop="endDate" content="<?php echo date('c', $endDate); ?>">

                    <?php if ($isAllday) : ?>
                        <div class="event-time event-allday">
                            <?php _e('All Day', 'rrze-calendar'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($isMultiday && $isAllday) : ?>
                        <div class="event-time">
                            <?php printf(
                                /* translators: 1: Start date, 2: End date. */
                                __('%1$s - %2$s', 'rrze-calendar'),
                                date($dateFormat, $startDate),
                                date($dateFormat, $endDate)
                            ); ?>
                        </div>
                    <?php elseif ($isMultiday && !$isAllday) : ?>
                        <div class="event-time">
                            <?php printf(
                                /* translators: 1: Start date, 2: Start time, 3: End date, 4: End time. */
                                __('%1$s %2$s - %3$s %4$s', 'rrze-calendar'),
                                date($dateFormat, $startDate),
                                date($timeFormat, $startDate),
                                date($dateFormat, $endDate),
                                date($timeFormat, $endDate)
                            ); ?>
                        </div>
                    <?php elseif (!$isMultiday && !$isAllday) : ?>
                        <div class="event-time">
                            <?php printf(
                                /* translators: 1: Start date, 2: Start time, 3: End time. */
                                __('%1$s %2$s - %3$s', 'rrze-calendar'),
                                date($dateFormat, $startDate),
                                date($timeFormat, $startDate),
                                date($timeFormat, $endDate)
                            ); ?>
                        </div>
                    <?php elseif (!$isMultiday && $isAllday) : ?>
                        <div class="event-time">
                            <?php echo date($dateFormat, $startDate); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($data->readable_rrule)) : ?>
                        <div class="event-time event-rrule">
                            <?php echo ucfirst($data->readable_rrule); ?>
                        </div>
                    <?php endif; ?>
                    <div class="event-location" itemprop="location"><?php echo $location; ?></div>
                </div>
            </div>
            <div class="event-description">
                <?php echo $description; ?>
            </div>
            <?php if (!empty($data->calendar_view_url)) : ?>
                <div class="events-more-links">
                    <a class="events-more" href="<?php echo $data->calendar_view_url; ?>" target="_blank"><?php _e('View the calendar', 'rrze-calendar'); ?> <span class="dashicons dashicons-external"></span></a>
                </div>
            <?php endif; ?>
            <?php if (!empty($data->calendar_subscription_url)) : ?>
                <div class="events-more-links">
                    <a class="events-more" href="<?php echo $data->calendar_subscription_url; ?>" target="_blank"><?php _e('Subscribe to calendar', 'rrze-calendar'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    public static function singleEventListOutput($data, $endpointUrl)
    {
        $data = (object) $data;

        // Date & time format
        $dateFormat = get_option('date_format');
        $timeFormat = get_option('time_format');

        // Color
        $inline = !empty($data->cat_bgcolor) ? sprintf(' style="background-color:%1$s; color:%2$s"', $data->cat_bgcolor, $data->cat_color) : '';

        // Date/time
        $currentTime = current_time('timestamp');
        $isMultiday = !empty($data->is_multiday) ? true : false;
        $isAllday = !empty($data->is_allday) ? true : false;
        if ($isMultiday && $isAllday) {
            $startDate = strtotime($data->start_date);
            $endDate = strtotime($data->end_date);
        } elseif ($isMultiday && !$isAllday) {
            $startDate = strtotime($data->start_date);
            $endDate = strtotime($data->end_date);
        } elseif (!$isMultiday && !$isAllday) {
            $startDate = strtotime($data->start_date);
            $endDate = strtotime($data->end_date);
        } elseif (!$isMultiday && $isAllday) {
            $startDate = strtotime($data->start_date);
            $endDate = $startDate;
        }
    ?>
        <div class="rrze-calendar" itemscope itemtype="http://schema.org/Event">
            <h1 class="screen-reader-text" itemprop="name"><?php echo $data->title; ?></h1>
            <div class="event-detail-item">
                <div class="event-date" <?php echo $inline; ?>>
                    <span class="event-date-month"><?php echo date('M', $startDate < $currentTime ? $currentTime : $startDate); ?></span>
                    <span class="event-date-day"><?php echo date('d', $startDate < $currentTime ? $currentTime : $startDate); ?></span>
                </div>
                <div class="event-info event-id-<?php echo $data->slug ?> <?php if ($isAllday) echo 'event-allday'; ?>">
                    <meta itemprop="startDate" content="<?php echo date('c', $startDate); ?>">
                    <meta itemprop="endDate" content="<?php echo date('c', $endDate); ?>">
                    <div class="event-title" itemprop="name">
                        <a itemprop="url" href="<?php echo $endpointUrl . '/' . $data->slug; ?>"><?php echo $data->title; ?></a>
                    </div>
                    <?php if ($isAllday) : ?>
                        <div class="event-time event-allday">
                            <?php _e('All Day', 'rrze-calendar'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($isMultiday && $isAllday) : ?>
                        <div class="event-time">
                            <?php printf(__('%1$s - %2$s', 'rrze-calendar'), date($dateFormat, $startDate), date($dateFormat, $endDate)); ?>
                        </div>
                    <?php elseif ($isMultiday && !$isAllday) : ?>
                        <div class="event-time">
                            <?php printf(__('%1$s %2$s - %3$s %4$s', 'rrze-calendar'), date($dateFormat, $startDate), date($timeFormat, $startDate), date($dateFormat, $endDate), date($timeFormat, $endDate)); ?>
                        </div>
                    <?php elseif (!$isMultiday && !$isAllday) : ?>
                        <div class="event-time">
                            <?php printf(__('%1$s %2$s - %3$s', 'rrze-calendar'), date($dateFormat, $startDate), date($timeFormat, $startDate), date($timeFormat, $endDate)); ?>
                        </div>
                    <?php elseif (!$isMultiday && $isAllday) : ?>
                        <div class="event-time">
                            <?php echo date($dateFormat, $startDate); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($data->readable_rrule)) : ?>
                        <div class="event-time event-rrule">
                            <?php echo ucfirst($data->readable_rrule); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($data->location)) : ?>
                        <div class="event-location" itemprop="location">
                            <?php echo $data->location ? nl2br($data->location) : '&nbsp;'; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php
    }
}
