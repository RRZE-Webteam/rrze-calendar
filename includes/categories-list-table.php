<?php

class RRZE_Calendar_Categories_List_Table extends WP_List_Table {

    public $callback_args;

    public function __construct() {

        parent::__construct(array(
            'singular' => 'category',
            'plural' => 'categories',
            'ajax' => true
        ));
    }

    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->items = RRZE_Calendar::get_categories();

        $this->set_pagination_args(array(
            'total_items' => count($this->items),
            'per_page' => count($this->items),
        ));
    }

    public function get_columns() {

        $columns = array(
            'name' => __('Name', 'rrze-calendar'),
            'color' => __('Farbe', 'rrze-calendar'),
            'description' => __('Beschreibung', 'rrze-calendar'),
            'slug' => __('Titelform', 'rrze-calendar'),
            'feeds' => __('Feeds', 'rrze-calendar'),
        );

        return $columns;
    }

    public function column_default($category, $column_name) {

    }

    public function column_name($category) {
        $output = '<strong><a href="' . esc_url(RRZE_Calendar::options_url(array('page' => 'rrze-calendar-categories', 'action' => 'edit', 'category-id' => $category->term_id))) . '">' . esc_html($category->name) . '</a></strong>';

        $actions = array();
        $actions['edit'] = sprintf('<a href="%1$s">' . __('Bearbeiten', 'rrze-calendar') . '</a>', RRZE_Calendar::options_url(array('page' => 'rrze-calendar-categories', 'action' => 'edit', 'category-id' => $category->term_id)));
        $actions['delete'] = sprintf('<a href="%1$s">' . __('Löschen', 'rrze-calendar') . '</a>', RRZE_Calendar::options_url(array('page' => 'rrze-calendar-categories', 'action' => 'delete', 'category-id' => $category->term_id)));
        $output .= $this->row_actions($actions, FALSE);

        return $output;
    }

    public function column_color($category) {
        $color = get_term_meta($category->term_id, 'color', TRUE);

        if(!empty($color)) {
            return '<div style="height: 20px; width: 30px; background-color: ' . $color . ';"></div>';
        }

        return '';
    }

    public function column_slug($category) {
        return esc_html($category->slug);
    }

    public function column_description($category) {
        return esc_html($category->description);
    }

    public function column_feeds($category) {
        return count($category->feed_ids);
    }

    public function single_row($category) {
        static $row_class = '';
        $row_class = ($row_class == '' ? ' class="alternate"' : '');

        echo '<tr id="category-' . $category->term_id . '"' . $row_class . '>';
        echo $this->single_row_columns($category);
        echo '</tr>';
    }

}
