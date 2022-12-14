<?php

/* Quit */
defined('ABSPATH') || exit;
?>
<div class="events-list">
    <?php if (empty($events_data)): ?>
    <p><?php _e('Keine bevorstehenden Termine.', 'rrze-calendar'); ?></p>
    <?php else: ?>
    <div>
        <?php foreach ($events_data as $event_date): ?>
            <?php foreach ($event_date as $event):
                if ($anzahl <= 0):
                    break;
                endif;
                if (!empty($start_date) && strtotime($event->start) < $start_date) {
                    continue;
                }
                if (!empty($end_date) && strtotime($event->end) > $end_date) {
                    continue;
                }
                ?>
                <div class="event-item" itemscope itemtype="http://schema.org/Event">
		            <div class="event-date">
                        <div class="day-month">
                            <div class="day"><?php echo $event->start_day . '. '; ?></div>
                            <div class="month"><?php echo $event->start_month; ?></div>
                        </div>
                        <div class="year"><?php echo $event->start_year; ?></div>
                    </div>
                    <div class="event-title" itemprop="name">
                        <a itemprop="url" href="<?php echo esc_attr(RRZE_Calendar::endpoint_url($event->slug)); ?>" title="<?php echo esc_html($event->summary . ', ' . $event->long_start_date); ?>"><?php echo esc_html($event->summary); ?></a>
                    </div>
                    <div class="event-info">
                        <?php if ($event->allday) : ?>
                            <div class="event-time event-allday">
                                <?php _e('Ganztägig', 'rrze-calendar'); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($event->allday && $event->multiday) : ?>
                            <div class="event-time">
                                <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_start_date, $event->long_end_date)) ?>
                            </div>
                        <?php elseif (!$event->allday && $event->multiday) : ?>
                            <div class="event-time">
                                <?php echo esc_html(sprintf( __('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $event->long_start_date, $event->short_start_time, $event->long_end_date, $event->short_end_time)) ?>
                            </div>
                        <?php elseif (!$event->allday): ?>
                            <div class="event-time">
                                <?php echo esc_html(sprintf( __('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($location && !empty($event->location)) : ?>
                            <p class="event-location" itemprop="location">
                                <?php printf('<strong>%1$s: </strong>%2$s', __('Ort', 'rrze-calendar'), $event->location); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <p style="margin:0;padding:0;"><meta itemprop="startDate" content="<?php echo date_i18n('c', strtotime($event->start)); ?>"><meta itemprop="endDate" content="<?php echo date_i18n('c', strtotime($event->end)); ?>"></p></div>
                <?php $anzahl--; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if ($calendar_page_url != ''):
            if (shortcode_exists('button')) :
                echo do_shortcode('[button link="' . $calendar_page_url . '" size="small"]' . __('Mehr Veranstaltungen', 'rrze-calendar') . '[/button]');
            else: ?>
                <p class="events-more-links">
                    <a class="events-more" href="<?php echo $calendar_page_url; ?>"><?php _e('Mehr Veranstaltungen', 'rrze-calendar'); ?></a>
                </p>
            <?php endif;
        endif; ?>
    </div>
    <?php endif; ?>
    <?php
    if($subscribe_url != ''): ?>
    <p class="events-more-links">
        <a class="events-more" href="<?php echo $calendar_subscribe_url; ?>"><i class="fas fa-rss" aria-hidden="true"></i> <?php _e('Abonnement', 'rrze-calendar'); ?></a>
    </p>
    <?php endif; ?>
</div>
