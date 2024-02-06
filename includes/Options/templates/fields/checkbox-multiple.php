<?php

namespace RRZE\Calendar\Options;

defined('ABSPATH') || exit;
?>
<tr>
    <th scope="row"><?php echo $option->getLabel(); ?></th>
    <td>
        <fieldset>
            <legend class="screen-reader-text"><span>Standardeinstellungen für Beiträge</span></legend>
            <?php foreach ($option->getArg('options', []) as $key => $label) : ?>
                <label for="<?php echo $option->getIdAttribute() . '_' . $key; ?>">
                    <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo $option->getIdAttribute() . '_' . $key; ?>" type="checkbox" value="<?php echo $key; ?>" <?php echo in_array($key, $option->getValueAttribute() ?? []) ? 'checked' : null; ?> class="<?php echo $option->getInputClassAttribute(); ?>"> <?php echo $label; ?>
                </label><br>
            <?php endforeach ?>
            <?php if ($description = $option->getArg('description')) : ?>
                <p class="description"><?php echo $description; ?></p>
                <?php if ($error = $option->hasError()) : ?>
                    <div class="rrze-calendar-error"><?php echo $error; ?></div>
                <?php endif ?>
            <?php endif ?>
        </fieldset>
    </td>
</tr>