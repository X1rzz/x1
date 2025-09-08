
<?php
// csrf.php — small CSRF helpers
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
function csrf_token() {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
function csrf_check($token) {
  return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}
