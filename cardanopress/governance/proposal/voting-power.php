<?php

/**
 * The template for displaying the proposal voting power.
 *
 * This can be overridden by copying it to yourtheme/cardanopress/governance/proposal/voting-power.php.
 *
 * @package ThemePlate
 * @since   0.1.0
 */

if (empty($proposal)) {
    $proposal = cpGovernance()->getProposalInstance(get_the_ID());
}

$userProfile = cpGovernance()->userProfile();

?>

<template x-if='!isConnected'>
    <div>
        <h3>Connect to see voting power</h3>
        <p><img alt="CardanoPress Wapuu" src="https://learncardano.io/wp-content/uploads/2023/10/profile-no-bg.png" width="300" height="300"> </p>
        <p>Voting power is determined by the amount of Wapuus you hold in your wallet.</p>
        <p>You can find out <a href="https://cardanopress.io/wapuu-nft/" target="_blank">more about Wapuus</a> or purchase one on the <a href="https://www.jpg.store/collection/6ed115674659a344b50ba87f7085fddb1ebcedd14cf61ee71a5974ee/" target="_blank">secondary markets</a>.</p>
    </div>
</template>

<template x-if='isConnected'>
    <div>
        <h3><?php echo esc_html($proposal->getVotingPower($userProfile)); ?>Wapuu(s)</h3>
        <p><img alt="CardanoPress Wapuu" src="https://learncardano.io/wp-content/uploads/2023/10/profile-no-bg.png" width="300" height="300"> </p>
        <p>Voting power is determined by the amount of Wapuus you hold in your wallet.</p>
        <p>You can find out <a href="https://cardanopress.io/wapuu-nft/" target="_blank">more about Wapuus</a> or purchase one on the <a href="https://www.jpg.store/collection/6ed115674659a344b50ba87f7085fddb1ebcedd14cf61ee71a5974ee/" target="_blank">secondary markets</a>.</p>
    </div>
</template>
