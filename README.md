# PWI Weight Tracker

A household weigh-in tracker: log weight, BP, blood sugar, gym/walk/Zumba,
and steps, and see a color-coded trend readout plus weekly activity charts.
Private per logged-in account — nobody can see another person's entries.

See [SETUP.md](SETUP.md) for deployment (database, API, GitHub Pages, and
migrating off the old Google Sheets version).

## Files

- `index.html` — the whole app (HTML, CSS, and JS in one file)
- `api/api.php` — backend: login/sessions + entry CRUD, scoped to the
  logged-in user's own rows only
- `api/schema.sql` — database tables (`users`, `sessions`, `pwi_entries`)
- `api/config.example.php` — copy to `api/config.php` with real DB
  credentials (gitignored)

## Accounts

No signup here — this shares the same login as My Apps Hub, T-Minus, and
Shed Inventory (all on MyDataWorld). Sign up through My Apps Hub first.

## Single sign-on

If `apps.sso_enabled = 1` is set for `pwi-weight-tracker` in My Apps Hub's
database, launching this app from the Hub skips the login screen entirely
(`?token=...` handoff).
