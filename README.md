# Tabletop Events Calendar — Tournaments

Swiss pairings and standings for competitive [Tabletop Events Calendar](https://github.com/TABARC-Code/tabletop-gaming-events-calendar) events.

Requires [Tabletop Events Calendar](https://github.com/TABARC-Code/tabletop-gaming-events-calendar) — this plugin does nothing without it.

## What it does

- Set up a tournament against an event, add players by hand or import them straight from that event's confirmed RSVPs, and generate Swiss pairings round by round from wp-admin.
- Score, opponent history, and a Buchholz tie-break are all tracked automatically; a result can be corrected after the fact without throwing the standings off.
- Odd number of players in a round gets a bye (an automatic win) — handed to the lowest-ranked player who hasn't already had one, not always whoever's dead last.
- `[tabletop_tournament_standings event="123"]` — a public, read-only view of the current standings and this round's pairings, so players don't need to keep asking the organiser.

## Why wp-admin rather than another public magic link

Running a live Swiss tournament is an active, in-the-room job — pairing a round, taking results as they come in — not a one-off self-service edit like everything else in this family. It gets the same treatment as review and listing moderation elsewhere: a wp-admin tool, authenticated the normal WordPress way. The public side (standings, current pairings) stays a plain read-only shortcode with no login involved.

## On the pairing algorithm

It's a deliberately simplified greedy Swiss pairer, not a full constraint solver: rank by score then Buchholz, pair down the list, skip a rematch if there's an alternative available. For a shop or convention-sized field over a handful of rounds that's plenty, and it's a lot easier to read, trust, and debug than "proper" Swiss-pairing software would be.

Not yet wired into the core plugin's ranking-circuit tags (ITC/UKTC/etc.) — that's a natural next step once there's real usage to design against, rather than guessing at a scoring format nobody's asked for yet.

## Installing

Install and activate the core calendar first. Then either upload a zip via Plugins ▸ Add New, or drop the `tabletop-events-tournaments` folder into `wp-content/plugins/`. GitHub's "Download ZIP" names the folder `...-main`, which WordPress won't treat as the same plugin on update — run `scripts/pack-plugin.sh` to build a correctly named zip instead.

## Other pieces of the set

Every plugin here stands alone and only ever needs the [core calendar](https://github.com/TABARC-Code/Tabletop-Gaming-Events-Calendar) — none depend on each other. Take what's useful:

- [Tabletop-Events-Discord-rsvp-Repo](https://github.com/TABARC-Code/Tabletop-Events-Discord-rsvp-Repo) — RSVP from a Discord button
- [Tabletop-Events-Reviews](https://github.com/TABARC-Code/Tabletop-Events-Reviews) — RSVP-gated event and organiser reviews
- [Tabletop-Events-Carpool](https://github.com/TABARC-Code/Tabletop-Events-Carpool) — Lift-share board per event
- [tabletop-events-resources](https://github.com/TABARC-Code/tabletop-events-resources) — Terrain, tables and dice to borrow

## Licence

GPL v2 or later.
