<?php

class RRZE_Calendar_Tags_List_Table extends WP_List_Table {

    public $callback_args;

    public function __construct() {

        parent::__construct(array(
            'screen' => 'edit-tag',
            'singular' => 'tag',            
            'plural' => 'tags',
            'ajax' => true
        ));
    }

    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->items = RRZE_Calendar::get_tags();

        $this->set_pagination_args(array(
            'total_items' => count($this->items),
            'per_page' => count($this->items),
        ));
    }

    public function get_columns() {

        $columns = array(
            'name' => __('Name', 'rrze-calendar'),           
            'description' => __('Beschreibung', 'rrze-calendar'),
            'slug' => __('Titelform', 'rrze-calendar'),
            'feeds' => __('Feeds', 'rrze-calendar'),
        );

        return $columns;
    }

    public function column_default($tag, $column_name) {
        
    }

    public function column_name($tag) {
        $output = '<strong><a href="' . esc_url(RRZE_Calendar::options_general_url(array('action' => 'edit-tag', 'tag-id' => $tag->term_id, 'tab' => 'tags'))) . '">' . esc_html($tag->name) . '</a></strong>';

        $actions = array();
        $actions['edit edit-tag'] = sprintf('<a href="%1$s">' . __('Bearbeiten', 'rrze-calendar') . '</a>', RRZE_Calendar::options_general_url(array('action' => 'edit-tag', 'tag-id' => $tag->term_id, 'tab' => 'tags')));
        
        if (empty($tag->feed_ids)) {
            $actions['delete delete-tag'] = sprintf('<a href="%1$s">' . __('Löschen', 'rrze-calendar') . '</a>', RRZE_Calendar::options_general_url(array('action' => 'delete-tag', 'tag-id' => $tag->term_id, 'tab' => 'tags')));
        }
        $output .= $this->row_actions($actions, FALSE);

        return $output;
    }

    public function column_color($tag) {
        $color = get_term_meta($tag->term_id, 'color', TRUE);

        if(!empty($color)) {
            return '<div style="height: 20px; width: 30px; background-color: ' . $color . ';"></div>';
        }

        return '';        
    }
    
    public function column_slug($tag) {
        return esc_html($tag->slug);
    }
    
    public function column_description($tag) {
        return esc_html($tag->description);
    }

    public function column_feeds($tag) {
        return count($tag->feed_ids);
    }
    
    public function single_row($tag) {
        static $row_class = '';
        $row_class = ($row_class == '' ? ' class="alternate"' : '');

        echo '<tr id="tag-' . $tag->term_id . '"' . $row_class . '>';
        echo $this->single_row_columns($tag);
        echo '</tr>';
    }

}

