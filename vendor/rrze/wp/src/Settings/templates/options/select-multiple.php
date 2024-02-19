<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row" class="rrze-wp-form-label">
        <label for="<?php echo $option->getIdAttribute(); ?>" <?php echo $option->getLabelClassAttribute(); ?>><?php echo $option->getLabel(); ?></label>
    </th>
    <td class="rrze-wp-form rrze-wp-form-input">
        <select id="<?php echo $option->getIdAttribute(); ?>" name="<?php echo esc_attr($option->getNameAttribute()); ?>" multiple <?php echo $option->getInputClassAttribute(); ?>>
            <?php foreach ($option->getArg('options', []) as $key => $label) { ?>
                <option value="<?php echo $key; ?>" <?php echo in_array($key, $option->getValueAttribute() ?? []) ? 'selected' : null; ?>><?php echo $label; ?></option>
            <?php } ?>
        </select>
        <?php if ($description = $option->getArg('description')) { ?>
            <p class="description"><?php echo $description; ?></p>
        <?php } ?>

        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-wp-settings-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>