<div class="kalender">
    <div class="menue">
        <div class="zeit buttons">
            <a href="<?php echo $daten['monat_datum_zurueck']; ?>" class="vergangenheit">&#9664;</a>
            <a href="<?php echo $daten['monat_datum_aktuell']; ?>" class="heute"><?php _e('Heute', 'rrze-calendar'); ?></a>
            <a href="<?php echo $daten['monat_datum_vor']; ?>" class="zukunft">&#9654;</a>
        </div>

        <div class="titel"><?php echo $daten['monat']; ?></div>

        <div class="intervall">
            <div><a class="aktion" href="#">&#9776;</a></div>
            <div class="buttons">
                <a href="<?php echo $daten['tag_datum']; ?>" class="tag"><?php _e('Tag', 'rrze-calendar'); ?></a>
                <a href="<?php echo $daten['woche_datum']; ?>" class="woche"><?php _e('Woche', 'rrze-calendar'); ?></a>
                <span class="monat aktiv"><?php _e('Monat', 'rrze-calendar'); ?></span>
                <a href="<?php echo $daten['liste']; ?>" class="liste"><?php _e('Liste', 'rrze-calendar'); ?></a>
                <?php if ($daten['abonnement_url']): ?>
                <a href="<?php echo $daten['abonnement_url']; ?>" class="tag"><?php _e('Abonnement', 'rrze-calendar'); ?></a>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="inhalt">
        <div class="monatsansicht">
            <div class="kopfzeile clear-fix">
                <div><span class="tag">Mo<span class="lang">ntag</span></span></div>
                <div><span class="tag">Di<span class="lang">enstag</span></span></div>
                <div><span class="tag">Mi<span class="lang">ttwoch</span></span></div>
                <div><span class="tag">Do<span class="lang">nnerstag</span></span></div>
                <div><span class="tag">Fr<span class="lang">eitag</span></span></div>
                <div class="wochenende"><span class="tag">Sa<span class="lang">mstag</span></span></div>
                <div class="wochenende sonntag"><span class="tag">So<span class="lang">nntag</span></span></div>
            </div>

            <?php foreach ($daten['wochen'] as $woche): ?>
            <?php $t = 1; ?>
            <div class="woche">
                <?php foreach ($woche['tage'] as $tag): ?>
                <?php switch ($t) {
                        case 1:
                            $titip = 'titip-right';
                        break;
                        case 7:
                            $titip = 'titip-left';
                        break;
                        default:
                            $titip = 'titip-top';
                } $t++; ?>
                <div class="<?php if (!empty($tag['heute'])): ?>heute <?php endif; ?><?php if (!empty($tag['nicht_im_monat'])): ?>nicht-aktuell <?php endif; ?><?php if (!empty($tag['wochenende'])): ?>wochenende <?php endif; ?><?php if (!empty($tag['sonntag'])): ?>sonntag <?php endif; ?>">
                    <div class="datum"><?php echo $tag['datum_kurz']; ?>.</div>
                    <div class="termine">
                        <div class="center">
                            <?php $k = 1; ?>
                            <?php foreach ($tag['termine'] as $termin): ?>
                            <?php if ($k <= 3): ?>
                            <a href="<?php echo esc_attr(RRZE_Calendar::endpoint_url($termin['slug'])); ?>" class="termin titip-default <?php echo $titip; ?>">
                                <span class="titel"><span class="dashicons dashicons-arrow-right" style="color: <?php echo $termin['farbe']; ?>"></span><?php echo $termin['summary']; ?></span>
                                <span class="titip-liste titip-content thick-border">
                                    <strong><?php echo wordwrap($termin['summary'], 50, "<br>\n"); ?></strong>
                                    <?php if (!empty($termin['location'])): ?>
                                    <br><span><?php echo $termin['location']; ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($termin['ganztagig'])): ?>
                                    <br><?php if (!empty($termin['datum'])): ?><?php echo $termin['datum']; ?><?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($termin['nicht_ganztagig'])): ?>
                                    <?php if (!empty($termin['time'])): ?>
                                    <br> <?php if (!empty($termin['datum'])): ?><?php echo $termin['datum']; ?>, <?php endif; ?><?php printf(__("%s Uhr", 'rrze-calendar'), $termin['time']); ?>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </span>
                            </a>
                            <?php elseif ($k == 4): ?>
                            <a class="termin" href="<?php echo esc_url(add_query_arg('calendar', 'tag_' . $tag["datum"], get_permalink())); ?>"><span class="dashicons dashicons-arrow-down"></span><?php _e('Weitere Termine', 'rrze-calendar'); ?></a>
                            <?php endif; ?>
                            <?php $k += 1; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div><?php endforeach; ?>
            </div><?php endforeach; ?></div></div></div>
