<?php
/* Quit */
defined('ABSPATH') || exit;

$event = &$events_data;

get_header();
wp_enqueue_style('rrze-calendar');
?>

<div id="singlepost-wrap" class="singlepost-wrap cf">
    <div id="content" class="rrze-calendar hentry" role="main" itemscope itemtype="http://schema.org/Event">
        <div class="event-detail-item">
            <h1 class="entry-title" itemprop="name">
                <?php echo $event->label; ?>
            </h1>
            <div class="event-info entry-content">
                <meta itemprop="startDate" content="<?php echo date_i18n('c', strtotime($event->start)); ?>">
                <meta itemprop="endDate" content="<?php echo date_i18n('c', strtotime($event->end)); ?>">
                <?php if ($event->allday) : ?>
                    <div class="event-date event-allday">
                        <?php _e('All Day', 'rrze-calendar'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($event->allday && !$event->multiday && !$event->recurrence_rules) : ?>
                    <div class="event-date">
                        <?php echo esc_html($event->long_start_date) ?>
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
                <?php elseif (!$event->allday && $event->recurrence_rules) : ?>
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
                <?php elseif (!$event->allday && !$event->recurrence_rules) : ?>
                    <div class="event-date">
                        <?php echo esc_html(
                            sprintf(
                                /* translators: 1: Start date, 2: Start time. 3: End time. */
                                __('%1$s %2$s - %3$s', 'rrze-calendar'),
                                $event->long_start_date,
                                $event->short_start_time,
                                $event->short_end_time
                            )
                        ) ?>
                    </div>
                <?php endif; ?>
                <?php if ($event->recurrence_rules) : ?>
                    <div class="event-rrule">
                        <?php echo ucfirst($event->rrules_human_readable); ?>
                    </div>
                <?php endif; ?>
                <?php if ($event->location) : ?>
                    <div class="event-location" itemprop="location">
                        <?php echo esc_html($event->location); ?>
                    </div>
                <?php endif; ?>
                <?php if ($event->description) : ?>
                    <div class="event-summary">
                        <?php
                        $content = make_clickable(nl2br(htmlspecialchars_decode($event->description)));
                        $content = apply_filters('the_content', $content);
                        $content = str_replace(']]>', ']]&gt;', $content);
                        echo $content;
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="events-more-links">
            <a class="events-more standard-btn small-btn grey-btn" href="<?php echo $event->subscribe_url; ?>"><?php _e('Subscription', 'rrze-calendar'); ?></a>
        </div>
    </div> <!-- end .hentry -->
    <?php get_sidebar(); ?>
</div> <!-- end #singlepost-wrap -->

<?php get_footer(); ?>