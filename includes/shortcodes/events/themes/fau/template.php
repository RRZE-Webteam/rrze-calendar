<?php

/* Quit */
defined('ABSPATH') || exit;

$multiday = [];
?>
<div class="rrze-calendar events-list">
    <?php if (empty($events_data)): ?>
    <p><?php _e('Keine bevorstehenden Termine.', 'rrze-calendar'); ?></p>
    <?php else: ?>
    <ul>
    <?php foreach ($events_data as $event_date) : ?>
        <?php foreach ($event_date as $event):
            if ($anzahl <= 0):
                break;
            endif;
            if (in_array($event->id, $multiday)):
                continue;
            endif;
            if (!empty($start_date) && $event->start < $start_date) {
                continue;
            }
            if (!empty($end_date) && $event->end > $end_date) {
                continue;
            }
    	    $bgcolorclass = '';
    	    $inline = '';
    	    if (isset($event->category)) :
    		if (!empty($event->category->bgcol)) :
    		    $bgcolorclass = $event->category->bgcol;
    		elseif (!empty($event->category->color)) :
    		    $inline = 'style="background-color:' . $event->category->color.'"';
    		endif;
    	    endif; ?>
            <li>
                <div class="event-item" itemscope itemtype="http://schema.org/Event">
		    <meta itemprop="startDate" content="<?php echo date_i18n( "c", $event->start ); ?>">
		    <meta itemprop="endDate" content="<?php echo date_i18n( "c", $event->end ); ?>">
                    <div class="event-date <?php echo $bgcolorclass; ?>" <?php echo $inline; ?>>
                        <span class="event-date-month"><?php echo $event->start_month_html ?></span>
                        <span class="event-date-day"><?php echo $event->start_day_html ?></span>
                    </div>
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
                        <div class="event-title" itemprop="name">
                            <a itemprop="url" href="<?php echo $event->endpoint_url; ?>"><?php echo esc_html($event->summary); ?></a>
                        </div>
                        <div class="event-location" itemprop="location">
                            <?php echo $event->location ? nl2br($event->location) : '&nbsp;'; ?>
                        </div>
                    </div>
                </div>
            </li>
            <?php $anzahl--; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </ul>
    <?php if ($calendar_page_url): ?>
    <p class="events-more-links">
        <a class="events-more" href="<?php echo $calendar_page_url; ?>"><?php _e('Mehr Veranstaltungen', 'rrze-calendar'); ?></a>
    </p>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($calendar_subscribe_url): ?>
    <p class="events-more-links">
        <a class="events-more" href="<?php echo $calendar_subscribe_url; ?>"><?php _e('Abonnement', 'rrze-calendar'); ?></a>
    </p>
    <?php endif; ?>
</div>
