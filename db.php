
<?php
// db.php — create PDO connection from environment or .env file
function env_load($path) {
  if (!file_exists($path)) return;
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    if (!str_contains($line, '=')) continue;
    [$k, $v] = array_map('trim', explode('=', $line, 2));
    if (!isset($_ENV[$k]) && !isset($_SERVER[$k])) {
      putenv("$k=$v");
      $_ENV[$k] = $v;
      $_SERVER[$k] = $v;
    }
  }
}

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Load .env if present
env_load(__DIR__ . '/.env');

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'expense_db';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_CHARSET = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
  http_response_code(500);
  die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
