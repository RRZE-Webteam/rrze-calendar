<?php
/* Quit */
defined('ABSPATH') || exit;

$multiday = [];

get_header();
wp_enqueue_style('rrze-calendar');
?>

<div class="content-wrap">
    <div id="blog-wrap" class="blog-wrap cf">
        <div id="primary" class="site-content cf rrze-calendar" role="main">

            <header class="entry-header">
                <h1 class="archive-title"><?php _e('Events', 'rrze-calendar'); ?></h1>
            </header>

            <div class="entry-content">
                <ul class="rrze-calendar events-list">
                    <?php if (empty($events_data)) : ?>
                        <p><?php _e('No upcoming events.', 'rrze-calendar'); ?></p>
                    <?php else : ?>
                        <?php foreach ($events_data as $date) : ?>
                            <?php foreach ($date as $event) : ?>
                                <?php
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
                                ?>
                                <?php if (in_array($event->endpoint_url, $multiday)) : ?>
                                    <?php continue; ?>
                                <?php endif;
                                $inline = '';
                                if (isset($event->category) && !empty($event->category->color)) :
                                    $inline = 'style="border-left: 5px solid ' . $event->category->color . '; padding-left: 10px;"';
                                endif;
                                ?>
                                <li class="event-item" <?php echo $inline; ?> itemscope itemtype="http://schema.org/Event">
                                    <meta itemprop="startDate" content="<?php echo date_i18n('c', strtotime($event->start)); ?>">
                                    <meta itemprop="endDate" content="<?php echo date_i18n('c', strtotime($event->end)); ?>">
                                    <div class="event-title" itemprop="name">
                                        <a itemprop="url" href="<?php echo $event->endpoint_url; ?>"><?php echo preg_replace('/[^[:alpha:]\s]/', '', $event->label) ?></a>
                                    </div>
                                    <div class="event-title-date">
                                        <?php echo $event->long_start_date ?>
                                    </div>
                                    <div class="event-info">
                                        <?php if ($event->allday) : ?>
                                            <div class="event-date event-allday">
                                                <?php _e('All Day', 'rrze-calendar'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($event->allday && $event->multiday) : ?>
                                            <?php $multiday[] = $event->endpoint_url; ?>
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
                                            <?php $multiday[] = $event->endpoint_url; ?>
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
                                        <?php elseif (!$event->allday) : ?>
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
                                        <?php endif; ?>
                                        <?php if ($event->location) : ?>
                                            <div class="event-location" itemprop="location">
                                                <?php echo esc_html($event->location); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($event->description) : ?>
                                            <div class="event-summary">
                                                <?php echo make_clickable(wp_trim_words(nl2br($event->description))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div><!-- end #primary -->

        <?php get_sidebar(); ?>

    </div><!-- end .blog-wrap -->
</div><!-- end .content-wrap -->

<?php get_footer(); ?>