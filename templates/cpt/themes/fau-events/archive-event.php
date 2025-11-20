<?php
/**
 * The template for displaying archive pages
 */

use RRZE\Calendar\Shortcodes\Events;

if (isset($_GET['format']) && $_GET['format'] == 'embedded') {
    get_template_part('template-parts/archive', 'embedded');
    return;
}
get_header();
global $wp_query;

?>

    <div class="content-wrap">
        <div id="blog-wrap" class="blog-wrap cf">
            <div id="primary" class="site-content cf" role="main">

                <header class="archive-header">
                    <h1 class="archive-title" ><?php _e('Events', 'rrze-calendar'); ?></h1>
                </header><!-- end .archive-header -->

                <?php
                $atts = [
                    'number' => '99',
                ];
                $queryVars = $wp_query->query_vars;
                if (isset($queryVars['rrze-calendar-category']) && $queryVars['rrze-calendar-category'] != '') {
                    $atts['categories'] = sanitize_title($queryVars['rrze-calendar-category']);
                    $atts['abonnement_link'] = '1';
                }
                echo Events::shortcode($atts);
                ?>

            </div><!-- end #primary -->
            <?php get_sidebar(); ?>
        </div><!-- end .blog-wrap -->
    </div><!-- end .content-wrap -->

<?php
get_footer();
