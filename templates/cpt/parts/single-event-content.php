<?php

/**
 * Event content partial (shared by the classic single template and the block template).
 *
 * Available variables:
 * @var int   $postId    Event post ID
 * @var array $data      Event data (CalendarEvent::getEventData())
 * @var bool  $showTitle Whether to output the title (false in block themes, where core/post-title is used)
 */

use RRZE\Calendar\CPT\CalendarEvent;

defined('ABSPATH') || exit;

$showTitle = $showTitle ?? true;
?>
<article class="rrze-event" itemscope itemtype="https://schema.org/Event">

    <?php if ($showTitle) : ?>
        <header class="entry-header">
            <h1 class="entry-title" itemprop="name"><?php echo esc_html(get_the_title($postId)); ?></h1>
        </header><!-- .entry-header -->
    <?php else : ?>
        <meta itemprop="name" content="<?php echo esc_attr(get_the_title($postId)); ?>">
    <?php endif; ?>

    <div class="rrze-event-main">

        <?php if (has_post_thumbnail($postId) && !post_password_required($postId)) : ?>
            <figure class="post-thumbnail wp-caption alignright">
                <?php echo get_the_post_thumbnail($postId, 'medium'); ?>
                <figcaption class="wp-caption-text"><?php echo get_the_post_thumbnail_caption($postId); ?></figcaption>
            </figure>
        <?php endif; ?>

        <?php CalendarEvent::displayEventMain($data); ?>

    </div>

    <?php CalendarEvent::displayEventDetails($data); ?>

</article>
