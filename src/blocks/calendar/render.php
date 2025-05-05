<?php

// Compatibility with Shortcode
$attributes = [];

echo wp_kses_post(\RRZE\Calendar\Shortcodes\Calendar::shortcode($attributes));