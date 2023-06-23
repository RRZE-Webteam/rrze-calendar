<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="<?php echo $option->getIdAttribute(); ?>" class="<?php echo $option->getLabelClassAttribute(); ?>"><?php echo $option->getLabel(); ?></label>
    </th>
    <td class="forminp forminp-text">
        <?php wp_editor($option->getValueAttribute(), $option->getIdAttribute(), [
            'textarea_name' => $option->getNameAttribute(),
            'wpautop' => $option->getArg('wpautop', true),
            'teeny' => $option->getArg('teeny', false),
            'media_buttons' => $option->getArg('media_buttons', true),
            'default_editor' => $option->getArg('default_editor'),
            'drag_drop_upload' => $option->getArg('drag_drop_upload', false),
            'textarea_rows' => $option->getArg('textarea_rows', 10),
            'tabindex' => $option->getArg('tabindex'),
            'tabfocus_elements' => $option->getArg('tabfocus_elements'),
            'editor_css' => $option->getArg('editor_css'),
            'editor_class' => $option->getArg('editor_class'),
            'tinymce' => $option->getArg('tinymce', true),
            'quicktags' => $option->getArg('quicktags', true)
        ]); ?>
        <?php if ($description = $option->getArg('description')) { ?>
            <p class="description"><?php echo $description; ?></p>
        <?php } ?>

        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-wp-settings-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>