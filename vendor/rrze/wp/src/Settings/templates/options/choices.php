<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="<?php echo $option->getIdAttribute(); ?>" class="<?php echo $option->getLabelClassAttribute(); ?>"><?php echo $option->getLabel(); ?></label>
    </th>
    <td class="forminp forminp-text">

        <?php foreach ($option->getArg('options', []) as $key => $label) { ?>
            <div>
                <label>
                    <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo $option->getIdAttribute(); ?>" type="radio" value="<?php echo $key; ?>" <?php checked($key, $option->getValueAttribute()); ?> class="<?php echo $option->getInputClassAttribute(); ?>">
                    <?php echo $label; ?>
                </label>
            </div>
        <?php } ?>

        <?php if ($description = $option->getArg('description')) { ?>
            <p class="description"><?php echo $description; ?></p>
        <?php } ?>

        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-wp-settings-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>