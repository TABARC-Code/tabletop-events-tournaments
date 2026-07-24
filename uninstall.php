<?php
/**
 * Runs only when the plugin is deleted from wp-admin (not on simple
 * deactivation). Same deliberately conservative approach as the rest
 * of this family — removes only the plugin's own version marker,
 * keeps every tournament, player list, and round result in place.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ttrn_plugin_version' );
