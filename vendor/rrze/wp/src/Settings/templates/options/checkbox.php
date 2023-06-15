<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="<?php echo $option->getIdAttribute(); ?>" class="<?php echo $option->getLabelClassAttribute(); ?>"><?php echo $option->getLabel(); ?></label>
    </th>
    <td class="forminp forminp-text">

        <label>
            <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo $option->getIdAttribute(); ?>" type="checkbox" value="<?php echo $option->getValueAttribute(); ?>" <?php echo $option->is_checked() ? 'checked' : null; ?> class="<?php echo $option->getInputClassAttribute(); ?>">
            <?php echo $option->getArg('description'); ?>
        </label>

        <input type="hidden" name="wp_settings_submitted[]" value="<?php echo esc_attr($option->getName()); ?>">

        <?php if ($error = $option->has_error()) { ?>
            <div class="rrze-wp-settings-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>