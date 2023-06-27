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

wp_enqueue_style('rrze-calendar-sc-events');
wp_enqueue_style( 'dashicons' );

get_header();

$id = get_the_ID();
$data = CalendarEvent::getEventData($id);

while (have_posts()) : the_post(); ?>

    <div id="content">
        <div class="content-container">
            <div class="content-row">
                <main>
                    <article  class="rrze-event" itemscope itemtype="https://schema.org/Event">
                        <h1 id="maintop" class="mobiletitle" itemprop="name"><?php the_title(); ?></h1>
                        <?php
                        // Thumbnail
                        if (has_post_thumbnail() && !post_password_required()) {
                            the_post_thumbnail('medium');
                        } ?>

                        <?php CalendarEvent::displayEventMain($data); ?>

                    </article>

                    <?php CalendarEvent::displayEventDetails($data); ?>

                </main>
            </div>
        </div>
    </div>
<?php endwhile;

get_footer();
