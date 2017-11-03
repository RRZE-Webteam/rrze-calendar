<?php

/* Quit */
defined('ABSPATH') || exit;

$multiday = [];
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
                <div class="event-item" itemscope itemtype="http://schema.org/Event">
		    <meta itemprop="startDate" content="<?php echo date_i18n( "c", $event->start ); ?>">
		    <meta itemprop="endDate" content="<?php echo date_i18n( "c", $event->end ); ?>">
                    <div class="event-date">
                        <div class="day-month">
                            <div class="day"><?php echo $event->start_day . '. '; ?></div>
                            <div class="month"><?php echo $event->start_month; ?></div>
                        </div>
                        <div class="year"><?php echo $event->start_year; ?></div>
                    </div>
                    <h2 class="event-title" itemprop="name">
                        <a itemprop="url" href="<?php echo $event->endpoint_url; ?>"><?php echo esc_html($event->summary); ?></a>
                    </h2>
                    <div class="event-info">
                        <?php if ($event->allday) : ?>
                            <div class="event-time event-allday">
                                <?php _e('Ganztägig', 'rrze-calendar'); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($event->allday && $event->multiday) : ?>
                            <?php $multiday[] = $event->id; ?>
                            <div class="event-time">
                                <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_e_start_date, $event->long_e_end_date)) ?>
                            </div>            
                        <?php elseif (!$event->allday && $event->multiday) : ?>
                            <?php $multiday[] = $event->id; ?>
                            <div class="event-time">
                                <?php echo esc_html(sprintf( __('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $event->long_e_start_date, $event->short_e_start_time, $event->long_e_end_date, $event->short_e_end_time)) ?>
                            </div>
                        <?php elseif (!$event->allday): ?>
                            <div class="event-time">
                                <?php echo esc_html(sprintf( __('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
                            </div>            
                        <?php endif; ?>
                        <p class="event-location" itemprop="location">
                        <?php if ($event->location) : ?>
                            <?php printf('<strong>%1$s: </strong>%2$s', __('Ort', 'rrze-calendar'), $event->location); ?>
                        <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php $anzahl--; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if ($calendar_page_url): ?>
        <p class="events-more-links">
            <a class="events-more" href="<?php echo $calendar_page_url; ?>"><?php _e('Mehr Veranstaltungen', 'rrze-calendar'); ?></a>
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if($subscribe_url): ?>
    <p class="events-more-links">
        <a class="events-more" href="<?php echo $calendar_subscribe_url; ?>"><?php _e('Abonnement', 'rrze-calendar'); ?></a>
    </p>
    <?php endif; ?>
</div>