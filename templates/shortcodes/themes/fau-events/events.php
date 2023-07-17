<?php

/* Quit */
defined('ABSPATH') || exit;

$count = 1;
wp_enqueue_style('rrze-calendar-shortcode-events');
?>

<ul class="rrze-calendar events-list">
    <?php if (empty($events_data)) : ?>
        <p><?php _e('No upcoming events.', 'rrze-calendar'); ?></p>
    <?php else : ?>
        <?php foreach ($events_data as $event_date) : ?>
            <?php foreach ($event_date as $event) :
                if ($count > $limit) break;
                if (!empty($start_date) && strtotime($event->start) < $start_date) {
                    continue;
                }
                if (!empty($end_date) && strtotime($event->end) > $end_date) {
                    continue;
                }
                $inline = '';
                if (isset($event->category) && !empty($event->category->color)) :
                    $inline = 'style="border-left: 5px solid ' . $event->category->color . '; padding-left: 10px;"';
                endif;
            ?>
                <li class="event-item" <?php echo $inline; ?> itemscope itemtype="http://schema.org/Event">
                    <div>
                        <meta itemprop="startDate" content="<?php echo date_i18n('c', strtotime($event->start)); ?>">
                        <meta itemprop="endDate" content="<?php echo date_i18n('c', strtotime($event->end)); ?>">
                    </div>
                    <div class="event-title" itemprop="name">
                        <a itemprop="url" href="<?php echo esc_attr(RRZE_Calendar::endpoint_url($event->slug)); ?>"><?php echo preg_replace('/[^[:alpha:]\s]/', '', $event->label); ?></a>
                    </div>
                    <div class="event-title-date">
                        <?php echo $event->long_start_date ?>
                    </div>
                    <div class="event-info">
                        <?php if ($event->allday) : ?>
                            <div class="event-date event-allday">
                                <?php _e('All Day', 'rrze-calendar'); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($event->allday && $event->multiday) : ?>
                            <div class="event-date">
                                <?php echo esc_html(
                                    sprintf(
                                        /* translators: 1: Start date, 2: End date. */
                                        __('%1$s - %2$s', 'rrze-calendar'),
                                        $event->long_start_date,
                                        $event->long_end_date
                                    )
                                ) ?>
                            </div>
                        <?php elseif (!$event->allday && $event->multiday) : ?>
                            <div class="event-date">
                                <?php echo esc_html(
                                    sprintf(
                                        /* translators: 1: Start date, 2: Start time, 3: End date, 4: End time. */
                                        __('%1$s %2$s - %3$s %4$s', 'rrze-calendar'),
                                        $event->long_start_date,
                                        $event->short_start_time,
                                        $event->long_end_date,
                                        $event->short_end_time
                                    )
                                ) ?>
                            </div>
                        <?php elseif (!$event->allday) : ?>
                            <div class="event-date">
                                <?php echo esc_html(
                                    sprintf(
                                        /* translators: 1: Start time, 2: End time. */
                                        __('%1$s - %2$s', 'rrze-calendar'),
                                        $event->short_start_time,
                                        $event->short_end_time
                                    )
                                ) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($location && $event->location) : ?>
                            <div class="event-location" itemprop="location">
                                <?php echo esc_html($event->location); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($description && $event->description) : ?>
                            <div class="event-summary">
                                <?php echo make_clickable(wp_trim_words(nl2br($event->description))); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </li>
                <?php $count++; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if ($calendar_page_url) : ?>
            <div class="events-more-links">
                <a class="events-more" href="<?php echo $calendar_page_url; ?>"><?php _e('More events', 'rrze-calendar'); ?></a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($subscribe_url) : ?>
        <p class="events-more-links">
            <a class="events-more" href="<?php echo $calendar_subscribe_url; ?>"><?php _e('Subscription', 'rrze-calendar'); ?></a>
        </p>
    <?php endif; ?>
</ul>