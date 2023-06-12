<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

class CategoryRadiolist extends \Walker_Category_Checklist
{
    /**
     * Start the element output.
     *
     * @see Walker::start_el()
     *
     * @since 2.5.1
     * @since 5.9.0 Renamed `$category` to `$data_object` and `$id` to `$current_object_id`
     *              to match parent class for PHP 8 named parameter support.
     *
     * @param string  $output            Used to append additional content (passed by reference).
     * @param WP_Term $data_object       The current term object.
     * @param int     $depth             Depth of the term in reference to parents. Default 0.
     * @param array   $args              An array of arguments. @see wp_terms_checklist()
     * @param int     $current_object_id Optional. ID of the current term. Default 0.
     */
    public function start_el(&$output, $data_object, $depth = 0, $args = array(), $current_object_id = 0)
    {
        $category = $data_object;

        if (empty($args['taxonomy'])) {
            $taxonomy = 'category';
        } else {
            $taxonomy = $args['taxonomy'];
        }

        $name = 'tax_input['.$taxonomy.']';

        $bgcolor = Utils::sanitizeHexColor(get_term_meta($category->term_id, 'color', true));
        $color = $bgcolor ? Utils::getContrastYIQ($bgcolor) : '#3c434a';
        $class = ' class="category-bgcolor"';

        $args['selected_cats'] = !empty($args['selected_cats']) ? array_map('intval', $args['selected_cats']) : array();

        if (!empty($args['list_only'])) {
            $aria_checked = 'false';
            $inner_class  = 'category';

            if (in_array($category->term_id, $args['selected_cats'], true)) {
                $inner_class .= ' selected';
                $aria_checked = 'true';
            }

            $output .= "\n" . '<li' . $class . '>' .
                '<div class="' . $inner_class . '" data-term-id=' . $category->term_id .
                ' tabindex="0" role="radio" aria-checked="' . $aria_checked . '">' .
                /** This filter is documented in wp-includes/category-template.php */
                esc_html(apply_filters('the_category', $category->name, '', '')) . '</div>';
        } else {
            $is_selected = in_array($category->term_id, $args['selected_cats'], true);
            $is_disabled = !empty($args['disabled']);

            $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class style='--category-bgcolor: " . $bgcolor . "; --category-color: " . $color . "'>" .
                '<label class="selectit category-color"><input value="' . $category->term_id . '" type="radio" name="' . $name . '[]" id="in-' . $taxonomy . '-' . $category->term_id . '"' .
                checked($is_selected, true, false) .
                disabled($is_disabled, true, false) . ' /> ' .
                /** This filter is documented in wp-includes/category-template.php */
                esc_html(apply_filters('the_category', $category->name, '', '')) . '</label>';
        }
    }
}
