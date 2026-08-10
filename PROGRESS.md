# PWI — Progress So Far

A lightweight household weigh-in tracker: a static `index.html` front end backed by a
Google Apps Script (`Code.gs`) that reads/writes a Google Sheet. Deployed as a
Web App URL and published via GitHub Pages.

## Core tracking

- Per-person weigh-ins (Name, Date, Weight) stored in a Google Sheet, one row per
  person per date — resubmitting a date overwrites that row instead of duplicating it.
- Blood Pressure and Blood Sugar, optional per entry.
- Gym and Walk check-ins (yes/no) plus Walk Distance in miles, optional per entry.
- Zumba check-in (yes/no), optional per entry.
- A "Person" tab (Users sheet: ID, Name, PW) is the only place accounts are managed —
  the app itself can't create new people.

## Login / access

- Simple login screen: pick your name, enter your password (from the Users sheet).
- Login is remembered on-device via `localStorage`; "Log out" clears it.
- The Person field is locked to whoever is logged in — no editing someone else's data.

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

- `SETUP.md` walks through: confirming the sheet layout, adding the Users tab,
  deploying the Apps Script as a Web App, wiring `SCRIPT_URL` in `index.html`, and
  publishing via GitHub Pages.
- Deploying a new Apps Script version keeps the same Web App URL, so `index.html`
  never needs updating for backend-only changes.

## Recent history (most recent first)

1. Show delta from running minimum in status text (e.g. "New Min", "Equal Min", ">Min 2.2") on the banner and history table
2. Add Zumba tracking with a weekly sessions chart
3. Add Gym/Walk/Walk Distance tracking with weekly activity charts
4. Rename save button to "Check in" and prefill fields for existing dates
5. Add 30/90/365-day range toggle for all charts
6. Fix BP legend overlapping History heading
7. Show WHO blood pressure severity as point shape on the BP chart
8. Add 30-day Blood Sugar and Blood Pressure charts
9. Lock Person field to the logged-in user
10. Add blood pressure/sugar tracking and simple login via Users sheet
11. Initial commit: PWI weight tracker

## Not yet done / ideas

- No way to edit/delete a past entry other than overwriting the same date.
- No data export (CSV/PDF).
- No multi-person comparison view (each person only sees their own data).
