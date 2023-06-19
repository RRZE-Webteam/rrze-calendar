<?php

namespace RRZE\Calendar\CPT;

defined('ABSPATH') || exit;

class CategoryMetabox
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'removeMetaboxes']);
        add_action('add_meta_boxes', [__CLASS__, 'addMetaboxes']);
        add_filter('wp_terms_checklist_args', [__CLASS__, 'filterTermsCkecklistArgs']);
        add_action('wp_ajax_add-' . CalendarEvent::TAX_CATEGORY, [__CLASS__, 'addTerm'], 5);
        add_action('save_post', [__CLASS__, 'saveSingleTerm']);
    }

    public static function removeMetaboxes()
    {
        remove_meta_box(CalendarEvent::TAX_CATEGORY . 'div', CalendarEvent::POST_TYPE, 'side');
        remove_meta_box(CalendarEvent::TAX_CATEGORY . 'div', CalendarFeed::POST_TYPE, 'side');
    }

    public static function addMetaboxes()
    {
        $taxonomy = get_taxonomy(CalendarEvent::TAX_CATEGORY);
        add_meta_box(
            CalendarEvent::TAX_CATEGORY . 'div',
            $taxonomy->label,
            [__CLASS__, 'renderCategoryMetabox'],
            CalendarEvent::POST_TYPE,
            'side'
        );
        add_meta_box(
            CalendarEvent::TAX_CATEGORY . 'div',
            $taxonomy->label,
            [__CLASS__, 'renderCategoryMetabox'],
            CalendarFeed::POST_TYPE,
            'side'
        );
    }

    public static function renderCategoryMetabox($post)
    {
        $taxName = esc_attr(CalendarEvent::TAX_CATEGORY);
        $taxonomy = get_taxonomy(CalendarEvent::TAX_CATEGORY);
?>
        <div id="taxonomy-<?php echo $taxName; ?>" class="categorydiv">
            <div id="<?php echo $taxName; ?>-all" class="tabs-panel">
                <?php
                // Allows for an empty term set to be sent. 0 is an invalid term ID and will be ignored by empty() checks.
                echo "<input type='hidden' name='{$taxName}[]' value='0' />";
                ?>
                <ul id="<?php echo $taxName; ?>checklist" data-wp-lists="list:<?php echo $taxName; ?>" class="categorychecklist form-no-clear">
                    <?php
                    self::termsChecklist(
                        $post->ID,
                        [
                            'taxonomy' => $taxName
                        ]
                    );
                    ?>
                </ul>
            </div>
            <?php if (current_user_can($taxonomy->cap->edit_terms)) : ?>
                <div id="<?php echo $taxName; ?>-adder" class="wp-hidden-children">
                    <a id="<?php echo $taxName; ?>-add-toggle" href="#<?php echo $taxName; ?>-add" class="hide-if-no-js taxonomy-add-new">
                        <?php
                        printf(
                            /* translators: %s: Add New taxonomy label. */
                            __('+ %s'),
                            $taxonomy->labels->add_new_item
                        );
                        ?>
                    </a>
                    <p id="<?php echo $taxName; ?>-add" class="category-add wp-hidden-child">
                        <label class="screen-reader-text" for="new<?php echo $taxName; ?>"><?php echo $taxonomy->labels->add_new_item; ?></label>
                        <input type="text" name="new<?php echo $taxName; ?>" id="new<?php echo $taxName; ?>" class="form-required form-input-tip" value="<?php echo esc_attr($taxonomy->labels->new_item_name); ?>" aria-required="true" />
                        <input type="button" id="<?php echo $taxName; ?>-add-submit" data-wp-lists="add:<?php echo $taxName; ?>checklist:<?php echo $taxName; ?>-add" class="button category-add-submit" value="<?php echo esc_attr($taxonomy->labels->add_new_item); ?>" />
                        <?php wp_nonce_field('add-' . $taxName, '_ajax_nonce-add-' . $taxName, false); ?>
                        <span id="<?php echo $taxName; ?>-ajax-response"></span>
                    </p>
                </div>
            <?php endif; ?>
        </div>
<?php
    }

    public static function filterTermsCkecklistArgs($args)
    {
        if (isset($args['taxonomy']) && CalendarEvent::TAX_CATEGORY == $args['taxonomy']) {
            $args['walker'] = new CategoryRadiolistWalker;
        }
        return $args;
    }

    protected static function termsChecklist($postId = 0, $args = [])
    {
        $defaults = [
            'descendants_and_self' => 0,
            'selected_cats'        => false,
            'popular_cats'         => false,
            'walker'               => null,
            'taxonomy'             => 'category',
            'checked_ontop'        => true,
            'echo'                 => true,
        ];

        $parsedArgs = wp_parse_args($args, $defaults);

        if (empty($parsedArgs['walker']) || !($parsedArgs['walker'] instanceof \Walker)) {
            $walker = new CategoryRadiolist;
        } else {
            $walker = $parsedArgs['walker'];
        }

        $taxonomy = $parsedArgs['taxonomy'];
        $descendantsAndSelf = (int) $parsedArgs['descendants_and_self'];

        $args = ['taxonomy' => $taxonomy];

        $tax = get_taxonomy($taxonomy);
        $args['disabled'] = !current_user_can($tax->cap->assign_terms);

        $args['list_only'] = !empty($parsedArgs['list_only']);

        if (is_array($parsedArgs['selected_cats'])) {
            $args['selected_cats'] = array_map('intval', $parsedArgs['selected_cats']);
        } elseif ($postId) {
            $args['selected_cats'] = wp_get_object_terms($postId, $taxonomy, array_merge($args, ['fields' => 'ids']));
        } else {
            $args['selected_cats'] = [];
        }

        if (is_array($parsedArgs['popular_cats'])) {
            $args['popular_cats'] = array_map('intval', $parsedArgs['popular_cats']);
        } else {
            $args['popular_cats'] = get_terms(
                [
                    'taxonomy'     => $taxonomy,
                    'fields'       => 'ids',
                    'orderby'      => 'count',
                    'order'        => 'DESC',
                    'number'       => 10,
                    'hierarchical' => false,
                ]
            );
        }

        if ($descendantsAndSelf) {
            $categories = (array) get_terms(
                [
                    'taxonomy'     => $taxonomy,
                    'child_of'     => $descendantsAndSelf,
                    'hierarchical' => 0,
                    'hide_empty'   => 0,
                ]
            );
            $self = get_term($descendantsAndSelf, $taxonomy);
            array_unshift($categories, $self);
        } else {
            $categories = (array) get_terms(
                [
                    'taxonomy' => $taxonomy,
                    'get'      => 'all',
                ]
            );
        }

        $output = '';

        if ($parsedArgs['checked_ontop']) {
            // Post-process $categories rather than adding an exclude to the get_terms() query
            // to keep the query the same across all posts (for any query cache).
            $checked_categories = [];
            $keys = array_keys($categories);

            foreach ($keys as $k) {
                if (in_array($categories[$k]->term_id, $args['selected_cats'], true)) {
                    $checked_categories[] = $categories[$k];
                    unset($categories[$k]);
                }
            }

            // Put checked categories on top.
            $output .= $walker->walk($checked_categories, 0, $args);
        }
        // Then the rest of them.
        $output .= $walker->walk($categories, 0, $args);

        if ($parsedArgs['echo']) {
            echo $output;
        }

        return $output;
    }

    public static function addTerm()
    {
        $action = $_POST['action'];
        $tax_name = substr($action, 4);
        $taxonomy = get_taxonomy($tax_name);
        check_ajax_referer($action, '_ajax_nonce-add-' . $taxonomy->name);
        if (current_user_can($taxonomy->cap->edit_terms)) {
            $names = explode(',', $_POST['new' . $taxonomy->name]);

            foreach ($names as $catName) {
                $catName = trim($catName);
                $category_nicename = sanitize_title($catName);
                if ('' === $category_nicename) {
                    continue;
                }

                if (!$catId = term_exists($catName, $taxonomy->name)) {
                    $catId = wp_insert_term($catName, $taxonomy->name);
                }

                if (is_wp_error($catId)) {
                    continue;
                } else if (is_array($catId)) {
                    $catId = $catId['term_id'];
                }

                $data = sprintf(
                    '<li id="%1$s-%2$s" class="category-bgcolor"><label class="selectit"><input id="in-%1$s-%2$s" type="radio" name="tax_input[%1$s][]" value="%2$s"> %3$s</label></li>',
                    esc_attr($taxonomy->name),
                    intval($catId),
                    esc_html($catName)
                );

                $add = array(
                    'what' => $taxonomy->name,
                    'id' => $catId,
                    'data' => str_replace(array("\n", "\t"), '', $data),
                    'position' => -1
                );
            }

            $x = new \WP_Ajax_Response($add);
            $x->send();
        } else {
            return false;
        }
    }

    public static function saveSingleTerm($post_id)
    {
        // Verify if this is an auto save routine.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // Prevent weirdness with multisite.
        if (function_exists('ms_is_switched') && ms_is_switched()) {
            return $post_id;
        }

        // Make sure we're on the supported post type.
        if (isset($_REQUEST['post_type']) && $_REQUEST['post_type'] != CalendarFeed::POST_TYPE) {
            return $post_id;
        }

        // Check capabilities.
        $taxonomy = get_taxonomy(CalendarEvent::TAX_CATEGORY);
        if (!current_user_can($taxonomy->cap->edit_terms)) {
            return $post_id;
        }

        // If posts are being bulk edited, and no term is selected, do nothing.
        if (!empty($_GET['bulk_edit']) && empty($_REQUEST['tax_input'][CalendarEvent::TAX_CATEGORY])) {
            return $post_id;
        }

        // Verify nonce.
        if (!isset($_REQUEST['_radio_nonce-' . CalendarEvent::TAX_CATEGORY]) || !wp_verify_nonce($_REQUEST['_radio_nonce-' . CalendarEvent::TAX_CATEGORY], 'radio_nonce-' . CalendarEvent::TAX_CATEGORY)) {
            return $post_id;
        }

        // OK, we need to make sure we're only saving 1 term.
        if (!empty($_REQUEST['tax_input'][CalendarEvent::TAX_CATEGORY])) {
            $terms = (array) $_REQUEST['tax_input'][CalendarEvent::TAX_CATEGORY];
            $singleTerm = intval($terms[array_key_last($terms)]);
        } else {
            // If not saving any terms, set to default (if exist).
            $singleTerm = intval(get_option('default_' . CalendarEvent::TAX_CATEGORY, 0));
        }

        // Set the single terms.
        wp_set_object_terms($post_id, $singleTerm, CalendarEvent::TAX_CATEGORY);

        return $post_id;
    }
}
