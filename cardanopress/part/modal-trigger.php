<?php

/**
 * The template for displaying the modal popup trigger button.
 *
 * This can be overridden by copying it to yourtheme/cardanopress/part/modal-trigger.php.
 *
 * @package ThemePlate
 * @since   0.1.0
 */

if (empty($text)) {
    $text = 'Connect wallet';
}

?>

<button type='button' @click='showModal = true' class="btn btn-primary btn-sm"><span class="fa fa-wallet"></span> <?php echo $text; ?></button>
