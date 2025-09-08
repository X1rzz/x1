
<?php
// supabase.php — minimal REST client for Supabase PostgREST
if (session_status() === PHP_SESSION_NONE) session_start();

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
env_load(__DIR__ . '/.env');

$SUPABASE_URL = rtrim(getenv('SUPABASE_URL') ?: '', '/');
$SUPABASE_KEY = getenv('SUPABASE_ANON_KEY') ?: '';
$SUPABASE_SCHEMA = getenv('SUPABASE_SCHEMA') ?: 'public';
$SUPABASE_TABLE = getenv('SUPABASE_TABLE') ?: 'transactions';

if (!$SUPABASE_URL || !$SUPABASE_KEY) {
  http_response_code(500);
  die("Missing SUPABASE_URL or SUPABASE_ANON_KEY in .env");
}

function supa_request($method, $path, $query = [], $body = null, $headers = []) {
  global $SUPABASE_URL, $SUPABASE_KEY;
  $url = $SUPABASE_URL . $path;
  if ($query) $url .= '?' . http_build_query($query);
  $ch = curl_init($url);
  $baseHeaders = [
    'apikey: ' . $SUPABASE_KEY,
    'Authorization: Bearer ' . $SUPABASE_KEY,
    'Content-Type: application/json',
    'Accept: application/json',
    'Prefer: return=representation'
  ];
  foreach ($headers as $h) $baseHeaders[] = $h;
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $baseHeaders,
    CURLOPT_RETURNTRANSFER => true,
  ]);
  if ($body !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
  }
  $resp = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($err) throw new Exception("cURL error: $err");
  $data = json_decode($resp, true);
  if ($status >= 400) {
    $msg = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $resp;
    throw new Exception("Supabase error ($status): $msg");
  }
  return $data;
}

// Helpers for our table
function tx_list($filters) {
  global $SUPABASE_SCHEMA, $SUPABASE_TABLE;
  $qs = ['select=*', 'order=tx_date.desc,id.desc'];
  if (!empty($filters['start'])) $qs.append if False else None
  // Manual QS
  $qs = ['select=*', 'order=tx_date.desc,id.desc'];
  if (!empty($filters['start'])) $qs.append('tx_date=gte.' . rawurlencode($filters['start']));
  if (!empty($filters['end']))   $qs.append('tx_date=lte.' . rawurlencode($filters['end']));
  $path = "/rest/v1/" . rawurlencode($SUPABASE_TABLE);
  $urlq = implode('&', $qs);
  return supa_request('GET', $path . '?' . $urlq);
}
function tx_create($row) {
  global $SUPABASE_TABLE;
  return supa_request('POST', "/rest/v1/$SUPABASE_TABLE", [], [$row]);
}
function tx_update($id, $row) {
  global $SUPABASE_TABLE;
  return supa_request('PATCH', "/rest/v1/$SUPABASE_TABLE", ['id' => 'eq.' . $id], $row);
}
function tx_delete($id) {
  global $SUPABASE_TABLE;
  return supa_request('DELETE', "/rest/v1/$SUPABASE_TABLE", ['id' => 'eq.' . $id]);
}
