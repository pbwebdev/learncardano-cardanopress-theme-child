<?php

/**
 * The template for displaying the single proposal.
 *
 * This can be overridden by copying it to yourtheme/single-proposal.php.
 *
 * @package ThemePlate
 * @since   0.1.0
 */

$proposalId = get_the_ID();
$proposal = cpGovernance()->getProposalInstance($proposalId);
$proposalDates = $proposal->getDates();
$discussionLink = $proposal->getDiscussionLink();

$currentStatus = get_post_status();
$statusText = 'Open for Voting';
$dateLabel = 'Closing Date';
$dateText = $proposalDates['end'];

if ('future' === $currentStatus) {
    $statusText = 'Upcoming';
    $dateLabel = 'Starting Date';
    $dateText = $proposalDates['start'];
} elseif ('archive' === $currentStatus) {
    $statusText = 'Complete';
}

get_header();

?>

<div id="proposal-<?php echo esc_attr($proposalId); ?>" class="py-5">
    <div class="container">
        <div class="row justify-content-md-center">
            <div class="col col-md-10">
                <nav class="breadcrumb" style="--bs-breadcrumb-divider: ' ';" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo esc_url(home_url()); ?>">Home</a></li>
                        <li class="breadcrumb-item">
                            <a href='<?php echo esc_url(get_post_type_archive_link('proposal')); ?>'>Governance</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page"><?php the_title(); ?></li>
                    </ol>
                </nav>

                <h1 class="pb-3"><?php the_title(); ?></h1>
                <p><b>Status: <?php echo esc_html($statusText); ?></b>
                <p><b><?php echo esc_html($dateLabel); ?>: <?php echo esc_html($dateText); ?></b></p>
                <p><b>Snapshot: <?php echo esc_html($proposalDates['snapshot']); ?></b></p>

                <?php the_content(); ?>

                <?php if ($discussionLink['url']) : ?>
                    <div class="mt-4">
                        <a
                            class="btn btn-primary"
                            href="<?php echo esc_url($discussionLink['url']); ?>"
                            target="<?php echo esc_attr($discussionLink['target']); ?>"
                        >
                            <?php echo esc_html($discussionLink['text']); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ('future' !== $currentStatus) : ?>
                    <div class="mt-5">
                        <?php cpGovernance()->template(
                            'proposal/voting-area',
                            compact('proposal', 'currentStatus')
                        ); ?>
                    </div>

                    <div class="mt-5">
                        <?php cpGovernance()->template(
                            'proposal/voting-transactions',
                            compact('proposal')
                        ); ?>
                    </div>
                <?php endif; ?>


                

                    <div class="podcast-comments pt-5 section">
                        <div class="container">
                            <div class="row justify-content-md-center">
                                <div class="col-lg-10 col-md-12">
                                    <h2>Comments</h2>
                                    <?php

                                    // If comments are open or we have at least one comment, load up the comment template.
                                    if ( comments_open() || get_comments_number() ) :
                                        comments_template();
                                    endif;

                                    the_post_navigation(
                                        array(
                                            'prev_text' => '<span class="screen-reader-text">' . __( 'Previous Post', 'learncardano' ) . '</span><span aria-hidden="true" class="nav-subtitle">' . __( 'Previous', 'learncardano' ) . '</span> <span class="nav-title"><span class="nav-title-icon-wrapper"> <<< </span>%title</span>',
                                            'next_text' => '<span class="screen-reader-text">' . __( 'Next Post', 'learncardano' ) . '</span><span aria-hidden="true" class="nav-subtitle">' . __( 'Next', 'learncardano' ) . '</span> <span class="nav-title">%title<span class="nav-title-icon-wrapper"> >>> </span></span>',
                                        )
                                    );

                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>


            </div>
        </div>
    </div>
</div>

<?php

get_footer();
