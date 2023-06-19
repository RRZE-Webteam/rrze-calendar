<?php
/**
 * The main template file.
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
 */

if (isset($_GET['format']) && $_GET['format'] == 'embedded') {
    get_template_part('template-parts/index', 'embedded');
    return;
}
if ( is_active_sidebar( 'news-sidebar' ) ) { 
    fau_use_sidebar(true);    
}
get_header();

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
                    //echo do_shortcode('[rrze-events]');
                    echo \RRZE\Calendar\Shortcodes\Events::shortcode([]);
                    ?>

			    </main>
		    </div>    
	    </div>
	
    </div>
<?php 
get_footer(); 

