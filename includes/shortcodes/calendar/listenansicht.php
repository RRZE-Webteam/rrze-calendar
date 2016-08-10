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
                <p><?php _e('Keine bevorstehenden Termine', 'rrze-calendar'); ?></p>
                <?php else: ?>
                <ul>
                    <?php foreach ($daten['termine'] as $termin): ?>
                    <li class="<?php if (!empty($termin['heute'])) echo 'event-today'; ?>">
                        <div class="event-date" style="background-color: <?php echo $termin['farbe']; ?>"><div class="event-date-month"><?php echo date_i18n('M', $termin['start_timestamp'], TRUE); ?></div><div class="event-date-day"><?php echo date_i18n('d', $termin['start_timestamp'], TRUE); ?></div></div>                          
                        <div class="event-info event-id-<?php echo $termin['id']; ?><?php if (!empty($termin['ganztagig'])) : echo 'event-allday'; endif; ?>"><?php if (!empty($termin['nicht_ganztagig'])): ?><div class="event-time"><?php printf(__("%s Uhr", 'rrze-calendar'), $termin['time']); ?></div><?php endif; ?>
                        <div class="event-title"><a href="<?php echo esc_attr(RRZE_Calendar::endpoint_url($termin['slug'])); ?>"><?php echo esc_html(apply_filters('the_title', $termin['summary'])); ?></a></div>
                        <div class="event-location"><?php if (!empty($termin['location'])): ?><?php echo $termin['location']; ?><?php endif; ?></div></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>      
    </div> 
</div>
