<div id="rrze-calendar" class="rrze-calendar kalender">
    <div class="menue">
        <div class="zeit buttons">
            <a href="<?php echo $daten['monat_datum_zurueck']; ?>#rrze-calendar" class="vergangenheit">&#9664;</a>
            <a href="<?php echo $daten['monat_datum_aktuell']; ?>#rrze-calendar" class="heute"><?php _e('Heute', 'rrze-calendar'); ?></a>
            <a href="<?php echo $daten['monat_datum_vor']; ?>#rrze-calendar" class="zukunft">&#9654;</a>
        </div>

        <div class="titel"><?php echo $daten['monat']; ?></div>
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

            <?php foreach ($daten['wochen'] as $woche) : ?>
                <?php $t = 1; ?>
                <div class="woche" style="height: <?php echo $woche['itemcount'] > 4 ? 9 + ($woche['itemcount'] - 4) : 9; ?>em">
                    <?php foreach ($woche['tage'] as $tag) : ?>
                        <?php switch ($t) {
                                    case 1:
                                        $titip = 'titip-right';
                                        break;
                                    case 7:
                                        $titip = 'titip-left';
                                        break;
                                    default:
                                        $titip = 'titip-top';
                                }
                                $t++; ?>
                            <div class="<?php if (!empty($tag['heute'])) : ?>heute <?php endif; ?><?php if (!empty($tag['nicht_im_monat'])) : ?>nicht-aktuell <?php endif; ?><?php if (!empty($tag['wochenende'])) : ?>wochenende <?php endif; ?><?php if (!empty($tag['sonntag'])) : ?>sonntag <?php endif; ?>">
                                <div class="datum"><?php echo $tag['datum_kurz']; ?>.</div>
                                <div class="termine">
                                    <div class="center">
                                        <?php foreach ($tag['termine'] as $termin) : ?>
                                            <a href="<?php echo esc_attr(RRZE_Calendar::endpoint_url($termin['slug'])); ?>" class="termin titip-default <?php echo $titip; ?>">
                                                <span class="titel"><span class="dashicons dashicons-arrow-right" style="color: <?php echo $termin['farbe']; ?>"></span><?php echo $termin['summary']; ?></span>
                                                <span class="titip-liste titip-content thick-border">
                                                    <strong><?php echo wordwrap($termin['summary'], 50, "<br>\n"); ?></strong>
                                                    <?php if (!empty($termin['location'])) : ?>
                                                        <br><span><?php echo $termin['location']; ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($termin['allday']) : ?>
                                                        <div class="event-time event-allday">
                                                            <?php _e('Ganztägig', 'rrze-calendar'); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($termin['allday'] && $termin['multiday']) : ?>
                                                        <div class="event-time">
                                                            <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $termin['long_start_date'], $termin['long_end_date'])) ?>
                                                        </div>
                                                    <?php elseif (!$termin['allday'] && $termin['multiday']) : ?>
                                                        <div class="event-time">
                                                            <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $termin['long_start_date'], $termin['short_start_time'], $termin['long_end_date'], $termin['short_end_time'])) ?>
                                                        </div>
                                                    <?php elseif (!$termin['allday']) : ?>
                                                        <div class="event-time">
                                                            <?php echo esc_html(sprintf(__('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $termin['short_start_time'], $termin['short_end_time'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div><?php endforeach; ?>
                </div><?php endforeach; ?>
        </div>
    </div>

    <div class="inhalt-mobile">
        <div class="listenansicht">
            <div class="events-list">
                <?php foreach ($daten['wochen'] as $woche) : ?>
                    <?php foreach ($woche['tage'] as $tag) : ?>
                        <?php if (!empty($tag['nicht_im_monat'])) : continue;
                                endif; ?>
                        <?php foreach ($tag['termine'] as $termin) : ?>
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
                                    <div class="event-time">
                                        <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $termin['long_start_date'], $termin['long_end_date'])) ?>
                                    </div>
                                <?php elseif (!$termin['allday'] && $termin['multiday']) : ?>
                                    <div class="event-time">
                                        <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $termin['long_start_date'], $termin['short_start_time'], $termin['long_end_date'], $termin['short_end_time'])) ?>
                                    </div>
                                <?php elseif (!$termin['allday']) : ?>
                                    <div class="event-time">
                                        <?php echo esc_html(sprintf(__('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $termin['short_start_time'], $termin['short_end_time'])) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="event-title"><a href="<?php echo esc_attr(RRZE_Calendar::endpoint_url($termin['slug'])); ?>">
                                        <?php echo esc_html(apply_filters('the_title', $termin['summary'])); ?></a>
                                </div>

                                <div class="event-location">
                                    <?php if (!empty($termin['location'])) : ?><?php echo $termin['location']; ?><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>