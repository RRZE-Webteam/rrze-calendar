<?php

// Compatibility with Shortcode
$attributes['count'] = $attributes['numEvents'];
$attributes['categories'] = $attributes['selectedCategories'];
$attributes['tags'] = $attributes['selectedTags'];
$attributes['page_link'] = $attributes['pageLink'];
$attributes['page_link_label'] = $attributes['pageLink'];

//var_dump( $attributes );
echo wp_kses_post(\RRZE\Calendar\Shortcodes\Events::shortcode($attributes));