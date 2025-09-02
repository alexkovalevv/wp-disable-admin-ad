<?php
// uninstall.php — optional cleanup

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Only delete if option set to true.
$delete = (bool) get_option( 'aidad_delete_on_uninstall', false );
if ( ! $delete ) {
    return;
}

delete_option( 'wp_admin_ad_hider_options' );
delete_site_option( 'wp_admin_ad_hider_network_options' );

delete_option( 'ads-destroyer_logs' );

