<?php

namespace RRZE\Calendar\Config;

defined('ABSPATH') || exit;

/**
 * Returns the name of the option.
 * @return string Option name
 */
function getOptionName(): string
{
    return 'rrze_calendar';
}

/**
 * Returns the settings of the menu.
 * @return array Menu settings
 */
function getMenuSettings(): array
{
    return [
        'page_title'    => __('Calendar', 'rrze-calendar'),
        'menu_title'    => __('Calendar', 'rrze-calendar'),
        'capability'    => 'manage_options',
        'menu_slug'     => 'rrze-calendar',
        'title'         => __('Calendar Settings', 'rrze-calendar'),
    ];
}

/**
 * Returns the sections settings.
 * @return array Sections settings
 */
function getSections(): array
{
    return [
        [
            'id'    => 'schedule',
            'title' => __('Schedule', 'rrze-calendar'),
            'desc' => ''
        ],
        [
            'id'    => 'endpoint',
            'title' => __('Endpoint', 'rrze-calendar'),
            'desc' => ''
        ]
    ];
}

/**
 * Returns the settings fields.
 * @return array Settings fields
 */
function getFields(): array
{
    return [
        'schedule' => [
            [
                'name'    => 'recurrence',
                'label'   => __('Recurrence', 'rrze-calendar'),
                'desc'    => __('Choose the recurrence to check for new events.', 'rrze-calendar'),
                'type'    => 'select',
                'default' => 'hourly',
                'options' => [
                    'hourly'     => __('Hourly', 'rrze-calendar'),
                    'twicedaily' => __('Twice daily', 'rrze-calendar'),
                    'daily'      => __('Daily', 'rrze-calendar')
                ]
            ]
        ],
        'endpoint' => [
            [
                'name'  => 'enabled',
                'label' => __('Enable', 'rrze-calendar'),
                'desc'  => __('Enables the endpoint url', 'rrze-calendar'),
                'type'  => 'checkbox'
            ],
            [
                'name'              => 'slug',
                'label'             => __('Slug', 'rrze-calendar'),
                'desc'              => __('The slug of the endpoint.', 'rrze-calendar'),
                'type'              => 'text',
                'default'           => 'events',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            [
                'name'              => 'title',
                'label'             => __('Title', 'rrze-calendar'),
                'desc'              => __('The title of the endpoint.', 'rrze-calendar'),
                'type'              => 'text',
                'default'           => 'Events',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ]
    ];
}
