<?php
/*
 * LaunchKey Uninstall - Securely remove all associated data.
 *
 * Uninstall will require new settings to be setup and the re-pairing of users if the plugin is re-installed in the future.
 */

//if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

//remove launchkey options
delete_option( 'launchkey_app_key' );
delete_option( 'launchkey_secret_key' );

//remove user pairings
delete_metadata( 'user', 0, 'launchkey_user', '', true );