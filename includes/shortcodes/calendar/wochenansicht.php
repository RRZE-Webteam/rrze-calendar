<div class="kalender">
    <?php $is_hetue = false; ?>
    <div class="menue">
        <span class="zeit buttons">
            <a href="<?php echo $daten['woche_datum_zurueck']; ?>" class="vergangenheit">&#9664;</a>
            <a href="<?php echo $daten['woche_datum_aktuell']; ?>" class="heute"><?php _e('Heute', 'rrze-calendar'); ?></a>
            <a href="<?php echo $daten['woche_datum_vor']; ?>" class="zukunft">&#9654;</a>
        </span>

        <span class="titel"><?php echo $daten['monat']; ?></span>

        <span class="intervall">
            <a class="aktion" href="#">&#9776;</a>
            <div class="buttons">
                <a href="<?php echo $daten['tag_datum']; ?>" class="tag"><?php _e('Tag', 'rrze-calendar'); ?></a>
                <span class="woche aktiv"><?php _e('Woche', 'rrze-calendar'); ?></span>
                <a href="<?php echo $daten['monat_datum']; ?>" class="monat"><?php _e('Monat', 'rrze-calendar'); ?></a>
                <a href="<?php echo $daten['liste']; ?>" class="liste"><?php _e('Liste', 'rrze-calendar'); ?></a>
                <?php if ($daten['abonnement_url']): ?>
                    <a href="<?php echo $daten['abonnement_url']; ?>" class="tag"><?php _e('Abonnement', 'rrze-calendar'); ?></a>
                <?php endif; ?>                
            </div>
        </span>

    </div>

    <div class="inhalt">
        <div class="wochenansicht" style="height: <?php echo get_option('rrze_calendar')['calendar_height']; ?>px">

            <div class="kopfzeile clear-fix">
                <?php foreach ($daten['tage'] as $tag): ?>
                    <div class="<?php if (!empty($tag['wochenende'])): ?>wochenende <?php endif; ?><?php if (!empty($tag['sonntag'])): ?>sonntag <?php endif; ?>">
                        <span class="datum"><?php echo $tag['datum_kurz']; ?>.</span> <span class="tag"><?php echo $tag['wochentag_anfang']; ?><span class="lang"><?php echo $tag['wochentag_ende']; ?></span></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            $ganztagig = array();
            foreach ($daten['tage'] as $tag):
                foreach ($tag['termine'] as $termin):
                    if (!empty($termin['ganztagig'])):
                        $ganztagig[$termin['id']] = array(
                            'id' => $termin['id'],
                            'farbe' => $termin['farbe'],
                            'slug' => $termin['slug'],
                            'summary' => $termin['summary'],
                            'start' => date('N', strtotime($termin['datum_start'])),
                            'ende' => date('N', strtotime($termin['datum_ende']))
                        );
                    endif;
                endforeach;
            endforeach;
            ?>
            <div class="ganztagige <?php echo!empty($ganztagig) ? '' : 'clear-fix'; ?>">
                <span><?php _e('Ganztägig', 'rrze-calendar'); ?></span>            
                <?php if (!empty($ganztagig)): ?>
                    <?php
                    $i = 1;
                    foreach ($ganztagig as $termin):
                        ?>
                        <?php
                        $margin_left = ($termin['start'] - 1) * 12.88 + 10;
                        $width = ($termin['ende'] - $termin['start'] + 1) * 14;
                        ?>
                        <div class="tag" style="margin-left: <?php echo $margin_left; ?>%;width: <?php echo $width; ?>%;border-left:4px solid <?php echo $termin['farbe']; ?>">
                            <a href="<?php echo esc_attr(RRZE_Calendar::endpoint_url($termin['slug'])); ?>" class="termin ganztagig">
                                <span class="titel"><?php echo $termin['summary']; ?></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="margin-left: 10%; width: 12.88%;"></div>
                <?php endif; ?>
            </div>





            <div class="tage clear-fix">
                <div class="stunden">
                    <?php foreach ($daten['stunden'] as $stunde): ?>
                        <div class="stunde"><span class="icon-uhr">&#9719; </span><?php echo $stunde['stunde']; ?></div>

                        <div class="stunde-line"></div>
                    <?php endforeach; ?>
                </div>
                <?php $t = 1; ?>
                <?php $i = -1 ?>
                <?php foreach ($daten['tage'] as $tag): ?>
                    <?php $i++; ?>
                    <?php
                    switch ($t) {
                        case 1:
                            $titip = 'titip-right';
                            break;
                        case 7:
                            $titip = 'titip-left';
                            break;
                        default:
                            $titip = 'titip-bottom';
                    } $t++;
                    ?>        
                    <?php
                    $day = new DateTime($tag['datum'] . ' 00:00 +' . get_option('gmt_offset'));
                    $day_current = current_time('d');
                    $month_current = current_time('m');
                    $year_current = current_time("Y");

                    $current = new DateTime($year_current . '-' . $month_current . '-' . $day_current . ' 00:00 +' . get_option('gmt_offset'));

                    $background = "";

                    if ($day->getTimestamp() == $current->getTimestamp()) {

                        $background = "background:#fdfad3;margin-left:2px;";

                        $is_hetue = true;
                    }
                    ?>
                    <div style="<?php echo $background; ?>left:<?php echo (($i * 13) + 10); ?>%;height: 1490px" class="tag <?php if (!empty($tag['wochenende'])): ?>wochenende <?php endif; ?><?php if (!empty($tag['sonntag'])): ?>sonntag <?php endif; ?><?php if (!empty($$tag['heute'])): ?>heute<?php endif; ?>" data-anfang="<?php echo $tag['tag_anfang']; ?>">
                        <?php
                        ;
                        foreach ($tag['termine'] as $termin):
                            ?>

                            <?php
                            if (!empty($termin['time_start']) && !in_array($t, array(2, 8)) && in_array(explode(':', $termin['time_start'])[0], array($daten['stunden'][0]['stunde'], $daten['stunden'][1]['stunde']))):
                                $titip = 'titip-bottom';
                            endif;
                            ?>
                            <?php if (!empty($termin['nicht_ganztagig'])): ?>
                                <a href="<?php echo esc_attr(RRZE_Calendar::endpoint_url($termin['slug'])); ?>" class="termin clear-fix" style="border-left-color: <?php echo $termin['farbe']; ?>; height: <?php echo $termin['duration'] + ($termin['duration'] / 60); ?>px; top: <?php echo (($termin['start']) + 25 + ($termin['start'] / 60)); ?>px; width: <?php echo $termin['width']; ?>%;  left: <?php echo $termin['left']; ?>%;" data-start="<?php echo $termin['start']; ?>" data-dauer="<?php echo $termin['duration']; ?>" data-ende="<?php echo $termin['ende']; ?>" data-farbe="<?php echo $termin['farbe']; ?>">
                                    <span class="permalink titip-default <?php echo $titip; ?>">
                                        <?php if (!empty($termin['time_start'])): ?>
                                            <?php if ($termin['duration'] > 50) { ?> 
                                                <?php if (!empty($termin['ganztagig'])): ?>
                                                    <span class="zeit"><?php printf(__('%1$s bis %2$s', 'rrze-calendar'), $termin['datum_start'], $termin['datum_ende']); ?></span>
                                                <?php else: ?>
                                                    <span class="zeit"><?php echo $termin['time']; ?></span>
                                                <?php endif; ?>
                                            <?php } ?>
                                        <?php endif; ?> 
                                        <span class="titel">
                                            <?php if ($termin['duration'] > 24) { ?>       
                                                <?php echo $termin['summary']; ?>

                                                <?php
                                            } else {

                                                echo '...';
                                            }
                                            ?>
                                        </span>                            





                                        <span class="titip-liste titip-content thick-border">
                                            <strong><?php echo wordwrap($termin['summary'], 50, "<br>\n"); ?></strong>

                                            <?php if ($termin['duration'] > 71) { ?>    
                                                <?php if (!empty($termin['location'])): ?>
                                                    <br><span><?php echo $termin['location']; ?></span>
                                                <?php endif; ?>
                                            <?php } ?> 

                                            <?php if ($termin['duration'] > 50) { ?> 
                                                <?php if (!empty($termin['time'])): ?>
                                                    <br> <?php if (!empty($termin['datum'])): ?><?php echo $termin['datum']; ?>, <?php endif; ?><?php printf(__("%s Uhr", 'rrze-calendar'), $termin['time']); ?>
                                                <?php endif; ?>

                                            <?php } ?>
                                        </span>
                                    </span>
                                </a>
                            <?php endif; ?>                   
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($is_hetue) {
                    ?>

                    <div class="stunde-now" style="top:<?php echo 78 + (current_time('H') * 60) + (current_time('H') + current_time('i') ); ?>px" >


                    </div>
                <?php } ?>
            </div>



        </div></div></div>
