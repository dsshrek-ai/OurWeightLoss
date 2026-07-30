// ─── CONFIG ───────────────────────────────────────────────────────────────────
const SHEET_ID         = '1LKSZpQ0zwlYM5oMce5QDty3GsS41sEIEA_OIXFFcpOE';   // ← paste your Sheet ID here
const SHEET_NAME       = 'Sheet1';                 // ← tab name with weigh-in entries
const USERS_SHEET_NAME = 'Users';                  // ← tab name with the Name/PW login rows

// ─── ROUTER ──────────────────────────────────────────────────────────────────
function doGet(e) {
  const action = e.parameter.action;
  const ss     = SpreadsheetApp.openById(SHEET_ID);
  const sheet  = ss.getSheetByName(SHEET_NAME);

  let result;
  try {
    switch (action) {
      case 'users': result = getUserNames(ss);                                                         break;
      case 'login': result = login(ss, e.parameter.user, e.parameter.pw);                               break;
      case 'list':  result = requireLogin(ss, e.parameter.user, e.parameter.pw, () => getAllRows(sheet)); break;
      case 'save':  result = requireLogin(ss, e.parameter.user, e.parameter.pw, () =>
                      saveEntry(sheet, e.parameter.name, e.parameter.date, e.parameter.weight, e.parameter.bp, e.parameter.sugar,
                                e.parameter.gym, e.parameter.walk, e.parameter.walkDistance, e.parameter.zumba)); break;
      default:      result = { error: 'Unknown action: ' + action };
    }
  } catch (err) {
    result = { error: err.message };
  }

  return ContentService
    .createTextOutput(JSON.stringify(result))
    .setMimeType(ContentService.MimeType.JSON);
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function ensureHeaders(sheet) {
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(['ID', 'Name', 'Date', 'Weight', 'Blood Pressure', 'Blood Sugar', 'Gym', 'Walk', 'Walk Distance', 'Zumba']);
  }
}

// A checkbox cell reads back as a real boolean; defensively also accept the
// literal string 'TRUE' in case the column was typed in as text instead.
function toBool(value) {
  return value === true || String(value).toUpperCase() === 'TRUE';
}

// Normalize a Date cell (or, defensively, a string) to 'yyyy-MM-dd'.
function toISODate(value) {
  if (value instanceof Date) {
    return Utilities.formatDate(value, Session.getScriptTimeZone(), 'yyyy-MM-dd');
  }
  const parsed = new Date(value);
  if (!isNaN(parsed.getTime())) {
    return Utilities.formatDate(parsed, Session.getScriptTimeZone(), 'yyyy-MM-dd');
  }
  return String(value);
}

// Parse a 'yyyy-MM-dd' string into a local-time Date, avoiding UTC-parsing
// off-by-one bugs from `new Date(dateString)`.
function parseISODate(dateStr) {
  const parts = dateStr.split('-').map(Number);
  return new Date(parts[0], parts[1] - 1, parts[2]);
}

// ─── AUTH ────────────────────────────────────────────────────────────────────
// Users are managed only on the "Users" tab (columns: ID, Name, PW) — the app
// never creates or edits rows there.
function getUsers(ss) {
  const sheet = ss.getSheetByName(USERS_SHEET_NAME);
  if (!sheet) return [];
  const data = sheet.getDataRange().getValues();
  const users = [];
  for (let i = 1; i < data.length; i++) {
    const [id, name, pw] = data[i];
    if (!name) continue;
    users.push({ name: String(name), pw: String(pw) });
  }
  return users;
}

function getUserNames(ss) {
  return { names: getUsers(ss).map(u => u.name) };
}

function login(ss, user, pw) {
  if (!user || !pw) return { ok: false, error: 'Enter a name and password' };
  const match = getUsers(ss).find(u => u.name === user);
  if (!match || match.pw !== pw) return { ok: false, error: 'Incorrect name or password' };
  return { ok: true };
}

function requireLogin(ss, user, pw, fn) {
  const auth = login(ss, user, pw);
  if (!auth.ok) return auth;
  return fn();
}

// ─── DATA ────────────────────────────────────────────────────────────────────
function getAllRows(sheet) {
  ensureHeaders(sheet);
  const data = sheet.getDataRange().getValues();
  const items = [];
  for (let i = 1; i < data.length; i++) {
    const [id, name, date, weight, bp, sugar, gym, walk, walkDistance, zumba] = data[i];
    if (!name) continue;
    items.push({
      id: id,
      name: String(name),
      date: toISODate(date),
      weight: Number(weight),
      bp: bp ? String(bp) : '',
      sugar: sugar ? String(sugar) : '',
      gym: toBool(gym),
      walk: toBool(walk),
      walkDistance: walkDistance ? Number(walkDistance) : null,
      zumba: toBool(zumba)
    });
  }
  return { items };
}

function saveEntry(sheet, name, dateStr, weightStr, bp, sugar, gym, walk, walkDistance, zumba) {
  if (!name || !dateStr || !weightStr) return { error: 'Missing name, date, or weight' };
  ensureHeaders(sheet);

  const weight = Number(weightStr);
  const gymBool = toBool(gym);
  const walkBool = toBool(walk);
  const walkDist = walkDistance ? Number(walkDistance) : '';
  const zumbaBool = toBool(zumba);
  const data = sheet.getDataRange().getValues();

  let maxId = 0;
  let targetRow = -1;
  for (let i = 1; i < data.length; i++) {
    const [id, rowName, rowDate] = data[i];
    if (Number(id) > maxId) maxId = Number(id);
    if (String(rowName) === name && toISODate(rowDate) === dateStr) {
      targetRow = i + 1; // 1-based sheet row
    }
  }

  const row = [weight, bp || '', sugar || '', gymBool, walkBool, walkDist, zumbaBool];
  if (targetRow !== -1) {
    sheet.getRange(targetRow, 4, 1, row.length).setValues([row]);
  } else {
    sheet.appendRow([maxId + 1, name, parseISODate(dateStr), ...row]);
  }

  return getAllRows(sheet);
}
