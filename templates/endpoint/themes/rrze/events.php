<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

$multiday = [];

get_header(); ?>

<?php if (!is_front_page()) { ?>
    <div id="sidebar" class="sidebar">
        <?php get_sidebar('page'); ?>
    </div><!-- .sidebar -->
<?php } ?>
<div id="primary" class="content-area">
    <div id="content" class="site-content" role="main">
        <div class="rrze-calendar events-list">
            <?php if (empty($events_data)) : ?>
                <p><?php _e('No upcoming events.', 'rrze-calendar'); ?></p>
            <?php else : ?>
                <?php foreach ($events_data as $date) : ?>
                    <?php foreach ($date as $event) :
                        if (!empty($event->tags)) :
                            $_nolist = false;
                            foreach ($event->tags as $tag) :
                                if ($tag->name == '_nolist_') :
                                    $_nolist = true;
                                    break;
                                endif;
                            endforeach;
                            if ($_nolist) :
                                continue;
                            endif;
                        endif;
                        if (in_array($event->endpoint_url, $multiday)) :
                            continue;
                        endif;
                        $bgcolorclass = '';
                        $inline = '';
                        if (isset($event->category)) :
                            if (!empty($event->category->bgcol)) :
                                $bgcolorclass = $event->category->bgcol;
                            elseif (!empty($event->category->color)) :
                                $inline = 'style="background-color:' . $event->category->color . '"';
                            endif;
                        endif; ?>
                        <div class="event-item" itemscope itemtype="http://schema.org/Event">
                            <meta itemprop="startDate" content="<?php echo date_i18n('c', strtotime($event->start)); ?>">
                            <meta itemprop="endDate" content="<?php echo date_i18n('c', strtotime($event->end)); ?>">
                            <div class="event-date">
                                <div class="day-month">
                                    <div class="day"><?php echo $event->start_day . '. '; ?></div>
                                    <div class="month"><?php echo $event->start_month; ?></div>
                                </div>
                                <div class="year"><?php echo $event->start_year; ?></div>
                            </div>
                            <div class="event-info">
                                <?php if ($event->allday) : ?>
                                    <div class="event-time event-allday">
                                        <?php _e('All Day', 'rrze-calendar'); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($event->allday && $event->multiday) : ?>
                                    <?php $multiday[] = $event->endpoint_url; ?>
                                    <div class="event-time">
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
                                    <?php $multiday[] = $event->endpoint_url; ?>
                                    <div class="event-time">
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
                                    <div class="event-time">
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
                                <div class="event-title" itemprop="name">
                                    <a itemprop="url" href="<?php echo $event->endpoint_url; ?>"><?php echo preg_replace('/[^[:alpha:]\s]/', '', $event->label); ?></a>
                                </div>
                                <div class="event-location" itemprop="location">
                                    <?php echo $location && $event->location ? nl2br($event->location) : '&nbsp;'; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php get_footer(); ?>