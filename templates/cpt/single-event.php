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
                    <article class="rrze-event" itemscope itemtype="https://schema.org/Event">

                        <header class="entry-header">
                            <?php the_title('<h1 class="entry-title" itemprop="name">', '</h1>'); ?>
                        </header><!-- .entry-header -->

                        <div class="rrze-event-main">

                            <?php if (has_post_thumbnail() && !post_password_required()) { ?>
                                <figure class="post-thumbnail wp-caption alignright">
                                    <?php the_post_thumbnail('medium'); ?>
                                    <figcaption class="wp-caption-text"><?php echo get_the_post_thumbnail_caption(); ?></figcaption>
                                </figure>
                            <?php } ?>

                            <?php CalendarEvent::displayEventMain($data); ?>

                        </div>

                        <?php CalendarEvent::displayEventDetails($data); ?>

                    </article>

                </main>
            </div>
        </div>
    </div>
<?php endwhile;

get_footer();
