/**
 * Tabletop Events Calendar — Tournaments — public standings widget.
 * Read-only: current standings table plus the current round's
 * pairings, refetched on load only (a spectator can just refresh the
 * page between rounds — this isn't trying to be a live scoreboard).
 */
(function () {
	'use strict';

	var REST = ( window.TTRN_STANDINGS && window.TTRN_STANDINGS.restUrl ) || '/wp-json/ttrn/v1';
	var EVENT_ID = ( window.TTRN_STANDINGS && window.TTRN_STANDINGS.eventId ) || 0;

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-ttrn-standings]' ).forEach( init );
	} );

	function init( root ) {
		root.innerHTML = '<div class="ttrn-empty">Loading…</div>';

		fetch( REST + '/event/' + EVENT_ID )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( ! data.exists ) {
					root.innerHTML = '<p class="ttrn-empty">No tournament has been set up for this event yet.</p>';
					return;
				}
				root.innerHTML = headingHtml( data ) + pairingsHtml( data ) + standingsHtml( data );
			} )
			.catch( function () {
				root.innerHTML = '<div class="ttrn-empty">Could not load the tournament.</div>';
			} );
	}

	function headingHtml( data ) {
		return (
			'<p class="ttrn-round-heading">Round ' + data.currentRound + ' of ' + data.roundsTotal + '</p>'
		);
	}

	function pairingsHtml( data ) {
		if ( ! data.currentPairings || ! data.currentPairings.length ) {
			return '<p class="ttrn-empty">Pairings haven\'t been posted yet.</p>';
		}
		var rows = data.currentPairings.map( function ( t, i ) {
			var resultText = resultLabel( t );
			return (
				'<tr>' +
					'<td>' + ( i + 1 ) + '</td>' +
					'<td>' + escapeHtml( t.player1 ) + '</td>' +
					'<td>' + ( t.player2 ? escapeHtml( t.player2 ) : '<em>Bye</em>' ) + '</td>' +
					'<td>' + resultText + '</td>' +
				'</tr>'
			);
		} ).join( '' );

		return (
			'<table class="ttrn-table ttrn-pairings">' +
				'<thead><tr><th>Table</th><th>Player 1</th><th>Player 2</th><th>Result</th></tr></thead>' +
				'<tbody>' + rows + '</tbody>' +
			'</table>'
		);
	}

	function resultLabel( t ) {
		if ( null === t.player2 ) return 'Bye win';
		if ( 'p1' === t.result ) return escapeHtml( t.player1 ) + ' won';
		if ( 'p2' === t.result ) return escapeHtml( t.player2 ) + ' won';
		if ( 'draw' === t.result ) return 'Draw';
		return '—';
	}

	function standingsHtml( data ) {
		if ( ! data.standings || ! data.standings.length ) {
			return '';
		}
		var rows = data.standings.map( function ( s ) {
			return (
				'<tr>' +
					'<td>' + s.rank + '</td>' +
					'<td>' + escapeHtml( s.name ) + '</td>' +
					'<td>' + s.score + '</td>' +
					'<td>' + s.buchholz + '</td>' +
				'</tr>'
			);
		} ).join( '' );

		return (
			'<h3 class="ttrn-standings-heading">Standings</h3>' +
			'<table class="ttrn-table ttrn-standings">' +
				'<thead><tr><th>#</th><th>Player</th><th>Score</th><th>Buchholz</th></tr></thead>' +
				'<tbody>' + rows + '</tbody>' +
			'</table>'
		);
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str == null ? '' : str );
		return div.innerHTML;
	}
})();
