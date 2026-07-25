<?php
/**
 * A small, deliberately simplified Swiss pairing engine. Real Swiss
 * tournament software solves pairing as a constraint problem (no
 * rematches, balanced colours, minimal score gaps) with a proper
 * matching algorithm. This is a greedy version instead: sort by score
 * then Buchholz, walk down the list pairing neighbours, and if that
 * would repeat a match already played, look a little further down the
 * list for someone who hasn't been faced yet. For a village hall or
 * game-shop tournament of a few dozen players and half a dozen rounds,
 * that's plenty — and it's a lot easier to read and trust than a full
 * blossom-algorithm implementation would be.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTRN_Swiss {

	/**
	 * @param array $players Each: array( id, name, score, byes,
	 *        opponents (array of ids faced), dropped (bool) ).
	 * @return array $players with 'buchholz' set — sum of the current
	 *         score of every opponent faced so far, the standard Swiss
	 *         tie-break for "strength of schedule".
	 */
	public static function with_buchholz( array $players ) {
		$score_by_id = array();
		foreach ( $players as $p ) {
			$score_by_id[ $p['id'] ] = $p['score'];
		}
		foreach ( $players as &$p ) {
			$total = 0.0;
			foreach ( $p['opponents'] as $opp_id ) {
				$total += $score_by_id[ $opp_id ] ?? 0;
			}
			$p['buchholz'] = $total;
		}
		unset( $p );
		return $players;
	}

	/**
	 * @return array $players sorted for standings/pairing: score desc,
	 *         then Buchholz desc, then name asc for stable ordering.
	 */
	public static function ranked( array $players ) {
		$players = self::with_buchholz( $players );
		usort(
			$players,
			function ( $a, $b ) {
				if ( $a['score'] !== $b['score'] ) {
					return $b['score'] <=> $a['score'];
				}
				if ( $a['buchholz'] !== $b['buchholz'] ) {
					return $b['buchholz'] <=> $a['buchholz'];
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		return $players;
	}

	/**
	 * @return array {
	 *     @type array  $tables List of array( player1 => id, player2 =>
	 *                  id|null, result => null ). player2 null means a bye.
	 *     @type array  $players Updated player list — bye recipient's
	 *                  score/byes are already applied.
	 * }
	 */
	public static function generate_round( array $players ) {
		$active = array_values( array_filter( $players, function ( $p ) { return empty( $p['dropped'] ); } ) );
		$active = self::ranked( $active );

		$bye_player_id = null;
		if ( count( $active ) % 2 === 1 ) {
			// Lowest-ranked player who hasn't already had a bye — walk
			// up from the bottom of the ranked list rather than always
			// picking dead last, so byes don't unfairly always land on
			// whoever happens to be at the very bottom this round.
			for ( $i = count( $active ) - 1; $i >= 0; $i-- ) {
				if ( empty( $active[ $i ]['byes'] ) ) {
					$bye_player_id = $active[ $i ]['id'];
					break;
				}
			}
			if ( null === $bye_player_id ) {
				// Everyone's already had one — fall back to dead last.
				$bye_player_id = end( $active )['id'];
			}
			$active = array_values( array_filter( $active, function ( $p ) use ( $bye_player_id ) { return $p['id'] !== $bye_player_id; } ) );
		}

		$tables       = array();
		$unpaired     = $active;
		$extra_byes   = array();
		while ( count( $unpaired ) > 0 ) {
			$p1 = array_shift( $unpaired );
			if ( ! $unpaired ) {
				// $active is always even by this point — the odd-player
				// bye above already made sure of that — so this should
				// never actually run. It's kept as a safety net rather
				// than trusting that invariant to hold forever: if it
				// ever does run, score it as a second bye rather than
				// leaving a table that displays "Automatic win" (see
				// TTRN_Rest::public_pairings()) without actually paying
				// out the score to match.
				$tables[]     = array( 'player1' => $p1['id'], 'player2' => null, 'result' => 'p1' );
				$extra_byes[] = $p1['id'];
				break;
			}

			$opponent_index = 0;
			for ( $i = 0; $i < count( $unpaired ); $i++ ) {
				if ( ! in_array( $unpaired[ $i ]['id'], $p1['opponents'], true ) ) {
					$opponent_index = $i;
					break;
				}
			}
			$p2 = array_splice( $unpaired, $opponent_index, 1 )[0];

			$tables[] = array( 'player1' => $p1['id'], 'player2' => $p2['id'], 'result' => null );
		}

		if ( $bye_player_id ) {
			$extra_byes[] = $bye_player_id;
			$tables[]     = array( 'player1' => $bye_player_id, 'player2' => null, 'result' => 'p1' );
		}

		foreach ( $players as &$p ) {
			if ( in_array( $p['id'], $extra_byes, true ) ) {
				$p['score'] += 1;
				$p['byes']   = (int) ( $p['byes'] ?? 0 ) + 1;
			}
		}
		unset( $p );

		return array( 'tables' => $tables, 'players' => $players );
	}

	/**
	 * Applies one table's result to the player list: updates score and
	 * records the opponent faced (both directions) so future pairing
	 * and Buchholz both see it. A bye table (player2 === null) is
	 * scored automatically at generation time and shouldn't be
	 * re-applied here.
	 */
	public static function apply_result( array $players, $player1_id, $player2_id, $result ) {
		foreach ( $players as &$p ) {
			if ( $p['id'] === $player1_id ) {
				$p['opponents'][] = $player2_id;
				if ( 'p1' === $result ) {
					$p['score'] += 1;
				} elseif ( 'draw' === $result ) {
					$p['score'] += 0.5;
				}
			} elseif ( $p['id'] === $player2_id ) {
				$p['opponents'][] = $player1_id;
				if ( 'p2' === $result ) {
					$p['score'] += 1;
				} elseif ( 'draw' === $result ) {
					$p['score'] += 0.5;
				}
			}
		}
		unset( $p );
		return $players;
	}
}
