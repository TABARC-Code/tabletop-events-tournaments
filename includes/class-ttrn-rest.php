<?php
/**
 * /wp-json/ttrn/v1/* — a public read-only standings/pairings endpoint
 * for the shortcode, and an admin-only set of routes for running the
 * tournament (player list, round generation, result entry). The admin
 * routes use WordPress's own REST nonce + capability check rather
 * than the magic-link token pattern the rest of this family uses —
 * this is a wp-admin tool, not a public self-service one, so it gets
 * wp-admin's own auth instead of reinventing one.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTRN_Rest {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ttrn/v1',
			'/event/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_event_tournament' ),
				'permission_callback' => '__return_true',
			)
		);

		$admin_permission = function () {
			return current_user_can( 'manage_options' );
		};

		register_rest_route(
			'ttrn/v1',
			'/admin/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_admin_state' ),
				'permission_callback' => $admin_permission,
			)
		);
		register_rest_route(
			'ttrn/v1',
			'/admin/(?P<id>\d+)/players',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_players' ),
				'permission_callback' => $admin_permission,
			)
		);
		register_rest_route(
			'ttrn/v1',
			'/admin/(?P<id>\d+)/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate_round' ),
				'permission_callback' => $admin_permission,
			)
		);
		register_rest_route(
			'ttrn/v1',
			'/admin/(?P<id>\d+)/result',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'record_result' ),
				'permission_callback' => $admin_permission,
			)
		);
	}

	private function get_players( $tournament_id ) {
		$raw = get_post_meta( $tournament_id, '_ttrn_players', true );
		$list = $raw ? json_decode( $raw, true ) : array();
		return is_array( $list ) ? $list : array();
	}

	private function save_players( $tournament_id, $players ) {
		update_post_meta( $tournament_id, '_ttrn_players', wp_json_encode( array_values( $players ) ) );
	}

	private function get_rounds( $tournament_id ) {
		$raw = get_post_meta( $tournament_id, '_ttrn_rounds', true );
		$rounds = $raw ? json_decode( $raw, true ) : array();
		return is_array( $rounds ) ? $rounds : array();
	}

	private function save_rounds( $tournament_id, $rounds ) {
		update_post_meta( $tournament_id, '_ttrn_rounds', wp_json_encode( array_values( $rounds ) ) );
	}

	private function name_lookup( array $players ) {
		$names = array();
		foreach ( $players as $p ) {
			$names[ $p['id'] ] = $p['name'];
		}
		return $names;
	}

	private function public_pairings( array $tables, array $names ) {
		return array_map(
			function ( $t ) use ( $names ) {
				return array(
					'player1' => $names[ $t['player1'] ] ?? '—',
					'player2' => null === $t['player2'] ? null : ( $names[ $t['player2'] ] ?? '—' ),
					'result'  => $t['result'],
				);
			},
			$tables
		);
	}

	private function public_standings( array $players ) {
		$ranked = TTRN_Swiss::ranked( $players );
		$out    = array();
		$rank   = 1;
		foreach ( $ranked as $p ) {
			if ( ! empty( $p['dropped'] ) ) {
				continue;
			}
			$out[] = array(
				'rank'     => $rank++,
				'name'     => $p['name'],
				'score'    => $p['score'],
				'buchholz' => round( $p['buchholz'], 1 ),
			);
		}
		return $out;
	}

	/**
	 * @return int Tournament post ID for this event, or 0 if none set
	 *         up yet.
	 */
	private function find_tournament_for_event( $event_id ) {
		$posts = get_posts(
			array(
				'post_type'      => TTRN_POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => '_ttrn_event_id', 'value' => (int) $event_id, 'compare' => '=' ),
				),
			)
		);
		return $posts ? (int) $posts[0] : 0;
	}

	public function get_event_tournament( WP_REST_Request $request ) {
		$event_id = (int) $request->get_param( 'id' );
		$tournament_id = $this->find_tournament_for_event( $event_id );
		if ( ! $tournament_id ) {
			return rest_ensure_response( array( 'exists' => false ) );
		}

		$players       = $this->get_players( $tournament_id );
		$rounds        = $this->get_rounds( $tournament_id );
		$current_round = (int) get_post_meta( $tournament_id, '_ttrn_current_round', true );
		$names         = $this->name_lookup( $players );

		return rest_ensure_response(
			array(
				'exists'         => true,
				'roundsTotal'    => (int) get_post_meta( $tournament_id, '_ttrn_rounds_total', true ),
				'currentRound'   => $current_round,
				'standings'      => $this->public_standings( $players ),
				'currentPairings' => $current_round > 0 && isset( $rounds[ $current_round - 1 ] )
					? $this->public_pairings( $rounds[ $current_round - 1 ], $names )
					: array(),
			)
		);
	}

	public function get_admin_state( WP_REST_Request $request ) {
		$tournament_id = (int) $request->get_param( 'id' );
		$post = get_post( $tournament_id );
		if ( ! $post || TTRN_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ttrn_not_found', __( 'Tournament not found.', 'tabletop-events-tournaments' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'id'           => $tournament_id,
				'roundsTotal'  => (int) get_post_meta( $tournament_id, '_ttrn_rounds_total', true ) ?: 4,
				'currentRound' => (int) get_post_meta( $tournament_id, '_ttrn_current_round', true ),
				'players'      => TTRN_Swiss::ranked( $this->get_players( $tournament_id ) ),
				'rounds'       => $this->get_rounds( $tournament_id ),
			)
		);
	}

	/**
	 * One endpoint for every player-list mutation (add / remove /
	 * import confirmed RSVPs) rather than three near-identical routes —
	 * they all just end with save_players(), so the branch here is
	 * simpler than the route registration would be.
	 */
	public function update_players( WP_REST_Request $request ) {
		$tournament_id = (int) $request->get_param( 'id' );
		$post = get_post( $tournament_id );
		if ( ! $post || TTRN_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ttrn_not_found', __( 'Tournament not found.', 'tabletop-events-tournaments' ), array( 'status' => 404 ) );
		}
		$params  = $request->get_json_params() ?: $request->get_body_params();
		$action  = sanitize_key( $params['action'] ?? '' );
		$players = $this->get_players( $tournament_id );

		// "drop" is the one player-list action allowed after the
		// tournament's started — it marks a player out rather than
		// deleting them, so their match history and past opponents'
		// Buchholz stay intact. Every other action requires the
		// player list to still be settable, i.e. before round 1.
		if ( 'drop' === $action ) {
			$player_id = (int) ( $params['player_id'] ?? 0 );
			foreach ( $players as &$p ) {
				if ( $p['id'] === $player_id ) {
					$p['dropped'] = true;
				}
			}
			unset( $p );
			$this->save_players( $tournament_id, $players );
			return rest_ensure_response( array( 'success' => true, 'players' => TTRN_Swiss::ranked( $players ) ) );
		}

		if ( (int) get_post_meta( $tournament_id, '_ttrn_current_round', true ) > 0 ) {
			return new WP_Error( 'ttrn_started', __( "Can't change the player list once the tournament's under way — drop a player instead if they've left.", 'tabletop-events-tournaments' ), array( 'status' => 400 ) );
		}

		if ( 'add' === $action ) {
			$name = sanitize_text_field( $params['name'] ?? '' );
			if ( ! $name ) {
				return new WP_Error( 'ttrn_invalid', __( 'Please enter a player name.', 'tabletop-events-tournaments' ), array( 'status' => 400 ) );
			}
			$next_id = 1;
			foreach ( $players as $p ) {
				$next_id = max( $next_id, $p['id'] + 1 );
			}
			$players[] = array(
				'id'        => $next_id,
				'name'      => $name,
				'score'     => 0,
				'byes'      => 0,
				'opponents' => array(),
				'dropped'   => false,
			);
		} elseif ( 'remove' === $action ) {
			$player_id = (int) ( $params['player_id'] ?? 0 );
			$players = array_values( array_filter( $players, function ( $p ) use ( $player_id ) { return $p['id'] !== $player_id; } ) );
		} elseif ( 'import_rsvps' === $action ) {
			$event_id = (int) get_post_meta( $tournament_id, '_ttrn_event_id', true );
			if ( ! $event_id || ! class_exists( 'TEC_Rsvp' ) ) {
				return new WP_Error( 'ttrn_no_rsvps', __( 'No linked event or RSVP data to import from.', 'tabletop-events-tournaments' ), array( 'status' => 400 ) );
			}
			$existing_names = array_map( function ( $p ) { return strtolower( $p['name'] ); }, $players );
			$next_id = 1;
			foreach ( $players as $p ) {
				$next_id = max( $next_id, $p['id'] + 1 );
			}
			foreach ( TEC_Rsvp::instance()->get_list( $event_id ) as $entry ) {
				if ( 'confirmed' !== $entry['status'] || in_array( strtolower( $entry['name'] ), $existing_names, true ) ) {
					continue;
				}
				$players[] = array(
					'id'        => $next_id,
					'name'      => $entry['name'],
					'score'     => 0,
					'byes'      => 0,
					'opponents' => array(),
					'dropped'   => false,
				);
				$existing_names[] = strtolower( $entry['name'] );
				$next_id++;
			}
		} else {
			return new WP_Error( 'ttrn_invalid', __( 'Unrecognised action.', 'tabletop-events-tournaments' ), array( 'status' => 400 ) );
		}

		$this->save_players( $tournament_id, $players );
		return rest_ensure_response( array( 'success' => true, 'players' => TTRN_Swiss::ranked( $players ) ) );
	}

	public function generate_round( WP_REST_Request $request ) {
		$tournament_id = (int) $request->get_param( 'id' );
		$post = get_post( $tournament_id );
		if ( ! $post || TTRN_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ttrn_not_found', __( 'Tournament not found.', 'tabletop-events-tournaments' ), array( 'status' => 404 ) );
		}

		$players       = $this->get_players( $tournament_id );
		$active        = array_filter( $players, function ( $p ) { return empty( $p['dropped'] ); } );
		$current_round = (int) get_post_meta( $tournament_id, '_ttrn_current_round', true );
		$rounds_total  = (int) get_post_meta( $tournament_id, '_ttrn_rounds_total', true ) ?: 4;

		if ( count( $active ) < 2 ) {
			return new WP_Error( 'ttrn_not_enough_players', __( 'Add at least two players before generating pairings.', 'tabletop-events-tournaments' ), array( 'status' => 400 ) );
		}
		if ( $current_round >= $rounds_total ) {
			return new WP_Error( 'ttrn_finished', __( 'All planned rounds have already been generated.', 'tabletop-events-tournaments' ), array( 'status' => 400 ) );
		}

		$rounds = $this->get_rounds( $tournament_id );
		if ( $current_round > 0 ) {
			$last_round = $rounds[ $current_round - 1 ] ?? array();
			foreach ( $last_round as $table ) {
				if ( null !== $table['player2'] && null === $table['result'] ) {
					return new WP_Error( 'ttrn_incomplete_round', __( 'Every table in the current round needs a result before starting the next one.', 'tabletop-events-tournaments' ), array( 'status' => 400 ) );
				}
			}
		}

		$generated = TTRN_Swiss::generate_round( $players );
		$rounds[]  = $generated['tables'];

		$this->save_players( $tournament_id, $generated['players'] );
		$this->save_rounds( $tournament_id, $rounds );
		update_post_meta( $tournament_id, '_ttrn_current_round', $current_round + 1 );

		return rest_ensure_response(
			array(
				'success'      => true,
				'currentRound' => $current_round + 1,
				'players'      => TTRN_Swiss::ranked( $generated['players'] ),
				'rounds'       => $rounds,
			)
		);
	}

	public function record_result( WP_REST_Request $request ) {
		$tournament_id = (int) $request->get_param( 'id' );
		$post = get_post( $tournament_id );
		if ( ! $post || TTRN_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ttrn_not_found', __( 'Tournament not found.', 'tabletop-events-tournaments' ), array( 'status' => 404 ) );
		}

		$params      = $request->get_json_params() ?: $request->get_body_params();
		$round_index = (int) ( $params['round'] ?? 0 ) - 1;
		$table_index = (int) ( $params['table'] ?? -1 );
		$result      = sanitize_key( $params['result'] ?? '' );

		if ( ! in_array( $result, array( 'p1', 'p2', 'draw' ), true ) ) {
			return new WP_Error( 'ttrn_invalid', __( 'Please choose a result.', 'tabletop-events-tournaments' ), array( 'status' => 400 ) );
		}

		$rounds = $this->get_rounds( $tournament_id );
		if ( ! isset( $rounds[ $round_index ][ $table_index ] ) ) {
			return new WP_Error( 'ttrn_no_table', __( 'That table could not be found.', 'tabletop-events-tournaments' ), array( 'status' => 404 ) );
		}
		$table = $rounds[ $round_index ][ $table_index ];
		if ( null === $table['player2'] ) {
			return new WP_Error( 'ttrn_is_bye', __( 'A bye table is already scored automatically.', 'tabletop-events-tournaments' ), array( 'status' => 400 ) );
		}

		$players = $this->get_players( $tournament_id );

		// A result can be corrected after the fact — undo the previous
		// scoring/opponent record for this table before applying the
		// new one, otherwise re-recording a result would double-count
		// it or leave a stale opponent entry behind.
		if ( null !== $table['result'] ) {
			$players = $this->undo_result( $players, $table['player1'], $table['player2'], $table['result'] );
		}

		$players = TTRN_Swiss::apply_result( $players, $table['player1'], $table['player2'], $result );
		$rounds[ $round_index ][ $table_index ]['result'] = $result;

		$this->save_players( $tournament_id, $players );
		$this->save_rounds( $tournament_id, $rounds );

		return rest_ensure_response( array( 'success' => true, 'players' => TTRN_Swiss::ranked( $players ), 'rounds' => $rounds ) );
	}

	private function undo_result( array $players, $player1_id, $player2_id, $previous_result ) {
		foreach ( $players as &$p ) {
			if ( $p['id'] === $player1_id ) {
				$this->remove_one_opponent( $p['opponents'], $player2_id );
				if ( 'p1' === $previous_result ) {
					$p['score'] -= 1;
				} elseif ( 'draw' === $previous_result ) {
					$p['score'] -= 0.5;
				}
			} elseif ( $p['id'] === $player2_id ) {
				$this->remove_one_opponent( $p['opponents'], $player1_id );
				if ( 'p2' === $previous_result ) {
					$p['score'] -= 1;
				} elseif ( 'draw' === $previous_result ) {
					$p['score'] -= 0.5;
				}
			}
		}
		unset( $p );
		return $players;
	}

	private function remove_one_opponent( array &$opponents, $opponent_id ) {
		$index = array_search( $opponent_id, $opponents, true );
		if ( false !== $index ) {
			array_splice( $opponents, $index, 1 );
		}
	}
}
