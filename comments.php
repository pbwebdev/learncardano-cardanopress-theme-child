<?php

/**
 * The template for displaying comments
 *
 * @package Learn Cardano
 * @since 0.1.0
 */

?>


                <ol class="comment-list">
                    <?php
                    wp_list_comments(
                        array(
                            'style' => 'ol',
                        )
                    );
                    ?>
                </ol><!-- .comment-list -->

                <?php
                the_comments_navigation();
                comment_form(
                    array(
                        'comment_field'      => '<p class="comment-form-comment"><textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required="required"></textarea></p>',
                        'logged_in_as'       => null,
                        'title_reply_before' => '<h4>',
                        'title_reply_after'  => '</h4>',
                    )
                );
                ?>

<!-- #comments -->