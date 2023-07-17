<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo $settings->title; ?></h1>

    <?php if ($flash = $settings->flash->has()) { ?>
        <div class="notice notice-<?php echo $flash['status']; ?> is-dismissible">
            <p><?php echo $flash['message']; ?></p>
        </div>
    <?php } ?>

    <?php if ($errors = $settings->errors->getAll()) { ?>
        <div class="notice notice-error is-dismissible">
            <p><?php _e('Settings issues detected.', 'rrze-wp-settings'); ?></p>
        </div>
    <?php } ?>

    <?php $settings->renderTabMenu(); ?>

    <?php $settings->renderActiveSections(); ?>
</div>