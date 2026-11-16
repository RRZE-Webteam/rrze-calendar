<?php
/**
 * The main template file.
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
 */

use RRZE\Calendar\Templates;

if (isset($_GET['format']) && $_GET['format'] == 'embedded') {
    get_template_part('template-parts/index', 'embedded');
    return;
}
if ( is_active_sidebar( 'news-sidebar' ) && function_exists('fau_use_sidebar') ) {
    fau_use_sidebar(true);
}
get_header();

?>

    <div id="content">
	    <div class="content-container">
		    <div class="post-row">
			    <main class="entry-content">

                    <?php
                    $showTitle = true;
                    include Templates::getCptArchiveEventContentTpl();
                    ?>

			    </main>
		    </div>
	    </div>

    </div>
<?php
get_footer();
