# PWI — Setup Guide

## Step 1: Confirm your Google Sheet

Your `OurWeightLossProgram` sheet already has the right layout — just confirm row 1 has these exact headers, columns A–J:

| A  | B    | C    | D      | E              | F           | G   | H    | I             | J      |
|----|------|------|--------|----------------|-------------|-----|------|---------------|--------|
| ID | Name | Date | Weight | Blood Pressure | Blood Sugar | Gym | Walk | Walk Distance | Zumba  |

Gym, Walk, and Zumba are recorded as checkboxes in the app (TRUE/FALSE). To make columns G, H, and J show as checkboxes in the sheet itself, select the column and use **Insert → Checkbox** — not required, but it keeps manual edits consistent with what the app writes. Walk Distance is a plain number in miles.

Copy the **Spreadsheet ID** from the URL:
`https://docs.google.com/spreadsheets/d/`**`THIS_PART`**`/edit`

Also note the **tab name** at the bottom of the sheet (e.g. `Sheet1`) — you'll need it below.

### Add a "Users" tab

Add a second tab named exactly **`Users`** with these headers, columns A–C:

| A  | B    | C  |
|----|------|----|
| ID | Name | PW |

Add one row per person, e.g. `1, Dennis, ****`, `2, Elaine, ****`. The `Name` here must exactly match (case-sensitive) the name used on the weigh-in tab. This is the **only** place logins/accounts are managed — the app itself no longer lets anyone create a user. Passwords are stored in plain text on this tab, so treat it like any other simple/shared-household password — it's not meant to protect sensitive data.

---

## Step 2: Deploy the Apps Script

1. In your Sheet, go to **Extensions → Apps Script**
2. Delete the default `myFunction` code
3. Paste in the contents of `Code.gs` from this folder
4. Replace `YOUR_SPREADSHEET_ID` on line 2 with the ID you copied above
5. Replace `Sheet1` on line 3 with your actual weigh-in tab name, if different
6. Leave `Users` on line 4 as-is unless you named that tab something else
7. Click **Save** (floppy disk icon)
8. Click **Deploy → New deployment**
9. Click the gear icon next to "Type" and choose **Web app**
10. Set:
    - **Execute as:** Me
    - **Who has access:** Anyone
11. Click **Deploy**
12. Click **Authorize access** and follow the Google prompts
13. Copy the **Web app URL** — it looks like:
    `https://script.google.com/macros/s/ABC123.../exec`

If you already had this deployed before, use **Deploy → Manage deployments → edit (pencil) → New version → Deploy** instead, so the existing URL keeps working.

---

## Step 3: Configure the app

Open `index.html` in a text editor and find this line near the top of the `<script>` block:

```js
const SCRIPT_URL = 'YOUR_APPS_SCRIPT_URL';
```

Replace `YOUR_APPS_SCRIPT_URL` with the URL you copied in Step 2. Save the file.

---

## Step 4: Publish to GitHub Pages

1. Create a new **public** repository on GitHub (e.g. `pwi`)
2. Push this folder's contents to the `main` branch:
   ```
   git init
   git add .
   git commit -m "Initial commit"
   git remote add origin https://github.com/YOUR_USERNAME/pwi.git
   git push -u origin main
   ```
3. In the repo on GitHub, go to **Settings → Pages**
4. Under "Source", select **Deploy from a branch → main → / (root)**
5. Click **Save** — your app will be live at:
   `https://YOUR_USERNAME.github.io/pwi/`

---

## Notes

- The Google Sheet is the source of truth — you can view/edit data there anytime.
- The Apps Script runs as your Google account, so the Sheet stays private even though the app URL is public.
- If you redeploy the Apps Script after changes, the URL stays the same — no need to update `index.html`.
- Submitting a weight for a person/date that already has an entry **overwrites** that row rather than creating a duplicate.
- The app no longer lets anyone add a new person from the UI. To add someone, add a row for them on the **Users** tab (Name + PW) — they'll then appear in both the login screen and the Person dropdown.
- Logging in requires picking your name and entering the matching password from the **Users** tab. A successful login is remembered on that device (via `localStorage`) so you won't have to log in every visit — use **Log out** to clear it.
- Blood Pressure, Blood Sugar, Gym, Walk, Walk Distance, and Zumba are all optional on each weigh-in; leave them blank/unchecked to skip.
- A person's very first-ever weigh-in shows a neutral **Start** indicator (gray dot) since there's no prior minimum to compare against yet. Every entry after that uses the normal green/yellow/red/NewMin rules.
