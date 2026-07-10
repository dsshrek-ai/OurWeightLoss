// ─── CONFIG ───────────────────────────────────────────────────────────────────
const SHEET_ID   = '1LKSZpQ0zwlYM5oMce5QDty3GsS41sEIEA_OIXFFcpOE';   // ← paste your Sheet ID here
const SHEET_NAME = 'Sheet1';                 // ← tab name in your Sheet

// ─── ROUTER ──────────────────────────────────────────────────────────────────
function doGet(e) {
  const action = e.parameter.action;
  const sheet  = SpreadsheetApp.openById(SHEET_ID).getSheetByName(SHEET_NAME);

  let result;
  try {
    switch (action) {
      case 'list': result = getAllRows(sheet);                                            break;
      case 'save': result = saveEntry(sheet, e.parameter.name, e.parameter.date, e.parameter.weight); break;
      default:     result = { error: 'Unknown action: ' + action };
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
    sheet.appendRow(['ID', 'Name', 'Date', 'Weight']);
  }
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

// ─── DATA ────────────────────────────────────────────────────────────────────
function getAllRows(sheet) {
  ensureHeaders(sheet);
  const data = sheet.getDataRange().getValues();
  const items = [];
  for (let i = 1; i < data.length; i++) {
    const [id, name, date, weight] = data[i];
    if (!name) continue;
    items.push({ id: id, name: String(name), date: toISODate(date), weight: Number(weight) });
  }
  return { items };
}

function saveEntry(sheet, name, dateStr, weightStr) {
  if (!name || !dateStr || !weightStr) return { error: 'Missing name, date, or weight' };
  ensureHeaders(sheet);

  const weight = Number(weightStr);
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

  if (targetRow !== -1) {
    sheet.getRange(targetRow, 4).setValue(weight);
  } else {
    sheet.appendRow([maxId + 1, name, parseISODate(dateStr), weight]);
  }

  return getAllRows(sheet);
}
