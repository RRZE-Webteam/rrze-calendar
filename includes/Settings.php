<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

class Settings {
    
    public static function optionsUrl($atts = array()) {
        $atts = array_merge(
            array(
                'page' => 'rrze-calendar'
            ), $atts
        );

        if (isset($atts['action'])) {
            switch ($atts['action']) {
                case 'update':
                case 'activate':
                case 'deactivate':
                case 'delete':
                case 'delete-category':
                case 'delete-tag':
                    $atts['_wpnonce'] = wp_create_nonce($atts['action']);
                    break;
                default:
                    break;
            }
        }

        return add_query_arg($atts, get_admin_url(NULL, 'admin.php'));
    }    
}