<?php

namespace RRZE\Calendar\Settings;

defined('ABSPATH') || exit;
?>
<form method="post" action="<?php echo $settings->getFullUrl(); ?>">
    <?php Template::include('section-menu', compact('settings')); ?>

    <?php foreach ($settings->getActiveTab()->getActiveSections() as $section) { ?>
        <?php Template::include('section', compact('section')); ?>
    <?php } ?>

    <?php wp_nonce_field('rrze_calendar_settings_save_' . $settings->optionName, 'rrze_calendar_settings_save'); ?>

    <?php submit_button(); ?>
</form>