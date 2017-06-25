<?php $multiday = []; ?>
<div class="kalender">
    <div class="menue">

        <span class="titel"></span>

        <span class="intervall">
            <a class="aktion" href="#">&#9776;</a>
            <div class="buttons">
                <a href="<?php echo $daten['tag']; ?>" class="tag"><?php _e('Tag', 'rrze-calendar'); ?></a>
                <a href="<?php echo $daten['woche']; ?>" class="woche"><?php _e('Woche', 'rrze-calendar'); ?></a>
                <a href="<?php echo $daten['monat']; ?>" class="monat"><?php _e('Monat', 'rrze-calendar'); ?></a>
                <span class="liste aktiv">Liste</span>
                <?php if ($daten['abonnement_url']): ?>
                <a href="<?php echo $daten['abonnement_url']; ?>" class="tag"><?php _e('Abonnement', 'rrze-calendar'); ?></a>
                <?php endif; ?>                
            </div>
        </span>

    </div>
    
    <div class="inhalt">
        <div class="listenansicht">
            <div class="events-list">
                <?php if (empty($daten['termine'])): ?>
                <p><?php _e('Keine bevorstehenden Termine.', 'rrze-calendar'); ?></p>
                <?php else: ?>
                    <?php foreach ($daten['termine'] as $termin): ?>
                        <?php $endpoint_url = RRZE_Calendar::endpoint_url($termin['slug']); ?>
                        <?php if (in_array($endpoint_url, $multiday)): ?>
                            <?php continue; ?>
                        <?php endif; ?>                 
                        <div class="event-info" style="border-left-color: <?php echo $termin['farbe']; ?>">
                            <div class="event-title-date">
                                <?php echo $termin['long_start_date']; ?>
                            </div>
                            
                            <?php if ($termin['allday']) : ?>
                                <div class="event-time event-allday">
                                    <?php _e('Ganztägig', 'rrze-calendar'); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($termin['allday'] && $termin['multiday']) : ?>
                                <?php $multiday[] = $endpoint_url; ?>
                                <div class="event-time">
                                    <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $termin['long_e_start_date'], $termin['long_e_end_date'])) ?>
                                </div>            
                            <?php elseif (!$termin['allday'] && $termin['multiday']) : ?>
                                <?php $multiday[] = $endpoint_url; ?>
                                <div class="event-time">
                                    <?php echo esc_html(sprintf( __('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $termin['long_e_start_date'], $termin['short_e_start_time'], $termin['long_e_end_date'], $termin['short_e_end_time'])) ?>
                                </div>
                            <?php elseif (!$termin['allday']): ?>
                                <div class="event-time">
                                    <?php echo esc_html(sprintf( __('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $termin['short_start_time'], $termin['short_end_time'])) ?>
                                </div>            
                            <?php endif; ?>
                            
                            <div class="event-title"><a href="<?php echo esc_attr($endpoint_url); ?>">
                                <?php echo esc_html(apply_filters('the_title', $termin['summary'])); ?></a>
                            </div>
                            
                            <div class="event-location">
                                <?php if (!empty($termin['location'])): ?><?php echo $termin['location']; ?><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>      
    </div> 
</div>
