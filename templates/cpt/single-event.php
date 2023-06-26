<?php

/**
 * The template for displaying a single post.
 *
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
 */

global $pagebreakargs;

use RRZE\Calendar\Utils;

get_header();
wp_enqueue_style( 'rrze-calendar-sc-events' );
wp_enqueue_style( 'dashicons' );

while (have_posts()) : the_post(); ?>

    <div id="content">
        <div class="content-container">
            <div class="content-row">
                <main>
                    <article  class="rrze-event" itemscope itemtype="http://schema.org/Event">
                        <h1 id="maintop" class="mobiletitle" itemprop="name"><?php the_title(); ?></h1>
                        <?php
                        // Thumbnail
                        if (has_post_thumbnail() && !post_password_required()) {
                            the_post_thumbnail('medium');
                        }

                        $id = get_the_ID();
                        $meta = get_post_meta($id);
                        $eventItems = Utils::getMeta($meta, 'event-items');
                        $allDay = Utils::getMeta($meta, 'all-day');
                        $firstItemTSstart_ID = array_key_first($eventItems);
                        $firstItemTSstart = explode('#', $firstItemTSstart_ID)[0];
                        $firstItemEnd = reset($eventItems);
                        $scheduleClass = count($eventItems) > 3 ? 'cols-3' : '';
                        $location = Utils::getMeta($meta, 'location');
                        $prices = Utils::getMeta($meta, 'prices');
                        $registrationUrl = Utils::getMeta($meta, 'registration-url');
                        $downloads = Utils::getMeta($meta, 'downloads');
                        $categoryObjects = wp_get_object_terms($id, 'rrze-calendar-category');

                        $eventItemsFormatted = [];
                        foreach ($eventItems as $TSstart_ID => $TSend) {
                            $TSstart = explode('#', $TSstart_ID)[0];
                            if ($TSstart < time()) continue;
                            $startDay = date('Y-m-d', $TSstart);
                            $endDay = date('Y-m-d', $TSend);
                            if ($allDay == 'on' || $startDay != $endDay) {
                                $eventItemsFormatted[] = [
                                    'date' => ($endDay == $startDay ? date_i18n(get_option('date_format'), $TSstart) : date_i18n(get_option('date_format'), $TSstart)
                                        . ' &ndash; '
                                        . date_i18n(get_option('date_format'), $TSend)),
                                    'time' => '',
                                    'startISO' => $startDay,
                                    'endISO' => $endDay,
                                ];
                            } else {
                                $eventItemsFormatted[] = [
                                    'date' => date_i18n(get_option('date_format'), $TSstart),
                                    'time' => date_i18n(get_option('time_format'), $TSstart) . ' &ndash; ' . date_i18n(get_option('time_format'), $TSend),
                                    'startISO' => date_i18n('c', $TSstart),
                                    'endISO' => date_i18n('c', $TSend),
                                ];
                            }
                        }

                        // Schedule
                        echo '<div class="rrze-event-schedule">'
                            . '<p><span class="rrze-event-date"><span class="dashicons dashicons-calendar"></span><span class="sr-only">' . __('Date', 'rrze-calendar') . ': </span>' . $eventItemsFormatted[0]['date'] . '</span>'
                            . '<meta itemprop="startDate" content="'. $eventItemsFormatted[0]['startISO'] . '">'
                            . '<meta itemprop="endDate" content="'. $eventItemsFormatted[0]['endISO'] . '">'
                            . (($allDay != 'on' && !strpos($eventItemsFormatted[0]['date'], '&ndash;')) ? '<span class="rrze-event-time"><span class="dashicons dashicons-clock"></span><span class="sr-only">' . __('Time', 'rrze-calendar') . ': </span>' . $eventItemsFormatted[0]['time'] . '</span>' : '')
                            . ($location != '' ? '<span class="rrze-event-location" itemprop="location" itemscope><span class="dashicons dashicons-location"></span><span class="sr-only">' . __('Location', 'rrze-calendar') . ': </span>' . $location . '</span>' : '')
                            . '</p>';
                        if (count($eventItemsFormatted) > 1) {
                            $upcomingItems = '';
                            foreach ($eventItemsFormatted as $k => $eventItemFormatted) {
                                if ($k == 0 ) continue;
                                $upcomingItems .= '<li><span class="dashicons dashicons-calendar"></span><span class="rrze--event-date">' . $eventItemFormatted['date'] . '</span>'
                                    . '<meta itemprop="startDate" content="'. $eventItemFormatted['startISO'] . '">'
                                    . '<meta itemprop="endDate" content="'. $eventItemFormatted['endISO'] . '">'
                                    .'</li>';
                            }
                            echo do_shortcode('[collapsibles][collapse title="Weitere Termine"]<ul class="' . $scheduleClass . '">'.$upcomingItems . '</ul>[/collapse][/collapsibles]');
                        }
                        echo '</div>';

                        // Description
                        echo '<div class="rrze-event-description" itemprop="description">';
                        $description = Utils::getMeta($meta, 'description');
                        echo wpautop($description);
                        echo '</div>';
                        ?>
                    </article>

                    <?php if (strlen($location . $prices . $registrationUrl) > 0 || (!is_wp_error($categoryObjects) && !empty($categoryObjects)) || !empty($downloads)) { ?>
                        <aside class="rrze-event-details">
                            <?php
                            echo '<h2>' . __('Event Details', 'rrze-calendar') . '</h2>';

                            // Date
                            echo '<dt>' . __('Date', 'rrze-calendar') . ':</dt><dd>' . $eventItemsFormatted[0]['date'] . '</dd>';

                            // Time
                            if ($allDay != 'on' && !strpos($eventItemsFormatted[0]['date'], '&ndash;')) {
                                echo '<dt>' . __('Time', 'rrze-calendar') . ':</dt><dd>' . $eventItemsFormatted[0]['time'] . '</dd>';
                            }

                            // Location
                            if ($location != '') {
                                echo '<dt>' . __('Location', 'rrze-calendar') . ':</dt><dd>' . wpautop($location) . '</dd>';
                            }
                            $vc_url = Utils::getMeta($meta, 'vc-url');
                            if ($vc_url != '') {
                                echo '<dt>' . __('Video Conference Link', 'rrze-calendar') . ':</dt><dd><p itemprop="location" itemscope itemtype="http://schema.org/VirtualLocation"><a itemprop="url" href="'. $vc_url . '">' . $vc_url . '</a></p></dd>';
                            }

                            // Prices + Tickets
                            if ($prices != '') {
                                echo '<dt>' . __('Prices', 'rrze-calendar') . ':</dt><dd><p itemprop="offers" itemscope itemtype="https://schema.org/Offer">' . wpautop($prices) . '</p></dd>';
                            }

                            // Registration
                            if ($registrationUrl != '') {
                                echo '<dt>' . __('Registration', 'rrze-calendar') . ':</dt><dd><a href="'. $registrationUrl . '">' . $registrationUrl . '</a></dd>';
                            }

                            //Downloads
                            if ($downloads != '') {
                                echo '<dt>' . __('Downloads', 'rrze-calendar') . ':</dt><dd><ul class="downloads"><li>';
                                $downloadList = [];
                                foreach ($downloads as $attachmentID => $attachmentURL ) {
                                    $caption = wp_get_attachment_caption($attachmentID);
                                    if ($caption == '') {
                                        $caption = basename(get_attached_file($attachmentID));
                                    }
                                    $downloadList[] = '<a href="' . $attachmentURL . '">' . $caption . '</a>';
                                }
                                echo  implode('</li><li>', $downloadList);
                                echo  '</li></ul></dd>';
                            }

                            // Categories
                            if (!is_wp_error($categoryObjects) && !empty($categoryObjects)) {
                                $categories = [];
                                foreach ($categoryObjects as $categoryObject) {
                                    $categories[] = '<a href="' . get_term_link($categoryObject->term_id) . '">' . $categoryObject->name . '</a>';
                                }
                                echo '<dt>' . __('Event Categories', 'rrze-calendar') . ':</dt><dd>' . implode(', ', $categories) . '</dd>';
                            }

                            ?>
                        </aside>
                    <?php } ?>
                </main>
            </div>
        </div>
    </div>
<?php endwhile;

get_footer();
