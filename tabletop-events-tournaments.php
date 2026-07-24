<?php
/**
 * Plugin Name:       Tabletop Events Calendar — Tournaments
 * Plugin URI:        https://github.com/TABARC-Code/tabletop-events-tournaments
 * Description:       Swiss pairings, live standings, and tie-break handling for competitive Tabletop Events Calendar events. Requires the Tabletop Events Calendar plugin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  tabletop-events-calendar
 * Author:            TABARC-Code
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tabletop-events-tournaments
 *
 * Player list, round-by-round pairings, and results all live as JSON
 * in a couple of post meta fields on the plugin's own tournament post
 * — the same "store the whole structure as one JSON blob" approach
 * the core plugin already uses for _tec_rsvp_list, rather than a set
 * of custom database tables for what's fundamentally a small, bursty
 * amount of per-event data.
 *
 * Pairing generation and result entry happen in wp-admin, not through
 * a public magic link like the rest of this family — running a live
 * Swiss tournament is an active, in-the-room task, not a one-off
 * self-service edit, so it gets the same "admin does it in wp-admin"
 * treatment as review/listing moderation elsewhere in this project.
 * Standings and the current round's pairings are still public, via
 * their own read-only shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TTRN_VERSION', '1.0.0' );
define( 'TTRN_PLUGIN_FILE', __FILE__ );
define( 'TTRN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TTRN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TTRN_POST_TYPE', 'ttrn_tournament' );

spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'TTRN_' ) !== 0 ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', $class ) );
		$path = TTRN_PLUGIN_DIR . 'includes/class-' . $slug . '.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

function ttrn_init() {
	if ( ! ttrn_dependency_met() ) {
		add_action( 'admin_notices', 'ttrn_missing_dependency_notice' );
		return;
	}

	load_plugin_textdomain( 'tabletop-events-tournaments', false, dirname( plugin_basename( TTRN_PLUGIN_FILE ) ) . '/languages' );

	TTRN_Post_Type::instance();
	TTRN_Rest::instance();
	TTRN_Admin_Page::instance();
	TTRN_Shortcode_Standings::instance();

	ttrn_maybe_upgrade();
}
add_action( 'plugins_loaded', 'ttrn_init', 20 );

function ttrn_dependency_met() {
	return defined( 'TEC_POST_TYPE' ) && class_exists( 'TEC_Admin' );
}

function ttrn_missing_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Tabletop Events Calendar — Tournaments requires the Tabletop Events Calendar plugin to be installed and active.', 'tabletop-events-tournaments' ) .
		'</p></div>';
}

/**
 * Deferred to 'init', same reasoning as every other plugin in this
 * family — flush_rewrite_rules() needs $wp_rewrite, which doesn't
 * exist yet on plugins_loaded.
 */
function ttrn_maybe_upgrade() {
	add_action( 'init', 'ttrn_run_upgrade', 20 );
}
function ttrn_run_upgrade() {
	$installed = get_option( 'ttrn_plugin_version' );
	if ( $installed === TTRN_VERSION ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'ttrn_plugin_version', TTRN_VERSION, false );
}

function ttrn_activate() {
	if ( ttrn_dependency_met() ) {
		require_once TTRN_PLUGIN_DIR . 'includes/class-ttrn-post-type.php';
		TTRN_Post_Type::instance()->register_post_type();
	}
	flush_rewrite_rules();
	update_option( 'ttrn_plugin_version', TTRN_VERSION, false );
}
register_activation_hook( TTRN_PLUGIN_FILE, 'ttrn_activate' );

function ttrn_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( TTRN_PLUGIN_FILE, 'ttrn_deactivate' );
