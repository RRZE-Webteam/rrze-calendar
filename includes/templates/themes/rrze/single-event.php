<?php

/* Quit */
defined('ABSPATH') || exit;

$event = &$events_data;
$bgcolorclass = '';
$inline = '';
if (isset($event->category)) {
    if (!empty($event->category->bgcol)) {
	$bgcolorclass = $event->category->bgcol;
    } elseif (!empty($event->category->color)) {
	$inline = 'style="background-color:' . $event->category->color.'"';
    }
}

get_header(); ?>

<?php if (!is_front_page()) { ?>
    <div id="sidebar" class="sidebar">
        <?php get_sidebar('page'); ?>
    </div><!-- .sidebar -->
<?php } ?>

<div id="primary" class="content-area">
    <div id="content" class="site-content" role="main">
        <div class="event-detail-item" itemscope itemtype="http://schema.org/Event">
            <h2 itemprop="name">
                <?php echo $event->summary; ?>
            </h2>    
	    
            <div class="event-date <?php echo $bgcolorclass; ?>" <?php echo $inline; ?>>
                <div class="day-month">
                    <span class="day"><?php echo $event->start_day . '. '; ?></span>
                    <span class="month"><?php echo $event->start_month; ?></span>
                </div>
                <span class="year"><?php echo $event->start_year; ?></span>
            </div>
            <div class="event-info">
		<meta itemprop="startDate" content="<?php echo date_i18n('c', strtotime($event->start)); ?>">
		<meta itemprop="endDate" content="<?php echo date_i18n('c', strtotime($event->end)); ?>">
                <?php if ($event->allday) : ?>
                    <div class="event-time event-allday">
                        <?php _e('Ganztägig', 'rrze-calendar'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($event->allday && !$event->multiday && !$event->recurrence_rules) : ?>
                    <div class="event-time">
                        <?php echo esc_html($event->long_start_date) ?>
                    </div>
                <?php endif; ?>                
                <?php if ($event->allday && $event->multiday) : ?>
                    <div class="event-time">
                        <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_start_date, $event->long_end_date)) ?>
                    </div>            
                <?php elseif (!$event->allday && $event->multiday) : ?>
                    <div class="event-time">
                        <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $event->long_start_date, $event->short_start_time, $event->long_end_date, $event->short_end_time)) ?>
                    </div>
                <?php elseif (!$event->allday &&  $event->recurrence_rules): ?>
                    <div class="event-time">
                        <?php echo esc_html(sprintf(__('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
                    </div>                
                <?php elseif (!$event->allday &&  !$event->recurrence_rules): ?>
                    <div class="event-time">
                        <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s Uhr', 'rrze-calendar'), $event->long_start_date, $event->short_start_time, $event->short_end_time)) ?>
                    </div>            
                <?php endif; ?>
                <?php if ($event->recurrence_rules) : ?>
                    <div class="event-rrules">
                        <?php echo ucfirst($event->rrules_human_readable); ?>
                    </div>                
                <?php endif; ?>
                <?php if ($event->location) : ?>
                    <p class="event-location" itemprop="location">
                        <?php printf('<strong>%1$s: </strong>%2$s', __('Ort', 'rrze-calendar'), $event->location); ?>
                    <p>
                <?php endif; ?>
                <?php if ($event->description) : ?>
                    <p>
                        <?php echo make_clickable(nl2br($event->description)); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <p class="events-more-links">
            <a class="events-more" href="<?php echo $event->subscribe_url; ?>">
                <i class="fa fa-calendar-plus-o" aria-hidden="true"></i>
                <?php _e('Veranstaltung abonnieren', 'rrze-calendar'); ?>
            </a>
        </p>
    </div>
</div>

<?php get_footer(); ?>