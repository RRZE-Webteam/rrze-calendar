<?php

/* Quit */
defined('ABSPATH') || exit;

if(function_exists('fau_initoptions')) {
    $options = fau_initoptions();
} else {
    $options = array();
}

$event = &$events_data;
$bgcolorclass = '';
$inline = '';
if (isset($event->category)) {
    if (!empty($event->category->bgcol)) {
	$bgcolorclass = $event->category->bgcol;
    } elseif (!empty($event->category->color)) {
	$inline = 'style="background-color:' . $event->category->color.'"';
    }
}
					
$breadcrumb = '';
if (isset($options['breadcrumb_root'])) {
    if ($options['breadcrumb_withtitle']) {
        $breadcrumb .= '<h3 class="breadcrumb_sitetitle" role="presentation">'.get_bloginfo('title').'</h3>';
        $breadcrumb .= "\n";
    }
    $breadcrumb .= '<nav aria-labelledby="bc-title" class="breadcrumbs">'; 
    $breadcrumb .= '<h4 class="screen-reader-text" id="bc-title">'.__('Sie befinden sich hier:','fau').'</h4>';
    $breadcrumb .= '<a data-wpel-link="internal" href="' . site_url('/') . '">' . $options['breadcrumb_root'] . '</a>' . $options['breadcrumb_delimiter'];
    $breadcrumb .= '<a data-wpel-link="internal" href="' . $calendar_endpoint_url . '">' . $calendar_endpoint_name . '</a>';
}
get_header(); ?>

    <section id="hero" class="hero-small">
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                    <?php echo $breadcrumb; ?>
                </div>
            </div>
            <div class="row">
                <div class="col-xs-12">
                    <h1><?php echo $event->summary; ?></h1>
                </div>
            </div>
        </div>
    </section>

    <div id="content">
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                    <main>
			<div class="rrze-calendar" itemscope itemtype="http://schema.org/Event">			   
			    <meta itemprop="name" content="<?php echo esc_html($event->summary);?>">
			    
			    <div class="event-detail-item">
				<div class="event-date <?php echo $bgcolorclass; ?>" <?php echo $inline; ?>>
				    <span class="event-date-month"><?php echo $event->start_month_html ?></span>
				    <span class="event-date-day"><?php echo $event->start_day_html ?></span>
				</div>
				<div class="event-info event-id-<?php echo $event->id ?> <?php if ($event->allday) echo 'event-allday'; ?>">
				    <meta itemprop="startDate" content="<?php echo date_i18n( "c", $event->start ); ?>">
				    <meta itemprop="endDate" content="<?php echo date_i18n( "c", $event->end ); ?>">
				    
				    <?php if ($event->allday) : ?>
					<div class="event-time event-allday">
					    <?php _e('Ganztägig', 'rrze-calendar'); ?>
					</div>
				    <?php endif; ?>
				    <?php if ($event->allday && !$event->multiday && !$event->recurrence_rules) : ?>
					<div class="event-time">
					    <?php echo esc_html($event->long_e_start_date) ?>
					</div>
				    <?php endif; ?>                                
				    <?php if ($event->allday && $event->multiday) : ?>
					<div class="event-time">
					    <?php echo esc_html(sprintf(__('%1$s bis %2$s', 'rrze-calendar'), $event->long_e_start_date, $event->long_e_end_date)) ?>
					</div>            
				    <?php elseif (!$event->allday && $event->multiday) : ?>
					<div class="event-time">
					    <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s %4$s Uhr', 'rrze-calendar'), $event->long_e_start_date, $event->short_e_start_time, $event->long_e_end_date, $event->short_e_end_time)) ?>
					</div>
				    <?php elseif (!$event->allday &&  $event->recurrence_rules): ?>
					<div class="event-time">
					    <?php echo esc_html(sprintf(__('%1$s Uhr bis %2$s Uhr', 'rrze-calendar'), $event->short_start_time, $event->short_end_time)) ?>
					</div>                
				    <?php elseif (!$event->allday &&  !$event->recurrence_rules): ?>
					<div class="event-time">
					    <?php echo esc_html(sprintf(__('%1$s %2$s Uhr bis %3$s Uhr', 'rrze-calendar'), $event->long_e_start_date, $event->short_start_time, $event->short_end_time)) ?>
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
				$content =  make_clickable(nl2br($event->description));; 
				$content = apply_filters( 'the_content', $content );
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
