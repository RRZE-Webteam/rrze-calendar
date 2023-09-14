<?php
/**
 * The template for displaying archive pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package RRZE_2019
 */

use RRZE\Calendar\Shortcodes\Events;

if (isset($_GET['format']) && $_GET['format'] == 'embedded') {
    get_template_part('template-parts/archive', 'embedded');
    return;
}
get_header();
global $wp_query;

?>

    <?php if ( !is_front_page() ) { ?>
        <div id="sidebar" class="sidebar">
            <?php get_sidebar('page'); ?>
        </div><!-- .sidebar -->
    <?php } ?>

    <div id="primary" class="content-area">
		<main id="main" class="site-main">

			<header class="page-header">
                <h1 class="page-title" ><?php _e('Events', 'rrze-calendar'); ?></h1>
			</header><!-- .page-header -->

			<?php
            $atts = [];
            $queryVars = $wp_query->query_vars;
            if (isset($queryVars['rrze-calendar-category']) && $queryVars['rrze-calendar-category'] != '') {
                $atts['categories'] = sanitize_title($queryVars['rrze-calendar-category']);
                $atts['abonnement_link'] = '1';
                $atts['number'] = '99';
            }
            echo Events::shortcode($atts);
            ?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_footer();
