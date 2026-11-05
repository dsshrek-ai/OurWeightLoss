<?php
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Never let a raw PHP error/notice leak through as HTML — every response
// this API sends must be JSON, or the front end's `res.json()` throws an
// opaque "Unexpected token '<'" instead of the real problem.
ini_set('display_errors', '0');
set_exception_handler(function ($e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
  exit;
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

function respond($data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data);
  exit;
}

function fail(string $message, int $status = 400): void {
  respond(['error' => $message], $status);
}

function db(): mysqli {
  static $conn = null;
  if ($conn === null) {
    try {
      $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
      $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
      fail('Database connection failed', 500);
    }
  }
  return $conn;
}

function jsonBody(): array {
  $raw = file_get_contents('php://input');
  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
}

// ---- Auth ----

// Every data action goes through here — the returned user's id is the ONLY
// thing ever used to scope a query. No request parameter is trusted to say
// whose data it wants; that's what kept the old Google Sheet version from
// enforcing this at all (it only checked that *some* login was valid).
function requireUser(): array {
  $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
    fail('Missing or invalid Authorization header', 401);
  }
  $token = $m[1];

  $stmt = db()->prepare(
    'SELECT u.id, u.username, u.display_name
     FROM sessions s JOIN users u ON u.id = s.user_id
     WHERE s.token = ? AND s.expires_at > NOW()'
  );
  $stmt->bind_param('s', $token);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$result) {
    fail('Session expired or invalid — please log in again', 401);
  }
  return $result;
}

// ---- Entry shaping ----

function shapeEntry(array $row): array {
  return [
    'id'           => (int)$row['id'],
    'date'         => $row['entry_date'],
    'weight'       => $row['weight'] !== null ? (float)$row['weight'] : null,
    'bp'           => $row['blood_pressure'],
    'sugar'        => $row['blood_sugar'] !== null ? (float)$row['blood_sugar'] : null,
    'gym'          => (bool)$row['gym'],
    'walk'         => (bool)$row['walk'],
    'walkDistance' => $row['walk_distance'] !== null ? (float)$row['walk_distance'] : null,
    'zumba'        => (bool)$row['zumba'],
    'steps'        => $row['steps'] !== null ? (int)$row['steps'] : null,
  ];
}

function nullableDecimal($value) {
  return ($value === null || $value === '') ? null : (float)$value;
}

function nullableInt($value) {
  return ($value === null || $value === '') ? null : (int)$value;
}

// ---- Medications ----
// Each person keeps their own list (pwi_medications) and dose log
// (pwi_med_doses). Medications are deactivated, never deleted, so old doses
// keep their name.

function shapeMedication(array $row): array {
  return [
    'id'            => (int)$row['id'],
    'name'          => $row['name'],
    'defaultDosage' => $row['default_dosage'],
    'isInjection'   => (bool)$row['is_injection'],
    'active'        => (bool)$row['active'],
  ];
}

function shapeDose(array $row): array {
  return [
    'id'            => (int)$row['id'],
    'medicationId'  => (int)$row['medication_id'],
    'medication'    => $row['medication_name'],
    'takenAt'       => $row['taken_at'],
    'dosage'        => $row['dosage'],
    'injectionSite' => $row['injection_site'],
    'notes'         => $row['notes'],
  ];
}

function listMedications(int $userId): array {
  $stmt = db()->prepare('SELECT * FROM pwi_medications WHERE user_id = ? ORDER BY active DESC, name');
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  return array_map('shapeMedication', $rows);
}

function listDoses(int $userId): array {
  $stmt = db()->prepare(
    'SELECT d.*, m.name AS medication_name FROM pwi_med_doses d
     JOIN pwi_medications m ON m.id = d.medication_id
     WHERE d.user_id = ? ORDER BY d.taken_at DESC, d.id DESC'
  );
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  return array_map('shapeDose', $rows);
}

function nullableText($value, int $max): ?string {
  $text = trim((string)($value ?? ''));
  return $text === '' ? null : mb_substr($text, 0, $max);
}

// ---- Router ----

$action = $_GET['action'] ?? '';

switch ($action) {

  case 'login': {
    $body = jsonBody();
    $username = trim((string)($body['username'] ?? ''));
    $password = (string)($body['password'] ?? '');
    if ($username === '' || $password === '') {
      fail('Username and password are required');
    }

    $stmt = db()->prepare('SELECT id, password_hash, display_name FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
      fail('Invalid username or password', 401);
    }

    $token = bin2hex(random_bytes(32));
    $days = SESSION_LIFETIME_DAYS;
    $ins = db()->prepare(
      'INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))'
    );
    $ins->bind_param('sii', $token, $user['id'], $days);
    $ins->execute();
    $ins->close();

    respond(['token' => $token, 'displayName' => $user['display_name']]);
  }

  case 'logout': {
    $body = jsonBody();
    $token = (string)($body['token'] ?? '');
    if ($token !== '') {
      $stmt = db()->prepare('DELETE FROM sessions WHERE token = ?');
      $stmt->bind_param('s', $token);
      $stmt->execute();
      $stmt->close();
    }
    respond(['success' => true]);
  }

  case 'listEntries': {
    $user = requireUser();
    $stmt = db()->prepare('SELECT * FROM pwi_entries WHERE user_id = ? ORDER BY entry_date');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    respond(['entries' => array_map('shapeEntry', $rows)]);
  }

  case 'saveEntry': {
    $user = requireUser();
    $body = jsonBody();
    $date = trim((string)($body['date'] ?? ''));
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      fail('A valid date is required');
    }
    $weight = nullableDecimal($body['weight'] ?? null);
    if ($weight === null) {
      fail('Weight is required');
    }
    $bp = trim((string)($body['bp'] ?? ''));
    $bp = $bp === '' ? null : $bp;
    $sugar = nullableDecimal($body['sugar'] ?? null);
    $gym = !empty($body['gym']) ? 1 : 0;
    $walk = !empty($body['walk']) ? 1 : 0;
    $walkDistance = nullableDecimal($body['walkDistance'] ?? null);
    $zumba = !empty($body['zumba']) ? 1 : 0;
    $steps = nullableInt($body['steps'] ?? null);

    $stmt = db()->prepare(
      'INSERT INTO pwi_entries
         (user_id, entry_date, weight, blood_pressure, blood_sugar, gym, walk, walk_distance, zumba, steps)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         weight = VALUES(weight), blood_pressure = VALUES(blood_pressure),
         blood_sugar = VALUES(blood_sugar), gym = VALUES(gym), walk = VALUES(walk),
         walk_distance = VALUES(walk_distance), zumba = VALUES(zumba), steps = VALUES(steps)'
    );
    $stmt->bind_param(
      'isdsdiidii',
      $user['id'], $date, $weight, $bp, $sugar, $gym, $walk, $walkDistance, $zumba, $steps
    );
    $stmt->execute();
    $stmt->close();

    $stmt = db()->prepare('SELECT * FROM pwi_entries WHERE user_id = ? ORDER BY entry_date');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    respond(['success' => true, 'entries' => array_map('shapeEntry', $rows)]);
  }

  case 'listMedications': {
    $user = requireUser();
    respond(['medications' => listMedications((int)$user['id']), 'doses' => listDoses((int)$user['id'])]);
  }

  // Adds a medication (no id) or updates one of the caller's own.
  case 'saveMedication': {
    $user = requireUser();
    $userId = (int)$user['id'];
    $body = jsonBody();
    $id = (int)($body['id'] ?? 0);
    $name = nullableText($body['name'] ?? '', 100);
    if ($name === null) {
      fail('Medication name is required');
    }
    $defaultDosage = nullableText($body['defaultDosage'] ?? '', 50);
    $isInjection = !empty($body['isInjection']) ? 1 : 0;
    $active = array_key_exists('active', $body) ? (!empty($body['active']) ? 1 : 0) : 1;
    try {
      if ($id > 0) {
        $stmt = db()->prepare(
          'UPDATE pwi_medications SET name = ?, default_dosage = ?, is_injection = ?, active = ?
           WHERE id = ? AND user_id = ?'
        );
        $stmt->bind_param('ssiiii', $name, $defaultDosage, $isInjection, $active, $id, $userId);
      } else {
        $stmt = db()->prepare(
          'INSERT INTO pwi_medications (user_id, name, default_dosage, is_injection, active) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issii', $userId, $name, $defaultDosage, $isInjection, $active);
      }
      $stmt->execute();
      $stmt->close();
    } catch (mysqli_sql_exception $e) {
      if ($e->getCode() === 1062) {
        fail('You already have a medication with that name');
      }
      throw $e;
    }
    respond(['success' => true, 'medications' => listMedications($userId), 'doses' => listDoses($userId)]);
  }

  // Records a dose (no id) or updates one of the caller's own. takenAt is
  // local "YYYY-MM-DD HH:MM". The injection site is kept only for a
  // medication marked as an injection.
  case 'saveDose': {
    $user = requireUser();
    $userId = (int)$user['id'];
    $body = jsonBody();
    $id = (int)($body['id'] ?? 0);
    $medicationId = (int)($body['medicationId'] ?? 0);
    $stmt = db()->prepare('SELECT is_injection FROM pwi_medications WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $medicationId, $userId);
    $stmt->execute();
    $med = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$med) {
      fail('Pick a medication');
    }
    $takenAt = trim((string)($body['takenAt'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $takenAt) || !strtotime($takenAt)) {
      fail('A valid date and time are required');
    }
    $takenAt .= ':00';
    $dosage = nullableText($body['dosage'] ?? '', 50);
    $site = $med['is_injection'] ? nullableText($body['injectionSite'] ?? '', 50) : null;
    $notes = nullableText($body['notes'] ?? '', 500);
    if ($id > 0) {
      $stmt = db()->prepare(
        'UPDATE pwi_med_doses SET medication_id = ?, taken_at = ?, dosage = ?, injection_site = ?, notes = ?
         WHERE id = ? AND user_id = ?'
      );
      $stmt->bind_param('issssii', $medicationId, $takenAt, $dosage, $site, $notes, $id, $userId);
    } else {
      $stmt = db()->prepare(
        'INSERT INTO pwi_med_doses (user_id, medication_id, taken_at, dosage, injection_site, notes)
         VALUES (?, ?, ?, ?, ?, ?)'
      );
      $stmt->bind_param('iissss', $userId, $medicationId, $takenAt, $dosage, $site, $notes);
    }
    $stmt->execute();
    $stmt->close();
    respond(['success' => true, 'medications' => listMedications($userId), 'doses' => listDoses($userId)]);
  }

  case 'deleteDose': {
    $user = requireUser();
    $userId = (int)$user['id'];
    $body = jsonBody();
    $id = (int)($body['id'] ?? 0);
    $stmt = db()->prepare('DELETE FROM pwi_med_doses WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    $stmt->close();
    respond(['success' => true, 'medications' => listMedications($userId), 'doses' => listDoses($userId)]);
  }

  default:
    fail('Unknown action', 404);
}
