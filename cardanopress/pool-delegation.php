<?php

/**
 * The template for displaying the stake pool delegation.
 *
 * This can be overridden by copying it to yourtheme/cardanopress/pool-delegation.php.
 *
 * @package ThemePlate
 * @since   0.1.0
 */

?>

<div x-data="poolDelegation">

    <div class="entry-wrap">
        <header class="entry-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>
        </header>

        <div class="entry-content">
            <?php the_content(); ?>
        </div>
    </div>

    <div class="py-6">
        <?php cardanoPress()->template('part/delegation-details'); ?>
    </div>

    <div class="py-6">
        <?php cardanoPress()->template('part/delegation-connect'); ?>
    </div>

    <div class="py-6">
        <?php cardanoPress()->template('part/delegation-process'); ?>
    </div>

    <div class="py-6">
        <?php cardanoPress()->template('part/delegation-result'); ?>
    </div>
</div>
