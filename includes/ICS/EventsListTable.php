<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\CPT\CalendarFeed;

class EventsListTable extends ListTable
{
    public function __construct()
    {

        parent::__construct([
            'singular' => __('Event', 'rrze-calendar'),
            'plural'   => __('Events', 'rrze-calendar'),
            'ajax'     => false
        ]);
    }

    /**
     * Prepare the items for the table to process.
     *
     * @return void
     */
    public function prepare_items()
    {
        $searchTerm = '';
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $searchTerm = sanitize_text_field($_GET['s']);
        }

        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $data = Events::getListTableData($searchTerm);

        $perPage = 20;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ));

        $data = array_slice($data, (($currentPage - 1) * $perPage), $perPage);

        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->items = $data;
    }

    /**
     * Override the parent columns method.
     *
     * @return array
     */
    public function get_columns()
    {
        $columns = [
            'title'             => __('Title', 'rrze-calendar'),
            'date'              => __('Date', 'rrze-calendar'),
            'readable_rrule'    => __('Recurrence', 'rrze-calendar'),
            'event_description' => __('Description', 'rrze-calendar'),
            'event_location'    => __('Location', 'rrze-calendar')
        ];

        return $columns;
    }

    /**
     * Define which columns are hidden.
     *
     * @return array
     */
    public function get_hidden_columns()
    {
        return [];
    }

    /**
     * Define what data to show on each column of the table.
     *
     * @param  Array $item Data
     * @param  String $column_name Current column name
     *
     * @return Mixed
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'title':
                return sprintf('<strong>%s</strong>', $item['summary']);
                break;
            case 'date':
                return $item['readable_date'];
                break;
            case 'readable_rrule':
                return $item['readable_rrule'] ?? '&mdash;';
                break;
            case 'event_location':
                return sanitize_text_field($item['location']);
                break;
            case 'event_description':
                return wp_trim_words(sanitize_text_field($item['description']), 20);
                break;
            default:
                return print_r($item, true);
        }
    }

    /**
     * Generates the table navigation above or below the table.
     *
     * @since 3.1.0
     * @param string $which
     */
    protected function display_tablenav($which)
    {
    ?>
        <div class="tablenav <?php echo esc_attr($which); ?>">

            <?php if ($this->has_items()) : ?>
                <div class="alignleft actions bulkactions">
                    <?php $this->bulk_actions($which); ?>
                </div>
            <?php
            endif;
            $this->extra_tablenav($which);
            $this->pagination($which);
            ?>

            <br class="clear" />
        </div>
    <?php
    }

    /**
     * Message to be displayed when there are no items
     *
     * @since 3.1.0
     */
    public function no_items()
    {
        global $post;
        $error = get_post_meta($post->ID, CalendarFeed::FEED_ERROR, true);
        if (!empty($error)) {
            echo '<p class="description">', $error, '</p>';
        } else {
            echo '<p class="description">',  __('No items found.'), '</p>';
        }
    }
}
