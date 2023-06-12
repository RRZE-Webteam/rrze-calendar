<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE\Calendar\Shortcodes\Shortcode;

$data = !empty($data) && class_exists(__NAMESPACE__ . '\Events') ? Events::getSingleData($data) : [];
$hasEvents = !empty($data);
$title = $data['label'] ?? '';

wp_enqueue_style('rrze-elements');
wp_enqueue_script('rrze-accordions');
if ($post = get_post()) {
    $post->post_title = $title;
}
get_header();
?>
<div id="content">
    <div class="content-container">
        <div class="content-row">
            <main>
                <h1 id="maintop" class="screen-reader-text"><?php echo $title; ?></h1>
                <div class="rrze-calendar" itemscope itemtype="http://schema.org/Event">
                    <?php if ($hasEvents) :
                        Shortcode::singleEventOutput($data);
                    else :
                        echo '<p>', __('Event not found.', 'rrze-calendar'), '</p>';
                    endif; ?>
                </div>
            </main>
        </div>
    </div>
</div>
<?php
get_footer();
