<?php

// Compatibility with Shortcode
$attributes = [];

echo wp_kses_post(\RRZE\Calendar\Shortcodes\Events::shortcode($attributes));