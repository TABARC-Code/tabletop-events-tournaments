<?php
/**
 * [tabletop_tournament_standings event="123"] — read-only current
 * standings and the current round's pairings, for players and
 * spectators. No admin controls here at all; that lives on its own
 * wp-admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTRN_Shortcode_Standings {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_shortcode( 'tabletop_tournament_standings', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array( 'event' => 0 ), $atts, 'tabletop_tournament_standings' );
		$event_id = (int) $atts['event'];
		if ( ! $event_id ) {
			return '';
		}

		wp_enqueue_style( 'ttrn-standings', TTRN_PLUGIN_URL . 'assets/css/tournament.css', array(), TTRN_VERSION );
		wp_enqueue_script( 'ttrn-standings', TTRN_PLUGIN_URL . 'assets/js/tournament-standings.js', array(), TTRN_VERSION, true );

		// wp_head()'s print pass has usually already run by the time a
		// shortcode renders inside the_content() — print explicitly or
		// this never makes it onto the page.
		wp_print_styles( 'ttrn-standings' );

		wp_localize_script(
			'ttrn-standings',
			'TTRN_STANDINGS',
			array(
				'restUrl' => esc_url_raw( rest_url( 'ttrn/v1' ) ),
				'eventId' => $event_id,
			)
		);

		return '<div class="ttrn-standings-root" data-ttrn-standings></div>';
	}
}
