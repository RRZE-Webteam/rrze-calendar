<?php

namespace RRZE\WP\Settings;

defined('ABSPATH') || exit;
?>
<h2 class="nav-tab-wrapper">
    <?php foreach ($settings->tabs as $tab) { ?>
        <a href="<?php echo $settings->getUrl(); ?>&tab=<?php echo $tab->slug; ?>" class="nav-tab <?php echo $tab->slug == $settings->getActiveTab()->slug ? 'nav-tab-active' : null; ?>"><?php echo $tab->title; ?></a>
    <?php } ?>
</h2>