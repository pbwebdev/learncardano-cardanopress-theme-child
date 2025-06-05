<?php

/**
 * The template containing the search form
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

?>

<form role="search" aria_label="Search" method="get" class="row g-3 align-items-center" action="<?php echo esc_url( home_url( '/' ) ); ?>">
    <label class="col">
        <span class="sr-only">
            <?php _x( 'Search for:', 'label' ); ?>
        </span>

        <input type="search" class="form-control" placeholder="<?php echo esc_attr_x( 'Search &hellip;', 'placeholder' ); ?>" value="<?php echo get_search_query(); ?>" name="s" />
    </label>

    <div class="col-sm-auto">
        <button type="submit" class="btn btn-secondary">
            <?php echo esc_attr_x( 'Search', 'submit button' ); ?>
        </button>
    </div>
</form>
