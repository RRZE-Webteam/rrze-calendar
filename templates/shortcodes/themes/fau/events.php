<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\Shortcodes\Shortcode;

$count = 1;
$data = !empty($data) && class_exists(__NAMESPACE__ . '\Events') ? Events::getListData($data) : [];
$hasEvents = !empty($data);
$endpointUrl = class_exists(__NAMESPACE__ . '\Endpoint') ? Endpoint::endpointUrl() : '';
?>
<div class="rrze-calendar events-list">
    <?php if ($hasEvents) : ?>
        <ul>
            <?php foreach ($data as $event) : ?>
                <?php if ($count > $limit) break; ?>
                <li><?php Shortcode::singleEventListOutput($event, $endpointUrl); ?></li>
                <?php $count++; ?>
            <?php endforeach ?>
        </ul>
    <?php else : ?>
        <p><?php _e('No upcoming events.', 'rrze-calendar'); ?></p>
    <?php endif; ?>
</div>