<?php

/* Quit */
defined('ABSPATH') || exit;

wp_enqueue_style('rrze-calendar-shortcode-events');
?>

<div class="rrze-calendar events-list">
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
                <div class="event-item">
                    <div class="event-title">
                        <a href="<?php echo esc_attr(RRZE_Calendar::endpoint_url($event->slug)); ?>"><?php echo esc_html($event->summary); ?></a>
                    </div>
                    <div class="event-title-date">
                        <?php echo $event->long_start_date ?>
                    </div>
                    <div class="event-info">
                        <?php if ($event->allday) : ?>
                            <div class="event-date event-allday">
                                <?php _e('Ganztägig', 'rrze-calendar'); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($event->allday && $event->multiday) : ?>
                            <div class="event-date">
                                <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_start_date, $event->long_end_date)) ?>
                            </div>
                        <?php elseif (!$event->allday && $event->multiday) : ?>
                            <div class="event-date">
                                <?php echo esc_html(sprintf( __('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $event->long_start_date, $event->short_start_time, $event->long_end_date, $event->short_end_time)) ?>
                            </div>
                        <?php elseif (!$event->allday): ?>
                            <div class="event-date">
                                <?php echo esc_html(sprintf( __('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($location && !empty($event->location)) : ?>
                            <div class="event-location">
                                <?php echo esc_html($event->location); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($description && !empty($event->description)) : ?>
                            <div class="event-summary">
                                <?php echo make_clickable(wp_trim_words(nl2br($event->description))); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php $anzahl--; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if ($calendar_page_url): ?>
        <div class="events-more-links">
            <a class="events-more" href="<?php echo $calendar_page_url; ?>"><?php _e('Mehr Veranstaltungen', 'rrze-calendar'); ?></a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if($subscribe_url): ?>
    <p class="events-more-links">
        <a class="events-more" href="<?php echo $calendar_subscribe_url; ?>"><?php _e('Abonnement', 'rrze-calendar'); ?></a>
    </p>
    <?php endif; ?>
</div>
