<?php

namespace RRZE\WP\Settings\Options;

class CodeEditor extends Field
{
    public $template = 'code-editor';

    public function __construct($section, $args = [])
    {
        add_action('rrze_wp_settings_before_render_settings_page', [$this, 'enqueue']);

        parent::__construct($section, $args);
    }

    public function enqueue()
    {
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');

        $settings_name = str_replace('-', '_', $this->getIdAttribute());

        wp_localize_script('jquery', $settings_name, wp_enqueue_code_editor(['type' => $this->getArg('editor_type', 'text/html')]));

        wp_add_inline_script('wp-theme-plugin-editor', 'jQuery(function($){
            if($("#'.$this->getIdAttribute().'").length > 0) {
                wp.codeEditor.initialize($("#'.$this->getIdAttribute().'"), '.$settings_name.');
            }
        });');
    }

    public function sanitize($value)
    {
        return $value;
    }
}
