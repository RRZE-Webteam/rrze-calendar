<?php

class RRZE_Calendar_Events_List_Table extends WP_List_Table {

    private $list_data;

    public function __construct($list_data = array()) {
        global $status, $page;

        $this->list_data = $list_data;

        parent::__construct(array(
            'singular' => 'event',
            'plural' => 'events',
            'ajax' => FALSE
        ));
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {               
            case 'summary':
                $item[$column_name] = !empty($item[$column_name]) ? sprintf('<a href="%1$s">%2$s</a>', esc_attr(RRZE_Calendar::endpoint_url($item['slug'])), $item[$column_name]) : '';
                break;
            case 'description':
            case 'location':
                $item[$column_name] = !empty($item[$column_name]) ? wp_trim_words($item[$column_name], 10) : '';
                break;
            case 'allday':
            case 'recurrence_rules':
                $item[$column_name] = !empty($item[$column_name]) ? '<span class="dashicons dashicons-yes"></span>' : '';
                break;
            case 'start':
            case 'end':
                $item[$column_name] = RRZE_Calendar_Functions::get_long_time($item[$column_name]);
                break;
            default:
                $item[$column_name] = !empty($item[$column_name]) ? $item[$column_name] : '';
        }
        
        return $item[$column_name];
    }

    public function get_columns() {
        $columns = array(
            'summary' => __('Titel', 'rrze-calendar'),
            'description' => __('Beschreibung', 'rrze-calendar'),
            'location' => __('Ort', 'rrze-calendar'),
            'start' => __('Start', 'rrze-calendar'),
            'end' => __('Ende', 'rrze-calendar'),
            'allday' => __('gt.', 'rrze-calendar'),
            'recurrence_rules' => __('Wdh.', 'rrze-calendar')
        );
        return $columns;
    }

    public function get_sortable_columns() {
        $sortable_columns = array(
            'start' => array('start', FALSE),
            'end' => array('end', FALSE),
            'summary' => array('summary', FALSE),
        );
        return $sortable_columns;
    }
    
    public function sort_data($a, $b) {
        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'start';
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
        $result = strnatcmp($a[$orderby], $b[$orderby]);
        return ($order === 'asc') ? $result : -$result;
    }

    public function prepare_items() {                
        $this->_column_headers = $this->get_column_info();
        
        usort($this->list_data, array(&$this, 'sort_data'));
        
        if (isset($_GET['s']) && strlen(trim($_GET['s'])) > 0) {
            $search = trim($_GET['s']);
            foreach ($this->list_data as $key => $data) {
                $summary = mb_stripos($data['summary'], $search) === FALSE ? TRUE : FALSE;
                $description = mb_stripos($data['description'], $search) === FALSE ? TRUE : FALSE;
                $location = mb_stripos($data['location'], $search) === FALSE ? TRUE : FALSE;
                
                if ($summary && $description && $location) {
                    unset($this->list_data[$key]);
                }
            }
        }        

        $this->process_bulk_action();

        $per_page = $this->get_items_per_page('feed_per_page', 20);
        $current_page = $this->get_pagenum();
        $total_items = count($this->list_data);

        $this->items = array_slice($this->list_data, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args(array(
            'total_items' => $total_items, // Total number of items
            'per_page' => $per_page, // How many items to show on a page
            'total_pages' => ceil($total_items / $per_page)   // Total number of pages
        ));
    }

}
