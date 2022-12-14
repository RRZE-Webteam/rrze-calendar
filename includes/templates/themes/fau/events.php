<?php

defined('ABSPATH') || exit;

use RRZE\Calendar\Util;

if (function_exists('fau_initoptions')) {
    $options = fau_initoptions();
} else {
    $options = array();
}

$multiday = [];

$breadcrumb = '';

$breadcrumb .= '<nav aria-label="' . __('Breadcrumb', 'fau') . '" class="breadcrumbs">';
$breadcrumb .= '<ol class="breadcrumblist" itemscope="" itemtype="https://schema.org/BreadcrumbList">';
$breadcrumb .= '<li itemprop="itemListElement" itemscope="" itemtype="https://schema.org/ListItem"><a itemprop="item" href="' . site_url('/') . '"><span itemprop="name">' . __('Startseite', 'fau') . '</span></a><meta itemprop="position" content="1"></li>';
$breadcrumb .= '</ol>';
$breadcrumb .= '</nav>';


get_header(); ?>

<section id="hero" class="hero-small">
    <div class="container hero-content">
        <div class="row">
            <div class="col-xs-12">
                <?php echo $breadcrumb; ?>
            </div>
        </div>
        <div class="row" aria-hidden="true" role="presentation">
            <div class="col-xs-12">
                <p class="presentationtitle"><?php echo $calendar_endpoint_name; ?></p>
            </div>
        </div>
    </div>
</section>

<div id="content">
    <div class="content-container">
        <div class="content-row">
            <div class="col-xs-12">
                <main>
                    <h1 class="screen-reader-text"><?php echo $calendar_endpoint_name; ?></h1>
                    <div class="rrze-calendar events-list">
                        <?php if (empty($events_data)) : ?>
                            <p><?php _e('Keine bevorstehenden Termine', 'rrze-calendar'); ?></p>
                        <?php else : ?>
                            <ul>
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
                                        if (!empty($event->category)) :
                                            // Color
                                            $bgcolorclass = !empty($event->category->color) ? $event->category->color : '';
                                            $inline = !empty($event->category->color) ? sprintf('style="background-color:%1$s; color:%2$s"', $event->category->color, Util::getContrastYIQ($event->category->color)) : '';
                                        endif; ?>
                                        <li>
                                            <div class="event-item" itemscope itemtype="http://schema.org/Event">
                                                <meta itemprop="startDate" content="<?php echo date_i18n('c', strtotime($event->start)); ?>">
                                                <meta itemprop="endDate" content="<?php echo date_i18n('c', strtotime($event->end)); ?>">
                                                <div class="event-date <?php echo $bgcolorclass; ?>" <?php echo $inline; ?>>
                                                    <span class="event-date-month"><?php echo $event->start_month; ?></span>
                                                    <span class="event-date-day"><?php echo $event->start_day; ?></span>
                                                </div>
                                                <div class="event-info">
                                                    <?php if ($event->allday) : ?>
                                                        <div class="event-time event-allday">
                                                            <?php _e('Ganztägig', 'rrze-calendar'); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($event->allday && $event->multiday) : ?>
                                                        <?php $multiday[] = $event->endpoint_url; ?>
                                                        <div class="event-time">
                                                            <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_start_date, $event->long_end_date)) ?>
                                                        </div>
                                                    <?php elseif (!$event->allday && $event->multiday) : ?>
                                                        <?php $multiday[] = $event->endpoint_url; ?>
                                                        <div class="event-time">
                                                            <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $event->long_start_date, $event->short_start_time, $event->long_end_date, $event->short_end_time)) ?>
                                                        </div>
                                                    <?php elseif (!$event->allday) : ?>
                                                        <div class="event-time">
                                                            <?php echo esc_html(sprintf(__('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="event-title" itemprop="name">
                                                        <a itemprop="url" href="<?php echo $event->endpoint_url; ?>"><?php echo esc_html($event->summary); ?></a>
                                                    </div>
                                                    <div class="event-location" itemprop="location">
                                                        <?php echo !empty($event->location) ? nl2br($event->location) : '&nbsp;'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>