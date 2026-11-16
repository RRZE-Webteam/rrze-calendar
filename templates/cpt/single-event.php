<?php

/**
 * The template for displaying a single post.
 *
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
 */

use RRZE\Calendar\CPT\CalendarEvent;
use RRZE\Calendar\Templates;

wp_enqueue_style('rrze-calendar-sc-events');
wp_enqueue_style( 'dashicons' );

get_header();

while (have_posts()) : the_post(); ?>

    <div id="content">
        <div class="content-container">
            <div class="content-row">
                <main>
                    <?php
                    $postId = get_the_ID();
                    $data = CalendarEvent::getEventData($postId);
                    $showTitle = true;
                    include Templates::getCptSingleEventContentTpl();
                    ?>
                </main>
            </div>
        </div>
    </div>
<?php endwhile;

get_footer();
