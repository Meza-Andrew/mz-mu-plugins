<?php

/**
 * Minimal single event template override loaded from mz-plugin-compat.
 *
 * This bypasses plugin-specific single-event wrappers and keeps only the
 * active theme's normal header/footer plus the event content.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div id="main-content">
    <?php while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</div>
<?php
get_footer();
