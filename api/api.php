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

  default:
    fail('Unknown action', 404);
}
