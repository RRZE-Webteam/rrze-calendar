<?php

/**
 * Events archive content partial (shared by the classic archive template and the block template).
 *
 * Available variables:
 * @var bool $showTitle Whether to output the title (false in block themes, where core/query-title is used)
 */

use RRZE\Calendar\CPT\CalendarEvent;
use RRZE\Calendar\Shortcodes\Events;

defined('ABSPATH') || exit;

$showTitle = $showTitle ?? true;

if ($showTitle) { ?>
    <h1 id="maintop"><?php _e('Events', 'rrze-calendar'); ?></h1>
<?php }

$atts = [
    'number' => 99,
];
$category = get_query_var(CalendarEvent::TAX_CATEGORY);
if (is_string($category) && $category !== '') {
    $atts['categories'] = sanitize_title($category);
    $atts['abonnement_link'] = '1';
}
echo Events::shortcode($atts);
