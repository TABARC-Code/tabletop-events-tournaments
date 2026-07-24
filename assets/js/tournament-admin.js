/**
 * Tabletop Events Calendar — Tournaments — wp-admin pairing/results
 * tool. Talks to /ttrn/v1/admin/* using WordPress's own REST nonce
 * (sent as X-WP-Nonce) rather than a public magic-link token — this
 * page only ever loads for someone who's already logged into
 * wp-admin with manage_options.
 */
(function () {
	'use strict';

	var REST = ( window.TTRN_ADMIN && window.TTRN_ADMIN.restUrl ) || '/wp-json/ttrn/v1';
	var NONCE = ( window.TTRN_ADMIN && window.TTRN_ADMIN.nonce ) || '';
	var TOURNAMENT_ID = ( window.TTRN_ADMIN && window.TTRN_ADMIN.tournamentId ) || 0;

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '[data-ttrn-admin]' );
		if ( !root || !TOURNAMENT_ID ) return;
		load( root );
	} );

	function load( root ) {
		root.innerHTML = '<p>Loading…</p>';
		apiGet( '/admin/' + TOURNAMENT_ID )
			.then( function ( state ) { render( root, state ); } )
			.catch( function ( err ) { root.innerHTML = '<p>' + escapeHtml( err.message ) + '</p>'; } );
	}

	function render( root, state ) {
		var started = state.currentRound > 0;

		root.innerHTML =
			messageHtml() +
			( started ? '' : playerFormHtml() ) +
			playerListHtml( state.players, started ) +
			generateButtonHtml( state ) +
			roundsHtml( state ) +
			standingsHtml( state.players );

		bind( root, state );
	}

	function messageHtml() {
		return '<div class="ttrn-admin-msg"></div>';
	}

	function playerFormHtml() {
		return (
			'<div class="ttrn-admin-panel">' +
				'<h2>Players</h2>' +
				'<form class="ttrn-add-player-form">' +
					'<input type="text" name="name" placeholder="Player name" required>' +
					'<button type="submit" class="button">Add Player</button>' +
					'<button type="button" class="button ttrn-import-rsvps">Import Confirmed RSVPs</button>' +
				'</form>' +
			'</div>'
		);
	}

	function playerListHtml( players, started ) {
		if ( ! players.length ) {
			return '<p>No players added yet.</p>';
		}
		var rows = players.map( function ( p ) {
			return (
				'<tr' + ( p.dropped ? ' class="ttrn-dropped"' : '' ) + '>' +
					'<td>' + escapeHtml( p.name ) + '</td>' +
					'<td>' + p.score + '</td>' +
					( started
						? '<td>' + ( p.dropped ? 'Dropped' : '<button type="button" class="button-link ttrn-drop" data-player="' + p.id + '">Drop</button>' ) + '</td>'
						: '<td><button type="button" class="button-link ttrn-remove" data-player="' + p.id + '">Remove</button></td>'
					) +
				'</tr>'
			);
		} ).join( '' );

		return (
			'<table class="widefat ttrn-admin-table">' +
				'<thead><tr><th>Player</th><th>Score</th><th></th></tr></thead>' +
				'<tbody>' + rows + '</tbody>' +
			'</table>'
		);
	}

	function generateButtonHtml( state ) {
		var active = state.players.filter( function ( p ) { return !p.dropped; } ).length;
		if ( state.currentRound >= state.roundsTotal ) {
			return '<p><strong>All ' + state.roundsTotal + ' rounds have been generated.</strong></p>';
		}
		return (
			'<p><button type="button" class="button button-primary ttrn-generate"' + ( active < 2 ? ' disabled' : '' ) + '>' +
				'Generate Round ' + ( state.currentRound + 1 ) +
			'</button></p>'
		);
	}

	function roundsHtml( state ) {
		if ( ! state.rounds.length ) {
			return '';
		}
		var names = {};
		state.players.forEach( function ( p ) { names[ p.id ] = p.name; } );

		var html = '';
		state.rounds.forEach( function ( tables, roundIdx ) {
			var rows = tables.map( function ( t, tableIdx ) {
				if ( null === t.player2 ) {
					return '<tr><td>' + ( tableIdx + 1 ) + '</td><td>' + escapeHtml( names[ t.player1 ] || '—' ) + '</td><td><em>Bye</em></td><td>Automatic win</td></tr>';
				}
				return (
					'<tr>' +
						'<td>' + ( tableIdx + 1 ) + '</td>' +
						'<td>' + escapeHtml( names[ t.player1 ] || '—' ) + '</td>' +
						'<td>' + escapeHtml( names[ t.player2 ] || '—' ) + '</td>' +
						'<td>' +
							'<select class="ttrn-result" data-round="' + ( roundIdx + 1 ) + '" data-table="' + tableIdx + '">' +
								'<option value="">— pick a result —</option>' +
								'<option value="p1"' + ( 'p1' === t.result ? ' selected' : '' ) + '>' + escapeHtml( names[ t.player1 ] || 'Player 1' ) + ' won</option>' +
								'<option value="p2"' + ( 'p2' === t.result ? ' selected' : '' ) + '>' + escapeHtml( names[ t.player2 ] || 'Player 2' ) + ' won</option>' +
								'<option value="draw"' + ( 'draw' === t.result ? ' selected' : '' ) + '>Draw</option>' +
							'</select>' +
						'</td>' +
					'</tr>'
				);
			} ).join( '' );

			html +=
				'<h2>Round ' + ( roundIdx + 1 ) + '</h2>' +
				'<table class="widefat ttrn-admin-table">' +
					'<thead><tr><th>Table</th><th>Player 1</th><th>Player 2</th><th>Result</th></tr></thead>' +
					'<tbody>' + rows + '</tbody>' +
				'</table>';
		} );
		return html;
	}

	function standingsHtml( players ) {
		if ( ! players.length ) {
			return '';
		}
		var rows = players.map( function ( p, i ) {
			return '<tr><td>' + ( i + 1 ) + '</td><td>' + escapeHtml( p.name ) + '</td><td>' + p.score + '</td><td>' + ( p.buchholz || 0 ).toFixed( 1 ) + '</td></tr>';
		} ).join( '' );
		return (
			'<h2>Standings</h2>' +
			'<table class="widefat ttrn-admin-table">' +
				'<thead><tr><th>#</th><th>Player</th><th>Score</th><th>Buchholz</th></tr></thead>' +
				'<tbody>' + rows + '</tbody>' +
			'</table>'
		);
	}

	function bind( root, state ) {
		var addForm = root.querySelector( '.ttrn-add-player-form' );
		if ( addForm ) {
			addForm.addEventListener( 'submit', function ( evt ) {
				evt.preventDefault();
				var input = addForm.querySelector( '[name="name"]' );
				var name = input.value.trim();
				if ( ! name ) return;
				apiPost( '/admin/' + TOURNAMENT_ID + '/players', { action: 'add', name: name } )
					.then( function () { load( root ); } )
					.catch( function ( err ) { showMessage( root, err.message ); } );
			} );
		}

		var importBtn = root.querySelector( '.ttrn-import-rsvps' );
		if ( importBtn ) {
			importBtn.addEventListener( 'click', function () {
				apiPost( '/admin/' + TOURNAMENT_ID + '/players', { action: 'import_rsvps' } )
					.then( function () { load( root ); } )
					.catch( function ( err ) { showMessage( root, err.message ); } );
			} );
		}

		root.querySelectorAll( '.ttrn-remove' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				apiPost( '/admin/' + TOURNAMENT_ID + '/players', { action: 'remove', player_id: parseInt( btn.dataset.player, 10 ) } )
					.then( function () { load( root ); } )
					.catch( function ( err ) { showMessage( root, err.message ); } );
			} );
		} );

		root.querySelectorAll( '.ttrn-drop' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Drop this player from the tournament?' ) ) return;
				apiPost( '/admin/' + TOURNAMENT_ID + '/players', { action: 'drop', player_id: parseInt( btn.dataset.player, 10 ) } )
					.then( function () { load( root ); } )
					.catch( function ( err ) { showMessage( root, err.message ); } );
			} );
		} );

		var generateBtn = root.querySelector( '.ttrn-generate' );
		if ( generateBtn ) {
			generateBtn.addEventListener( 'click', function () {
				apiPost( '/admin/' + TOURNAMENT_ID + '/generate', {} )
					.then( function () { load( root ); } )
					.catch( function ( err ) { showMessage( root, err.message ); } );
			} );
		}

		root.querySelectorAll( '.ttrn-result' ).forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				if ( ! select.value ) return;
				apiPost( '/admin/' + TOURNAMENT_ID + '/result', {
					round: parseInt( select.dataset.round, 10 ),
					table: parseInt( select.dataset.table, 10 ),
					result: select.value,
				} )
					.then( function () { load( root ); } )
					.catch( function ( err ) { showMessage( root, err.message ); } );
			} );
		} );
	}

	function showMessage( root, message ) {
		var el = root.querySelector( '.ttrn-admin-msg' );
		if ( el ) el.textContent = message;
	}

	function apiGet( path ) {
		return fetch( REST + path, { headers: { 'X-WP-Nonce': NONCE } } )
			.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
			.then( function ( result ) {
				if ( !result.ok ) throw new Error( ( result.body && result.body.message ) || 'Something went wrong.' );
				return result.body;
			} );
	}

	function apiPost( path, data ) {
		return fetch( REST + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
			body: JSON.stringify( data ),
		} )
			.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
			.then( function ( result ) {
				if ( !result.ok ) throw new Error( ( result.body && result.body.message ) || 'Something went wrong.' );
				return result.body;
			} );
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str == null ? '' : str );
		return div.innerHTML;
	}
})();
