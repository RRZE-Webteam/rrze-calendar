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

                        <div class="rrze-event-main">
                            <?php
                            // Thumbnail
                            if (has_post_thumbnail() && !post_password_required()) {
                                $post_thumbnail_id = get_post_thumbnail_id();
                                if ($post_thumbnail_id && !metadata_exists('post', $post->ID, 'vidpod_url')) {
                                    $value = get_post_meta( $post->ID, '_hide_featured_image', true );
                                    $imgdata = fau_get_image_attributs($post_thumbnail_id);
                                    $full_image_attributes = wp_get_attachment_image_src($post_thumbnail_id, 'full');
                                    if ($full_image_attributes) {
                                        $altattr = trim(strip_tags($imgdata['alt']));
                                        if ((fau_empty($altattr)) && (get_theme_mod("advanced_display_postthumb_alt-from-desc"))) {
                                            $altattr = trim(strip_tags($imgdata['description']));
                                        }
                                        if (fau_empty($altattr)) {
                                            // falls es noch immer leer ist, geben wir an, dass dieses Bild ein Symbolbild ist und
                                            // der Klick das Bild größer macht.
                                            $altattr = __('Symbolbild zum Artikel. Der Link öffnet das Bild in einer großen Anzeige.', 'fau');
                                        }
                                        if ($value) {
                                            $post_thumbnail_id = get_post_thumbnail_id($post->ID);
                                            $post_thumbnail_url = wp_get_attachment_image_src($post_thumbnail_id, 'full')[0];
                                            echo '<style type="text/css">.post-image { display: none; }</style>';
                                            echo '<img src="' . esc_url($post_thumbnail_url) . '" class="post-image" />';
                                        }
                                        echo '<div class="post-image">';
                                        echo '<figure>';
                                        echo '<a class="lightbox" href="' . fau_esc_url($full_image_attributes[0]) . '">';
                                        echo fau_get_image_htmlcode($post_thumbnail_id, 'rwd-480-3-2', $altattr);
                                        echo '</a>';

                                        $bildunterschrift = get_post_meta($post->ID, 'fauval_overwrite_thumbdesc', true);
                                        if (isset($bildunterschrift) && strlen($bildunterschrift) > 1) {
                                            $imgdata['fauval_overwrite_thumbdesc'] = $bildunterschrift;
                                        }
                                        echo fau_get_image_figcaption($imgdata);
                                        echo '</figure>';
                                        echo '</div>';
                                    }
                                }
                            } ?>

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
