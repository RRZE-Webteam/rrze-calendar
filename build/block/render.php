<?php
// Compatibility with Shortcode

$attributes['layout'] = $attributes[ 'layout' ] ?? 'teaser';
$attributes['count'] = $attributes['numEvents'] ?? '';
$attributes['categories'] = is_array($attributes['selectedCategories']) ? implode(', ', $attributes['selectedCategories']) : '';
$attributes['tags'] = is_array($attributes['selectedTags']) ? implode(', ', $attributes['selectedTags']) : '';
$attributes['page_link'] = $attributes['pageLink'] ?? '';
$attributes['page_link_label'] = isset($attributes['pageLinkLabel']) ? esc_html($attributes['pageLinkLabel']) : '';
$attributes['include'] = $attributes['includeEvents'] ?? '';
$attributes['exclude'] = $attributes['excludeEvents'] ?? '';
$attributes['abonnement_link'] = $attributes['abonnementLink'] ? '1' : '';

switch ( $attributes['layout'] ) {
    case 'teaser':
    case 'list':
    echo \RRZE\Calendar\Shortcodes\Events::shortcode($attributes);
        break;
    case 'full':
    case '':
    default:
        echo \RRZE\Calendar\Shortcodes\Calendar::shortcode($attributes);
        break;
}