<?php

// Compatibility with Shortcode
$attributes['count'] = $attributes['numEvents'];
$attributes['categories'] = implode(', ', $attributes['selectedCategories']);
$attributes['tags'] = implode(', ', $attributes['selectedTags']);
$attributes['page_link'] = $attributes['pageLink'];
$attributes['page_link_label'] = esc_html($attributes['pageLinkLabel']);
$attributes['start'] = $attributes['startDate'];
$attributes['end'] = $attributes['endDate'];
$attributes['include'] = $attributes['includeEvents'];
$attributes['exclude'] = $attributes['excludeEvents'];

echo wp_kses_post(\RRZE\Calendar\Shortcodes\Events::shortcode($attributes));