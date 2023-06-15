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

$posttype = get_post_type();
$screenreadertitle = '';
$herotype = get_theme_mod('advanced_header_template');
if (($posttype == 'post') && (is_archive())) {    
    if (is_category()) {
        $screenreadertitle = single_cat_title("", false);
    } else {
        $screenreadertitle = get_the_archive_title();
    }

} else {	
    $screenreadertitle = __('Index','fau');
}
?>

    <div id="content">
	    <div class="content-container">
		    <div class="post-row">
			    <main class="entry-content">

                    <?php if (empty($herotype)) {   ?>
                        <h1 id="maintop"  class="screen-reader-text"><?php echo $screenreadertitle; ?></h1>
                     <?php } else { ?>
                        <h1 id="maintop" ><?php echo $screenreadertitle; ?></h1>
                    <?php } ?>

                    [rrze-events]

			    </main>
		    </div>    
	    </div>
	
    </div>
<?php 
get_footer(); 

