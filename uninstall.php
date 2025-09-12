<?php
// uninstall.php — optional cleanup

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Only delete if option set to true.
$delete = (bool) get_option( 'adsd_delete_on_uninstall', false );
if ( ! $delete ) {
    return;
}

delete_option( 'adsd_disable_ad_options' );
delete_site_option( 'adsd_network_disable_ad_options' );

delete_option( 'ads-destroyer_logs' );

