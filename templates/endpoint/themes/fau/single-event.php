<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\Shortcodes\Shortcode;

$data = !empty($data) && class_exists(__NAMESPACE__ . '\Events') ? Events::getSingleData($data) : [];
$hasEvents = !empty($data);
$endpointUrl = class_exists(__NAMESPACE__ . '\Endpoint') ? Endpoint::endpointUrl() : '';
$endpointTitle = class_exists(__NAMESPACE__ . '\Endpoint') ? Endpoint::endpointTitle() : '';

$post = get_post();
$post->post_title = $endpointTitle;
$currentTheme = wp_get_theme();		
$vers = $currentTheme->get( 'Version' );
 
get_header(); 
  if (version_compare($vers, "2.3", '<')) {  
    $breadcrumb = '';
    $breadcrumb .= '<nav aria-label="' . __('Breadcrumb', 'fau') . '" class="breadcrumbs">';
    $breadcrumb .= '<ol class="breadcrumblist" itemscope="" itemtype="https://schema.org/BreadcrumbList">';
    $breadcrumb .= '<li itemprop="itemListElement" itemscope="" itemtype="https://schema.org/ListItem"><a itemprop="item" href="' . site_url('/') . '"><span itemprop="name">' . __('Startseite', 'fau') . '</span></a><meta itemprop="position" content="1"></li>';
    $breadcrumb .= '</ol>';
    $breadcrumb .= '</nav>';
?>
<section id="hero" class="hero-small">
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <?php echo $breadcrumb; ?>
            </div>
        </div>
       <div class="row" aria-hidden="true" role="presentation">
            <div class="col-xs-12">
                <p class="presentationtitle"><?php echo $endpointTitle; ?></p>
            </div>
        </div>
    </div>
</section>
 <?php } ?>
	<div id="content">
		<div class="content-container">
		    <div class="content-row">
			    <main<?php echo fau_get_page_langcode($post->ID);?>>
				<h1 id="maintop" class="screen-reader-text"><?php echo $endpointTitle; ?></h1>
                
                
                    <div class="rrze-calendar" itemscope itemtype="http://schema.org/Event">
                        <?php if ($hasEvents) :
                            Shortcode::singleEventOutput($data);
                        else :
                            echo '<p>', __('Event not found.', 'rrze-calendar'), '</p>';
                        endif; ?>
                    </div>

                </main>
            <</div>
		</div>
	</div>

<?php get_footer();
