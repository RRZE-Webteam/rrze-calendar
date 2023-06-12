<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

$data = !empty($data) && class_exists(__NAMESPACE__ . '\Events') ? Events::getListData($data) : [];
$hasEvents = !empty($data);
$endpointUrl = class_exists(__NAMESPACE__ . '\Endpoint') ? Endpoint::endpointUrl() : '';
$endpointTitle = class_exists(__NAMESPACE__ . '\Endpoint') ? Endpoint::endpointTitle() : '';

$post = get_post();
$post->post_title = $endpointTitle;
$currentTheme = wp_get_theme();
$vers = $currentTheme->get('Version');

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
        <div class="container hero-content">
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
            <main<?php echo fau_get_page_langcode($post->ID); ?>>
                <h1 id="maintop" class="screen-reader-text"><?php echo $endpointTitle; ?></h1>
                <div class="rrze-calendar events-list">
                    <?php if ($hasEvents) : ?>
                        <ul>
                            <?php foreach ($data as $event) : ?>
                                <li><?php singleEventOutput($event, $endpointUrl); ?></li>
                            <?php endforeach ?>
                        </ul>
                    <?php else : ?>
                        <p><?php _e('No upcoming events.', 'rrze-calendar'); ?></p>
                    <?php endif; ?>
                </div>

                </main>
                << /div>
        </div>
    </div>

    <?php get_footer();

    function singleEventOutput($data, $endpointUrl)
    {
        $data = (object) $data;

        // Date & time format
        $dateFormat = get_option('date_format');
        $timeFormat = get_option('time_format');

        // Color
        $inline = !empty($data->cat_bgcolor) ? sprintf(' style="background-color:%1$s; color:%2$s"', $data->cat_bgcolor, $data->cat_color) : '';

        // Date/time
        $currentTime = current_time('timestamp');
        $isMultiday = !empty($data->is_multiday) ? true : false;
        $isAllday = !empty($data->is_allday) ? true : false;
        if ($isMultiday && $isAllday) {
            $startDate = strtotime($data->start_date);
            $endDate = strtotime($data->end_date);
        } elseif ($isMultiday && !$isAllday) {
            $startDate = strtotime($data->start_date);
            $endDate = strtotime($data->end_date);
        } elseif (!$isMultiday && !$isAllday) {
            $startDate = strtotime($data->start_date);
            $endDate = strtotime($data->end_date);
        } elseif (!$isMultiday && $isAllday) {
            $startDate = strtotime($data->start_date);
            $endDate = $startDate;
        }
    ?>
        <div class="rrze-calendar" itemscope itemtype="http://schema.org/Event">
            <h1 class="screen-reader-text" itemprop="name"><?php echo $data->title; ?></h1>
            <div class="event-detail-item">
                <div class="event-date" <?php echo $inline; ?>>
                    <span class="event-date-month"><?php echo date('M', $startDate < $currentTime ? $currentTime : $startDate); ?></span>
                    <span class="event-date-day"><?php echo date('d', $startDate < $currentTime ? $currentTime : $startDate); ?></span>
                </div>
                <div class="event-info event-id-<?php echo $data->post_id ?> <?php if ($isAllday) echo 'event-allday'; ?>">
                    <meta itemprop="startDate" content="<?php echo date('c', $startDate); ?>">
                    <meta itemprop="endDate" content="<?php echo date('c', $endDate); ?>">
                    <div class="event-title" itemprop="name">
                        <a itemprop="url" href="<?php echo $endpointUrl . '/' . $data->slug; ?>"><?php echo esc_html($data->title); ?></a>
                    </div>
                    <?php if ($isAllday) : ?>
                        <div class="event-time event-allday">
                            <?php _e('All Day', 'rrze-calendar'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($isMultiday && $isAllday) : ?>
                        <div class="event-time">
                            <?php printf(
                                /* translators: 1: Start date, 2: End date. */
                                __('%1$s - %2$s', 'rrze-calendar'),
                                date($dateFormat, $startDate),
                                date($dateFormat, $endDate)
                            ); ?>
                        </div>
                    <?php elseif ($isMultiday && !$isAllday) : ?>
                        <div class="event-time">
                            <?php printf(
                                /* translators: 1: Start date, 2: Start time, 3: End date, 4: End time. */
                                __('%1$s %2$s - %3$s %4$s', 'rrze-calendar'),
                                date($dateFormat, $startDate),
                                date($timeFormat, $startDate),
                                date($dateFormat, $endDate),
                                date($timeFormat, $endDate)
                            ); ?>
                        </div>
                    <?php elseif (!$isMultiday && !$isAllday) : ?>
                        <div class="event-time">
                            <?php printf(
                                /* translators: 1: Start date, 2: Start time. 3: End time. */
                                __('%1$s %2$s - %3$s', 'rrze-calendar'),
                                date($dateFormat, $startDate),
                                date($timeFormat, $startDate),
                                date($timeFormat, $endDate)
                            ); ?>
                        </div>
                    <?php elseif (!$isMultiday && $isAllday) : ?>
                        <div class="event-time">
                            <?php echo date($dateFormat, $startDate); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($data->readable_rrule)) : ?>
                        <div class="event-time event-rrule">
                            <?php echo ucfirst($data->readable_rrule); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($data->location)) : ?>
                        <div class="event-location" itemprop="location">
                            <?php echo $data->location ? nl2br($data->location) : '&nbsp;'; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php
    }
