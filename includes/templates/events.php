<?php

global $rrze_calendar_data;

get_header(); ?>
    <header class="entry-header">
        <h1>Termine</h1>
    </header>

    <div class="entry-content">
        <?php if (empty($rrze_calendar_data)): ?>
        <p><?php _e('Keine bevorstehenden Termine.', 'rrze-calendar'); ?></p>
        <?php else: ?>
        <div>           
            <?php foreach ($rrze_calendar_data as $date): ?>
                <?php foreach ($date as $event): ?>                                         
                    <div class="event-detail-item">
                        <h2 class="event-title">
                            <a href="<?php echo $event->endpoint_url; ?>"><?php echo esc_html($event->summary); ?></a>
                        </h2>
                        <div class="event-date">
                            <?php echo $event->long_start_date ?>
                        </div>
                        <div class="event-info <?php if ($event->allday) echo 'event-allday'; ?>">
                            <?php if ($event->allday && !$event->multiday) : ?>
                                <div class="event-allday">
                                    <?php _e('Ganztägig', 'rrze-calendar'); ?>
                                </div>
                            <?php elseif ($event->allday && $event->multiday) : ?>
                                <div class="event-time">
                                    <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_start_date, $event->long_end_date)) ?>
                                </div>            
                            <?php elseif (!$event->allday && $event->multiday) : ?>
                                <div class="event-time">
                                    <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_start_time, $event->long_end_time)) ?>
                                </div>
                            <?php else: ?>
                                <div class="event-time">
                                    <?php echo esc_html(sprintf(__('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
                                </div>            
                            <?php endif; ?>
                            <p class="event-location">
                            <?php if ($event->location) : ?>
                                <?php printf('<strong>%1$s: </strong>%2$s', __('Ort', 'rrze-calendar'), $event->location); ?>
                            <?php endif; ?>
                            </p>                                    
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>                   
        </div>
        <?php endif; ?>
    </div>

<?php get_footer(); ?>
