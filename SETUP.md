# PWI — Setup Guide

## Step 1: Confirm your Google Sheet

Your `OurWeightLossProgram` sheet already has the right layout — just confirm row 1 has these exact headers, columns A–D:

| A  | B    | C    | D      |
|----|------|------|--------|
| ID | Name | Date | Weight |

Copy the **Spreadsheet ID** from the URL:
`https://docs.google.com/spreadsheets/d/`**`THIS_PART`**`/edit`

Also note the **tab name** at the bottom of the sheet (e.g. `Sheet1`) — you'll need it below.

---

## Step 2: Deploy the Apps Script

1. In your Sheet, go to **Extensions → Apps Script**
2. Delete the default `myFunction` code
3. Paste in the contents of `Code.gs` from this folder
4. Replace `YOUR_SPREADSHEET_ID` on line 2 with the ID you copied above
5. Replace `Sheet1` on line 3 with your actual tab name, if different
6. Click **Save** (floppy disk icon)
7. Click **Deploy → New deployment**
8. Click the gear icon next to "Type" and choose **Web app**
9. Set:
   - **Execute as:** Me
   - **Who has access:** Anyone
10. Click **Deploy**
11. Click **Authorize access** and follow the Google prompts
12. Copy the **Web app URL** — it looks like:
    `https://script.google.com/macros/s/ABC123.../exec`

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
- To add a new person, choose **+ Add new person** at the bottom of the Person dropdown, type their name, and save their first weigh-in as normal — no need to edit the sheet directly.
- A person's very first-ever weigh-in shows a neutral **Start** indicator (gray dot) since there's no prior minimum to compare against yet. Every entry after that uses the normal green/yellow/red/NewMin rules.
