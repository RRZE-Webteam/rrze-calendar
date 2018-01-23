<?php
/* Quit */
defined('ABSPATH') || exit;

$multiday = [];

get_header();
wp_enqueue_style('rrze-calendar');
?>

<div class="content-wrap">
    <div id="blog-wrap" class="blog-wrap cf">
        <div id="primary" class="site-content cf" role="main">

            <header class="entry-header">
                <h2>sdfgsdfgsdgfsdfgsdgf<?php _e('Termine', 'rrze-calendar'); ?></h2>
            </header>

            <div class="entry-content">
                <?php if (empty($events_data)): ?>
                    <p><?php _e('Keine bevorstehenden Termine.', 'rrze-calendar'); ?></p>
                <?php else: ?>
                    <?php foreach ($events_data as $date): ?>
                        <?php foreach ($date as $event): ?>
                            <?php if (in_array($event->endpoint_url, $multiday)): ?>
                                <?php continue; ?>
                            <?php endif; ?>
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
                                        <?php $multiday[] = $event->endpoint_url; ?>
                                        <div class="event-date">
                                            <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_e_start_date, $event->long_e_end_date)) ?>
                                        </div>
                                    <?php elseif (!$event->allday && $event->multiday) : ?>
                                        <?php $multiday[] = $event->endpoint_url; ?>
                                        <div class="event-date">
                                            <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $event->long_e_start_date, $event->short_e_start_time, $event->long_e_end_date, $event->short_e_end_time)) ?>
                                        </div>
                                    <?php elseif (!$event->allday): ?>
                                        <div class="event-date">
                                            <?php echo esc_html(sprintf(__('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($event->location) : ?>
                                        <div class="event-location">
                                            <?php echo esc_html($event->location); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($event->description) : ?>
                                        <div class="event-summary">
                                            <?php echo make_clickable(wp_trim_words(nl2br($event->description))); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div><!-- end #primary -->

        <?php get_sidebar(); ?>

    </div><!-- end .blog-wrap -->
</div><!-- end .content-wrap -->

<?php get_footer(); ?>
