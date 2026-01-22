<?php
/**
 * The main template file.
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
 */

use RRZE\Calendar\Shortcodes\Events;

if (isset($_GET['format']) && $_GET['format'] == 'embedded') {
    get_template_part('template-parts/index', 'embedded');
    return;
}
if ( is_active_sidebar( 'news-sidebar' ) ) {
    fau_use_sidebar(true);
}
get_header();
global $wp_query;

?>

    <div id="content">
        <div class="content-container">
            <div class="post-row">
                <main class="entry-content">

                    <?php if (empty($herotype)) {   ?>
                        <h1 id="maintop"  class="screen-reader-text"><?php _e('Events', 'rrze-calendar'); ?></h1>
                    <?php } else { ?>
                        <h1 id="maintop" ><?php _e('Events', 'rrze-calendar');; ?></h1>
                    <?php }
                    $atts = [];
                    $queryVars = $wp_query->query_vars;
                    if (isset($queryVars['rrze-calendar-category']) && $queryVars['rrze-calendar-category'] != '') {
                        $atts['categories'] = sanitize_title($queryVars['rrze-calendar-category']);
                        $atts['abonnement_link'] = '1';
                    }
                    echo Events::shortcode($atts);
                    ?>

                    <?php the_posts_pagination(); ?>

                </main>
            </div>
        </div>

    </div>
<?php
get_footer(); 

