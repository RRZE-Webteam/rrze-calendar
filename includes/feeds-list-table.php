<?php

class RRZE_Calendar_Feeds_List_Table extends WP_List_Table {

    protected $rrze_calendar;
    private $list_data;

    public function __construct($list_data = array()) {
        $this->rrze_calendar = RRZE_Calendar::instance();
        $this->list_data = $list_data;

        parent::__construct(array(
            'singular' => 'feed',
            'plural' => 'feeds',
            'ajax' => FALSE
        ));
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {               
            case 'title':
                $item[$column_name] = !empty($item[$column_name]) ? wp_trim_words($item[$column_name], 10) : '';
                break;
            case 'url':
                $item[$column_name] = !empty($item[$column_name]) ? sprintf('<a href="%1$s">%1$s</a>', $item[$column_name]) : '';
                break;
            case 'category':
                if (!empty($item[$column_name]) && is_array($item[$column_name])) {
                    
                    $edit_link = RRZE_Calendar::options_url(array('page' => 'rrze-calendar-categories', 'action' => 'edit-category', 'category-id' => $item[$column_name]['id']));
                    $category = sprintf('<a href="%1$s" title="%2$s">%2$s</a><br>%3$s', $edit_link, $item[$column_name]['name'], $item[$column_name]['slug']);                    
                    $item[$column_name] = $category;
                } else {
                    $item[$column_name] = '';
                }
                break;
            case 'tags':
                if (!empty($item[$column_name]) && is_array($item[$column_name])) {
                    $tags = array();
                    foreach ($item[$column_name] as $tag) {
                        $edit_link = RRZE_Calendar::options_url(array('page' => 'rrze-calendar-tags', 'action' => 'edit-tag', 'tag-id' => $tag['id']));
                        $tags[] = sprintf('<a href="%1$s" title="%2$s">%3$s</a>', $edit_link, $tag['slug'], $tag['name']);
                    }
                    $item[$column_name] = implode(', ', $tags);
                } else {
                    $item[$column_name] = '';
                }
                break;                
            case 'events_count';
                break;
            default:
                $item[$column_name] = !empty($item[$column_name]) ? $item[$column_name] : '';
        }
        
        return $item[$column_name];
    }

    public function single_row($item) {
        echo $item['active'] ? '<tr class="active">' : '<tr class="inactive">';
        $this->single_row_columns($item);
        echo '</tr>';
    }
    
    public function column_url($item) {
        $id = $item['id'];
        // Build row actions
        if ($item['active']) {
            $actions = array(
                'update' => '<a href="' . esc_url(RRZE_Calendar::options_url(array('action' => 'update', 'feed-id' => $item['id']))) . '">' . esc_html(__('Aktualisieren', 'rrze-calendar')) . '</a>',
                'edit' => '<a href="' . esc_url(RRZE_Calendar::options_url(array('action' => 'edit', 'feed-id' => $item['id']))) . '">' . esc_html(__('Bearbeiten', 'rrze-calendar')) . '</a>',
                'deactivate' => '<a href="' . esc_url(RRZE_Calendar::options_url(array('action' => 'deactivate', 'feed-id' => $item['id']))) . '">' . esc_html(__('Deaktivieren', 'rrze-calendar')) . '</a>'
            );
        } else {
            $actions = array(
                'edit' => '<a href="' . esc_url(RRZE_Calendar::options_url(array('action' => 'edit', 'feed-id' => $item['id']))) . '">' . esc_html(__('Bearbeiten', 'rrze-calendar')) . '</a>',
                'activate' => '<a href="' . esc_url(RRZE_Calendar::options_url(array('action' => 'activate', 'feed-id' => $item['id']))) . '">' . esc_html(__('Aktivieren', 'rrze-calendar')) . '</a>',                
                'delete' => '<a href="' . esc_url(RRZE_Calendar::options_url(array('action' => 'delete', 'feed-id' => $item['id']))) . '">' . esc_html(__('Löschen', 'rrze-calendar')) . '</a>'
            );
        }
        // Return the title contents
        return sprintf('%1$s %2$s',
                /* $1%s */ sprintf('<a href="%1$s">%2$s</a>', $item['url'], $item['url']),
                /* $2%s */ $this->row_actions($actions)
        );
    }

    public function column_cb($item) {
        return sprintf(
                '<input type="checkbox" name="%1$s[]" value="%2$s">',
                /* $1%s */ $this->_args['singular'], // Let's simply repurpose the table's singular label
                /* $2%s */ $item['id'] // The value of the checkbox should be the items's id
        );
    }

    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox">', // Render a checkbox instead of text
            'url' => __('Feed-URL', 'rrze-calendar'),
            'title' => __('Titel', 'rrze-calendar'),
            'category' => __('Kategorie', 'rrze-calendar'),
            'tags' => __('Schlagworte', 'rrze-calendar'),
            'events_count' => __('Termine', 'rrze-calendar')
        );
        return $columns;
    }

    public function get_sortable_columns() {
        $sortable_columns = array(
            'title' => array('title', FALSE),
            'events_count' => array('events_count', TRUE)
        );
        return $sortable_columns;
    }

    public function get_bulk_actions() {
        $actions = array(
            'update' => __('Aktualisieren', 'rrze-calendar'),
            'activate' => __('Aktivieren', 'rrze-calendar'),
            'deactivate' => __('Deaktivieren', 'rrze-calendar'),
            'delete' => __('Löschen', 'rrze-calendar')
        );
        return $actions;
    }

    public function process_bulk_action() {
        if(!empty($_POST['feed']) && is_array($_POST['feed'])) {
            $feed_ids = $_POST['feed'];
            switch ($this->current_action()) {
                case 'update':
                    $this->feed_bulk_update($feed_ids);
                    break;                
                case 'delete':
                    $this->feed_bulk_delete($feed_ids);
                    break;
                case 'activate':
                    $this->feed_bulk_activate($feed_ids);
                    break;
                case 'deactivate':
                    $this->feed_bulk_activate($feed_ids, 0);
                    break;
            }
        }
    }

    private function feed_bulk_update($feed_ids) {
        $this->rrze_calendar->feed_bulk_update($feed_ids);
    }
    
    private function feed_bulk_delete($feed_ids) {
        $this->rrze_calendar->feed_bulk_delete($feed_ids);
    }
    
    private function feed_bulk_activate($feed_ids, $activate = 1) {
        $this->rrze_calendar->feed_bulk_activate($feed_ids, $activate);
    }
    
    public function sort_data($a, $b) {
        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'created';
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
                $url = mb_stripos($data['url'], $search) === FALSE ? TRUE : FALSE;
                $title = mb_stripos($data['title'], $search) === FALSE ? TRUE : FALSE;
                $category = mb_stripos((isset($data['category']['name']) ? $data['category']['name'] : ''), $search) === FALSE ? TRUE : FALSE;
                $tags = !empty($data['tags']) ? $data['tags'] : array();
                $tag = TRUE;
                foreach ($tags as $value) {
                    if (isset($value['name']) && mb_stripos($value['name'], $search) !== FALSE) {
                        $tag = FALSE;
                        break;
                    }
                }
                if ($url && $title && $category && $tag) {
                    unset($this->list_data[$key]);
                }
            }
        }        

        $this->process_bulk_action();

        $per_page = $this->get_items_per_page('feeds_per_page', 20);
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
