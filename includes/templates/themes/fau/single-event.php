<?php

defined('ABSPATH') || exit;

use RRZE\Calendar\Util;

if(function_exists('fau_initoptions')) {
    $options = fau_initoptions();
} else {
    $options = array();
}

$event = &$events_data;
$bgcolorclass = '';
$inline = '';
                
if (isset($event->category)) {
    // Color
    $bgcolorclass = !empty($event->category->color) ? $event->category->color : '';
    $inline = !empty($event->category->color) ? sprintf('style="background-color:%1$s; color:%2$s"', $event->category->color, Util::getContrastYIQ($event->category->color)) : '';
}
 $currentTheme = wp_get_theme();		
$vers = $currentTheme->get( 'Version' );
 


get_header(); 
  if (version_compare($vers, "2.3", '<')) {  
    $breadcrumb = '';
    $breadcrumb .= '<nav aria-label="'.__('Breadcrumb','fau').'" class="breadcrumbs">';    
    $breadcrumb .= '<ol class="breadcrumblist" itemscope="" itemtype="https://schema.org/BreadcrumbList">';
    $breadcrumb .= '<li itemprop="itemListElement" itemscope="" itemtype="https://schema.org/ListItem"><a itemprop="item" href="'. site_url('/') .'"><span itemprop="name">'.__('Startseite','fau').'</span></a><meta itemprop="position" content="1"></li>';
    $breadcrumb .= '<li itemprop="itemListElement" itemscope="" itemtype="https://schema.org/ListItem"><a itemprop="item" href="' . $calendar_endpoint_url . '"><span itemprop="name">' . $calendar_endpoint_name . '</span></a><meta itemprop="position" content="2"></li>';
    $breadcrumb .= '</ol>';
    $breadcrumb .= '</nav>'; 
?>
    <section id="hero" class="hero-small">
        <div class="container hero-content">
            <div class="row">
                <div class="col-xs-12">
                    <?php echo $breadcrumb; ?>
                </div>
            </div>
            <div class="row" aria-hidden="true" role="presentation">
                <div class="col-xs-12">
                    <p class="presentationtitle"><?php echo $event->summary; ?></p>
                </div>
            </div>
        </div>
    </section>

  <?php } ?>
    <div id="content">
        <div class="content-container">
            <div class="content-row">
                <div class="col-xs-12">
                    <main>
			<div class="rrze-calendar" itemscope itemtype="http://schema.org/Event">	
			<h1 class="screen-reader-text" itemprop="name"><?php echo $event->summary; ?></h1>    

			    
			    <div class="event-detail-item">
				<div class="event-date <?php echo $bgcolorclass; ?>" <?php echo $inline; ?>>
				    <span class="event-date-month"><?php echo $event->start_month; ?></span>
				    <span class="event-date-day"><?php echo $event->start_day; ?></span>
				</div>
				<div class="event-info event-id-<?php echo $event->id ?> <?php if ($event->allday) echo 'event-allday'; ?>">
				    <meta itemprop="startDate" content="<?php echo date_i18n('c', strtotime($event->start)); ?>">
				    <meta itemprop="endDate" content="<?php echo date_i18n('c', strtotime($event->end)); ?>">
				    
				    <?php if ($event->allday) : ?>
					<div class="event-time event-allday">
					    <?php _e('Ganztägig', 'rrze-calendar'); ?>
					</div>
				    <?php endif; ?>
				    <?php if ($event->allday && !$event->multiday && !$event->recurrence_rules) : ?>
					<div class="event-time">
					    <?php echo esc_html($event->long_start_date) ?>
					</div>
				    <?php endif; ?>                                
				    <?php if ($event->allday && $event->multiday) : ?>
					<div class="event-time">
					    <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_start_date, $event->long_end_date)) ?>
					</div>            
				    <?php elseif (!$event->allday && $event->multiday) : ?>
					<div class="event-time">
					    <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $event->long_start_date, $event->short_start_time, $event->long_end_date, $event->short_end_time)) ?>
					</div>
				    <?php elseif (!$event->allday &&  $event->recurrence_rules): ?>
					<div class="event-time">
					    <?php echo esc_html(sprintf(__('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
					</div>                
				    <?php elseif (!$event->allday &&  !$event->recurrence_rules): ?>
					<div class="event-time">
					    <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s Uhr', 'rrze-calendar'), $event->long_start_date, $event->short_start_time, $event->short_end_time)) ?>
					</div>            
				    <?php endif; ?>
				    <?php if ($event->recurrence_rules) : ?>
					<div class="event-rrules">
					    <?php echo ucfirst($event->rrules_human_readable); ?>
					</div>                
				    <?php endif; ?>
				    <div class="event-location" itemprop="location"><?php echo $event->location ? $event->location : '&nbsp;'; ?></div>
				</div>
			    </div>
			    <div>
				<?php 
                $content = make_clickable(nl2br(htmlspecialchars_decode($event->description)));
                $content = apply_filters('the_content', $content);
                $content = str_replace(']]>', ']]&gt;', $content);
                echo $content;
                ?>
			    </div>                        
			    <div class="events-more-links">
				<a class="events-more" href="<?php echo $event->subscribe_url; ?>"><?php _e('Abonnement', 'rrze-calendar'); ?></a>
			    </div>       
			</div>      
			    
                    </main>
                </div>
            </div>
        </div>
    </div>

<?php get_footer(); ?>
