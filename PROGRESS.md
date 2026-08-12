# PWI — Progress So Far

A household weigh-in tracker: a static `index.html` front end backed by
`api/api.php` on **MyDataWorld** (shared MySQL database with My Apps Hub,
T-Minus, and Shed Inventory). Published via GitHub Pages.

Originally built on Google Sheets + Apps Script (`Code.gs`); migrated off
that entirely in favor of MyDataWorld + SSO — see "Recent history" below and
[SETUP.md](SETUP.md) for the migration notes.

## Core tracking

- Per-user weigh-ins (Date, Weight) stored in `pwi_entries`, one row per
  person per date — resubmitting a date overwrites that row instead of
  duplicating it (`UNIQUE(user_id, entry_date)`).
- Blood Pressure and Blood Sugar, optional per entry.
- Gym and Walk check-ins (yes/no) plus Walk Distance in miles, optional per entry.
- Zumba check-in (yes/no), optional per entry.

## Login / access

- Shares the same login as My Apps Hub, T-Minus, and Shed Inventory — sign
  up once through the Hub. Supports SSO handoff (`?token=...`) from the Hub
  so a visitor who's already logged in there skips this app's login screen.
- Every entry is scoped server-side to the logged-in user's own `user_id` —
  the API itself never returns another user's rows, not just the UI.
- Login is remembered on-device via `localStorage`; "Log out" clears it.

## Dashboard

- Status banner showing the latest (or selected) entry's weight, trend indicator, and
  vitals/activity for that day.
- Trend indicator: gray dot for a first-ever entry, green/yellow/red dot based on
  distance from the person's running minimum weight, and a 🎉 for a new minimum.
- History table: date, weight, day-over-day trend arrow, delta, BP, sugar, activity
  (gym/walk/miles/zumba), status, and indicator — most recent first.
- Selecting a date on the check-in form prefills weight/BP/sugar/gym/walk/distance/zumba
  from that day's existing entry (if any), so you can amend a field without
  accidentally blanking the rest.

## Charts (Chart.js)

All charts share a **30-day / 90-day / 1-year** range toggle:

- **Weight** — line chart over the selected range.
- **Blood Sugar** — line chart, shown only when sugar data exists in range.
- **Blood Pressure** — line chart with systolic/diastolic series; point shape encodes
  WHO/ISH severity grade (Optimal → Grade 3), with a legend.
- **Gym Visits per Week**, **Walks per Week**, **Miles Walked per Week**,
  **Zumba Sessions per Week** — bar charts aggregated by ISO week number, including
  zero-activity weeks so gaps are visible.

## Setup / deployment

- `SETUP.md` walks through: running `api/schema.sql` against MyDataWorld,
  deploying `api/api.php`, wiring `API_URL` in `index.html`, publishing via
  GitHub Pages, and the one-time Google Sheets → MyDataWorld data migration.

## Recent history (most recent first)

1. Migrate off Google Sheets/Apps Script entirely: new `api/api.php` + MySQL
   schema (`pwi_entries`) on MyDataWorld, SSO login shared with My Apps Hub/
   T-Minus/Shed Inventory, entries re-keyed from free-text Name to
   `user_id`, all queries scoped server-side to the logged-in user (closes a
   gap where the old `list` action returned every person's rows to any
   authenticated request and relied on the UI to filter them out)
2. Show delta from running minimum in status text (e.g. "New Min", "Equal Min", ">Min 2.2") on the banner and history table
3. Add Zumba tracking with a weekly sessions chart
4. Add Gym/Walk/Walk Distance tracking with weekly activity charts
5. Rename save button to "Check in" and prefill fields for existing dates
6. Add 30/90/365-day range toggle for all charts
7. Fix BP legend overlapping History heading
8. Show WHO blood pressure severity as point shape on the BP chart
9. Add 30-day Blood Sugar and Blood Pressure charts
10. Lock Person field to the logged-in user
11. Add blood pressure/sugar tracking and simple login via Users sheet
12. Initial commit: PWI weight tracker

## Not yet done / ideas

- No way to edit/delete a past entry other than overwriting the same date.
- No data export (CSV/PDF).
