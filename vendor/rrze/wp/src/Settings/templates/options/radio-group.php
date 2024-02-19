<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row">
        <?php echo $option->getLabel(); ?>
    </th>
    <td>
        <fieldset>
            <legend class="screen-reader-text"><span><?php echo $option->getLabel(); ?></span></legend>
            <?php foreach ($option->getArg('options', []) as $key => $label) { ?>
                <label>
                    <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo $option->getIdAttribute(); ?>" type="radio" value="<?php echo $key; ?>" <?php checked($key, $option->getValueAttribute()); ?> <?php echo $option->getInputClassAttribute(); ?>>
                    <?php echo $label; ?>
                </label><br>
            <?php } ?>
            <?php if ($description = $option->getArg('description')) { ?>
                <p class="description"><?php echo $description; ?></p>
            <?php } ?>
            <?php if ($error = $option->hasError()) { ?>
                <div class="rrze-wp-settings-error"><?php echo $error; ?></div>
            <?php } ?>
        </fieldset>
    </td>
</tr>