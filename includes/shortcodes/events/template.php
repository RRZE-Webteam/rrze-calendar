<?php

/* Quit */
defined('ABSPATH') || exit;

$multiday = [];

wp_enqueue_style('rrze-calendar-shortcode-events');
?>

<div class="events-list">
    <?php if (empty($events_data)): ?>
    <p><?php _e('Keine bevorstehenden Termine.', 'rrze-calendar'); ?></p>
    <?php else: ?>
    <div>
        <?php foreach ($events_data as $date): ?>
            <?php foreach ($date as $event):
                if ($anzahl <= 0):
                    break;
                endif;
                if (in_array($event->id, $multiday)):
                    continue;
                endif; ?>
                <div class="event-item">
                    <div class="event-title">
                        <a href="<?php echo $event->endpoint_url; ?>"><?php echo esc_html($event->summary); ?></a>
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
                            <?php $multiday[] = $event->id; ?>
                            <div class="event-date">
                                <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_e_start_date, $event->long_e_end_date)) ?>
                            </div>            
                        <?php elseif (!$event->allday && $event->multiday) : ?>
                            <?php $multiday[] = $event->id; ?>
                            <div class="event-date">
                                <?php echo esc_html(sprintf( __('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $event->long_e_start_date, $event->short_e_start_time, $event->long_e_end_date, $event->short_e_end_time)) ?>
                            </div>
                        <?php elseif (!$event->allday): ?>
                            <div class="event-date">
                                <?php echo esc_html(sprintf( __('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
                            </div>            
                        <?php endif; ?>
                        <?php if ($location && $event->location) : ?>
                            <div class="event-location">
                                <?php echo esc_html($event->location); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($description && $event->description) : ?>
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
