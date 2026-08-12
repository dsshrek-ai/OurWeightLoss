# PWI Weight Tracker — Setup

Log daily weigh-ins — plus BP, blood sugar, gym/walk/Zumba, and steps — and
see how you're tracking, with a color-coded readout and weekly activity
charts. Backed by **MyDataWorld**, the same shared database as My Apps Hub,
T-Minus, and Shed Inventory — one login works everywhere. Data is private
per account: nobody can see another person's entries.

See the "Data migration" section below if you're moving off the old Google
Sheets version — this replaces it entirely.

## 1. Update the database

Open **phpMyAdmin**, select the MyDataWorld database, go to the **SQL** tab,
and run everything in [`api/schema.sql`](api/schema.sql). It's safe to run
even if `users`/`sessions` already exist (from My Apps Hub/T-Minus/Shed
Inventory) — those use `CREATE TABLE IF NOT EXISTS`. This adds one new
table, `pwi_entries`.

## 2. Deploy the API

1. Copy `api/config.example.php` to `api/config.php` and fill in the real
   `DB_NAME`, `DB_USER`, `DB_PASS` — same credentials as your other
   MyDataWorld apps.
2. Upload the whole `api/` folder via FTP/File Manager — e.g.
   `seniorfamily.org/pwi-api/`.

## 3. Point the app at your API

In `index.html`:

```js
const API_URL = 'https://seniorfamily.org/pwi-api/api.php';
```

## 4. Publish

Push this repo (already set up for GitHub Pages) or upload `index.html`
directly to seniorfamily.org — your call, same as the other MyDataWorld
apps.

## 5. Accounts

There's no separate signup here — everyone logs in with the same account
they use for **My Apps Hub**. If you haven't already, sign up there first
with the email you want tied to your weigh-ins.

## 6. Register with My Apps Hub (optional but recommended)

The Hub already has a `pwi-weight-tracker` row. Once this is deployed, mark
it SSO-enabled so logging into the Hub also logs you into PWI automatically:

```sql
UPDATE apps SET sso_enabled = 1 WHERE app_key = 'pwi-weight-tracker';
```

(my-apps-hub's own `api/schema.sql` seed list should be updated to match, so
a future re-run doesn't flip it back to 0.)

## Data migration (Google Sheets → MyDataWorld)

If you're moving existing history out of the old `OurWeightLossProgram`
sheet:

1. Make sure everyone who has historical rows already has a MyDataWorld
   account (Step 5 above) — you need their `users.id` to re-key their data.
2. Export the weigh-in tab as CSV (**File → Download → Comma-separated
   values**).
3. Import that CSV into a staging table in phpMyAdmin (**Import** tab, or
   `LOAD DATA LOCAL INFILE`), then run an `INSERT ... SELECT` that joins the
   staging table's `Name` column to the right `users.id` (via each person's
   email) and inserts into `pwi_entries`. See the project owner's own copy
   of this migration script for the exact `Name → email` mapping used —
   it's specific to who was in the sheet, not something to template here.
4. Spot-check a few rows in `pwi_entries` against the original sheet, then
   the old Apps Script deployment and `Code.gs` can be retired.

## Notes

- Submitting a check-in for a date that already has one **overwrites** it
  (`UNIQUE(user_id, entry_date)` in the schema) rather than creating a
  duplicate — same behavior as before.
- A person's very first-ever weigh-in shows a neutral **Start** indicator
  (gray dot) since there's no prior minimum to compare against yet.
- Blood Pressure, Blood Sugar, Gym, Walk, Walk Distance, Steps, and Zumba
  are all optional on each check-in.
