<?php

/* Quit */
defined('ABSPATH') || exit;

$event = &$events_data;

get_header();
wp_enqueue_style('rrze-calendar');
?>
<div id="primary" class="content-area">
    <div id="content" class="site-content" role="main">
        <div class="event-detail-item">
            <h2>
                <?php echo $event->summary; ?>
            </h2>
            <div class="event-info">
                <?php if ($event->allday) : ?>
                    <div class="event-date event-allday">
                        <?php _e('Ganztägig', 'rrze-calendar'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($event->allday && !$event->multiday && !$event->recurrence_rules) : ?>
                    <div class="event-date">
                        <?php echo esc_html($event->long_e_start_date) ?>
                    </div>
                <?php endif; ?>                
                <?php if ($event->allday && $event->multiday) : ?>
                    <div class="event-date">
                        <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_e_start_date, $event->long_e_end_date)) ?>
                    </div>            
                <?php elseif (!$event->allday && $event->multiday) : ?>
                    <div class="event-date">
                        <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $event->long_e_start_date, $event->short_e_start_time, $event->long_e_end_date, $event->short_e_end_time)) ?>
                    </div>
                <?php elseif (!$event->allday &&  $event->recurrence_rules): ?>
                    <div class="event-date">
                        <?php echo esc_html(sprintf(__('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
                    </div>                
                <?php elseif (!$event->allday &&  !$event->recurrence_rules): ?>
                    <div class="event-date">
                        <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s Uhr', 'rrze-calendar'), $event->long_e_start_date, $event->short_start_time, $event->short_end_time)) ?>
                    </div>            
                <?php endif; ?>
                <?php if ($event->recurrence_rules) : ?>
                    <div class="event-rrules">
                        <?php echo ucfirst($event->rrules_human_readable); ?>
                    </div>                
                <?php endif; ?>
                <?php if ($event->location) : ?>
                    <div class="event-location">
                        <?php echo esc_html($event->location); ?>
                    </div>
                <?php endif; ?>
                <?php if ($event->description) : ?>
                    <div class="event-summary">
                        <?php echo make_clickable(nl2br($event->description)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="events-more-links">
            <a class="events-more" href="<?php echo $event->subscribe_url; ?>"><?php _e('Abonnement', 'rrze-calendar'); ?></a>
        </div>            
    </div>
</div>

<?php get_footer(); ?>
