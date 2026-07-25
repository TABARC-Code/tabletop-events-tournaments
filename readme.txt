=== Tabletop Events Calendar — Tournaments ===
Contributors: tabarccode
Tags: events, calendar, tabletop, tournaments, swiss pairings
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Swiss pairings, live standings, and tie-break handling for competitive Tabletop Events Calendar events. Requires the Tabletop Events Calendar plugin.

== Description ==

Set up a tournament against one of your events, add players (typed in by hand, or pulled straight from that event's confirmed RSVPs in one click), and generate Swiss pairings round by round from wp-admin. Record each table's result as it comes in and standings — score plus a Buchholz tie-break — update immediately.

Running the tournament itself is a wp-admin job, same as review and listing moderation elsewhere in this family — it's an active, in-the-room task, not a one-off self-service edit. Players and spectators get a public, read-only standings and current-pairings view via a shortcode.

Pairing is a simplified greedy Swiss algorithm: rank by score then Buchholz, pair down the list, avoid rematches where there's a choice available, and hand a bye (an automatic win) to the lowest-ranked player who hasn't already had one if the field's odd. It won't out-think a full constraint solver on a huge field, but for a shop or convention-sized tournament it's more than enough, and it's easy to follow and trust.

One shortcode:

* `[tabletop_tournament_standings event="123"]` — current standings and the current round's pairings for one event.

== Installation ==

If you're grabbing this via GitHub's own "Download ZIP" button, rename the extracted folder to `tabletop-events-tournaments` first — GitHub names it `tabletop-events-tournaments-main`, which WordPress will happily install but won't recognise as the same plugin on your next update. Running `scripts/pack-plugin.sh` (needs `php` and `zip`) builds a zip with the folder already named correctly, ready for Plugins ▸ Add New ▸ Upload Plugin.

1. Install and activate **Tabletop Events Calendar** first.
2. Upload the `tabletop-events-tournaments` folder to `/wp-content/plugins/` and activate it.
3. Go to **Events Calendar ▸ Tournaments ▸ Add New**, link it to an event, and set the planned number of rounds.
4. Go to **Events Calendar ▸ Manage Pairings**, add players (or import confirmed RSVPs), and generate Round 1.
5. Add `[tabletop_tournament_standings event="123"]` to the event's page so players can follow along.

== Frequently Asked Questions ==

= Can players manage their own results? =

No — this is a wp-admin tool for whoever's running the tournament on the day. Everyone else just watches the public standings shortcode.

= What happens if I need to correct a result? =

Just pick a different option from the same dropdown — the previous result is undone (score and opponent history both) before the new one's applied, so it's safe to fix a mistake after the fact.

== Changelog ==

= 1.0.0 =
* Initial release: ttrn_tournament CPT, Swiss pairing engine with Buchholz tie-break, wp-admin pairings/results tool, public standings shortcode.
