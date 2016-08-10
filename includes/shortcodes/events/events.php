<?php

add_shortcode('rrze-events', array('RRZE_Calendar_Events_Shortcode', 'shortcode'));
add_shortcode('rrze-termine', array('RRZE_Calendar_Events_Shortcode', 'shortcode'));

class RRZE_Calendar_Events_Shortcode {
    
    public static function shortcode($atts, $content = "") {
        global $event_events_helper, $event_calendar_helper;    

        $atts = shortcode_atts(
            array(
                'kategorien' => '',     // Mehrere Kategorien (Titelform) werden durch Komma getrennt.
                'schlagworte' => '',    // Mehrere Schlagworte (Titelform) werden durch Komma getrennt.
                'anzahl' => 10,         // Anzahl der Termineausgabe. Standardwert: 10.
                'abonnement_link' => 0  // Abonnement-Link anzeigen (1 oder 0).
            ), $atts
        );

        $anzahl = intval($atts['anzahl']);
        if ($anzahl < 1) {
            $anzahl = 10;
        }

        $terms = explode(',', $atts['kategorien']);
        $terms = array_map('trim', $terms);

        $feed_ids = array();
        foreach ($terms as $value) {
            $term = RRZE_Calendar::get_category_by('slug', $value);
            if (empty($term)) {
                continue;
            }
            foreach ($term->feed_ids as $feed_id) {
                $feed_ids[$feed_id] = $feed_id;
            }
        }

        $terms = explode(',', $atts['schlagworte']);
        $terms = array_map('trim', $terms);

        $event_tag_ids = array();
        foreach ($terms as $value) {
            $term = RRZE_Calendar::get_tag_by('slug', $value);
            if (empty($term)) {
                continue;
            }
            foreach ($term->feed_ids as $feed_id) {
                $feed_ids[$feed_id] = $feed_id;
            }
        }

        $subscribe_url = '';
        if (!empty($atts['abonnement_link'])) {
            $subscribe_url = RRZE_Calendar::webcal_url(array('feed-ids' => !empty($feed_ids) ? implode(',', $feed_ids) : ''));
        }

        $atts['filter'] = array(
            'feed_ids' => $feed_ids
        );

        $timestamp = RRZE_Calendar_Functions::gmt_to_local(time());
        $events_result = RRZE_Calendar::get_events_relative_to($timestamp, $anzahl, 0, $atts);
        $dates = RRZE_Calendar_Functions::get_calendar_dates($events_result['events']);
        
        $content = self::get_content($dates, $subscribe_url);
        
        return apply_filters('rrze-calendar-events-shortcode', $content, $dates, $subscribe_url);
    }
    
    private static function get_content($dates, $subscribe_url) {
        ob_start();
        ?>
        <div class="events-list">
            <?php if (empty($dates)): ?>
            <p><?php _e('Keine bevorstehenden Termine.', 'rrze-calendar'); ?></p>
            <?php else: ?>
            <div>
                <?php foreach ($dates as $date): ?>
                    <?php foreach ($date as $event): ?>                                         
                        <div class="event-detail-item">
                            <h2 class="event-title">
                                <a href="<?php echo esc_attr(RRZE_Calendar::endpoint_url($event->slug)); ?>"><?php echo esc_html($event->summary); ?></a>
                            </h2>
                            <div class="event-date">
                                <?php echo $event->long_start_date ?>
                            </div>
                            <div class="event-info <?php if ($event->allday) echo 'event-allday'; ?>">
                                <?php if ($event->allday && !$event->multiday) : ?>
                                    <div class="event-allday" style="text-transform: uppercase;">
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
                <p class="events-more-links">
                    <a class="events-more" href="<?php echo RRZE_Calendar::endpoint_url(); ?>"><?php _e('Mehr Veranstaltungen', 'rrze-calendar'); ?></a>
                </p>                      
            </div>
            <?php endif; ?>
            <?php if($subscribe_url): ?>
            <p class="events-more-links">
                <a class="events-more" href="<?php echo $subscribe_url; ?>"><?php _e('Abonnement', 'rrze-calendar'); ?></a>
            </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();        
    }
    
}
