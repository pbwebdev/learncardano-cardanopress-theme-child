<?php

/**
 * The template containing the sidebar area
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

if ( ! is_active_sidebar( 'sidebar' ) ) {
	return;
}

?>

<aside class="<?php echo cardanopress_child_bootstrap_class( 'sidebar' ); ?>">
	<?php dynamic_sidebar( 'sidebar' ); ?>

    <script src="https://unpkg.com/react@18.2/umd/react.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18.2/umd/react-dom.production.min.js" crossorigin></script>
    <script type="module" src="https://unpkg.com/@dexhunterio/swaps@0.0.86/lib/umd/swaps.umd.js"></script>


    <div class="dexhunter-side">
        <div id="dexhunter-root"></div>
        <script type="module">
            ReactDOM.render(
                React.createElement(
                    dexhunterSwap,
                    {"orderTypes":["SWAP","LIMIT"],"colors":{"background":"#FFFFFF","containers":"#D9E3F0","subText":"#555555","mainText":"#0E0F12","buttonText":"#FFFFFF","accent":"#007DFF"},"theme":"light","width":450,"partnerCode":"learncardano616464723171786d6b393439323730327138683630756466383067786e716d6a336a64757a32767235796532757470667038677634387576687a7168383077347135636d776535753570746c796a74756a6a38386875363675616c70656a663373743564743978da39a3ee5e6b4b0d3255bfef95601890afd80709","partnerName":"LearnCardano"}
                ),
                document.getElementById('dexhunter-root')
            );
        </script>
    </div>
</aside><!-- .sidebar -->
