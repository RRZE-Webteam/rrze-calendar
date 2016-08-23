<?php

global $rrze_calendar_data;

if (empty($rrze_calendar_data)) {
    wp_die();
}
$event = &$rrze_calendar_data;

get_header(); ?>

    <div id="primary" class="content-area">
            <div id="content" class="site-content" role="main">
                <div class="event-detail-item">
                    <h2>
                        <?php echo $event->summary; ?>
                    </h2>
                    <div class="event-date">
                        <?php echo $event->long_start_date ?>
                    </div>
                    <div class="event-info event-id-<?php echo $event->id ?> <?php if ($event->allday) echo 'event-allday'; ?>">
                        <?php if ($event->allday && !$event->multiday) : ?>
                        <div class="event-allday">
                            <?php _e('Ganztägig', 'rrze-calendar'); ?>
                        </div>
                        <?php elseif ($event->allday && $event->multiday) : ?>
                        <div class="event-time">
                            <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_start_date, $event->long_end_date)) ?>
                        </div>            
                        <?php elseif(!$event->allday && $event->multiday) : ?>
                        <div class="event-time">
                            <?php echo esc_html( sprintf( __('%1$s bis %2$s', 'rrze-calendar'), $event->long_start_time, $event->long_end_time)) ?>
                        </div>
                        <?php else: ?>
                        <div class="event-time">
                            <?php echo esc_html(sprintf( __('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
                        </div>            
                        <?php endif; ?>
                        <p class="event-location">
                        <?php if ($event->location) : ?>
                            <?php printf('<strong>%1$s: </strong>%2$s', __('Ort', 'rrze-calendar'), $event->location); ?>
                        <?php endif; ?>
                        <p>
                    </div>
                </div>                
                <p>
                    <?php echo make_clickable(nl2br($event->description)); ?>
                </p>
                <p class="events-more-links">
                    <a class="events-more" href="<?php echo $event->subscribe_url; ?>"><?php _e('Abonnement', 'rrze-calendar'); ?></a>
                </p>                
            </div>
    </div>

<?php get_footer(); ?>
