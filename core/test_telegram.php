<?php
/* ════════════════════════════════════════════════════════════════════
   core/test_telegram.php  --  Telegram Bot Diagnostic & Verification
   ──────────────────────────────────────────────────────────────────
   Run this script to test your Telegram Bot Token & Chat ID:
       php core/test_telegram.php
   ════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/telegram_webhook.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║          NetraX 3.0 — Telegram Bot Diagnostic Suite               ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$envFile = dirname(__DIR__) . '/.env';
echo "📁 Checking .env file at: {$envFile}\n";

if (!file_exists($envFile)) {
    echo "❌ ERROR: .env file does not exist!\n";
    echo "   Please create .env (or copy .env.example) and add your bot credentials.\n\n";
    exit(1);
}

echo "✅ .env file found.\n\n";

$token  = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : '';
$chatId = defined('TELEGRAM_CHAT_ID') ? TELEGRAM_CHAT_ID : '';

echo "🔍 Loaded Configuration:\n";
echo "   - TELEGRAM_BOT_TOKEN : " . (!empty($token) ? substr($token, 0, 10) . '...' . substr($token, -5) : '(EMPTY)') . "\n";
echo "   - TELEGRAM_CHAT_ID   : " . (!empty($chatId) ? $chatId : '(EMPTY)') . "\n\n";

if (empty($token)) {
    echo "❌ ERROR: TELEGRAM_BOT_TOKEN is empty in .env!\n";
    echo "   Step 1: Open Telegram -> @BotFather -> create bot -> copy token.\n";
    echo "   Step 2: Add to .env: TELEGRAM_BOT_TOKEN=\"your_bot_token\"\n\n";
    exit(1);
}

if (empty($chatId)) {
    echo "❌ ERROR: TELEGRAM_CHAT_ID is empty in .env!\n";
    echo "   Step 1: Open Telegram -> @userinfobot -> click Start -> copy Id.\n";
    echo "   Step 2: Add to .env: TELEGRAM_CHAT_ID=\"your_chat_id\"\n\n";
    exit(1);
}

// ── Test 1: getMe (Validate Bot Token) ──────────────────────────────
echo "🤖 Step 1/2: Validating Bot Token with Telegram API (getMe)...\n";
$getMeUrl = "https://api.telegram.org/bot{$token}/getMe";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $getMeUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT      => 'NetraX-Diagnostic/3.0',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || empty($response)) {
    echo "❌ FAILED: Could not authenticate bot token (HTTP {$httpCode}).\n";
    if ($curlErr) {
        echo "   cURL Error: {$curlErr}\n";
    }
    echo "   Response: {$response}\n\n";
    echo "💡 TROUBLESHOOTING:\n";
    echo "   - Make sure your bot token is copied correctly without extra spaces or symbols.\n";
    echo "   - Format should look like: 7123456789:AAHk1234567890abcdef\n\n";
    exit(1);
}

$botData = json_decode($response, true);
if (!$botData || empty($botData['ok']) || empty($botData['result'])) {
    echo "❌ FAILED: Invalid response from Telegram API:\n";
    echo "   {$response}\n\n";
    exit(1);
}

$botName     = $botData['result']['first_name'] ?? 'Unknown';
$botUsername = $botData['result']['username'] ?? 'Unknown';

echo "✅ Bot Token is VALID!\n";
echo "   - Bot Name     : {$botName}\n";
echo "   - Bot Username : @{$botUsername}\n\n";

// ── Test 2: sendMessage (Validate Chat ID & Delivery) ───────────────
echo "📨 Step 2/2: Sending Test Alert to Chat ID {$chatId}...\n";

$testFields = [
    '⚡ Status'      => 'Online & Connected',
    '🎯 Engine'      => 'NetraX 3.0 Notification System',
    '🕒 Test Time'   => date('Y-m-d H:i:s T'),
    '🤖 Bot Handle'  => '@' . $botUsername,
];

$success = sendTelegramAlert('🔔 NetraX 3.0 — Telegram Alert Test', $testFields);

if ($success) {
    echo "✅ SUCCESS! Test alert sent to your Telegram chat!\n";
    echo "   Check your Telegram app — you should see a structured alert card from @{$botUsername}.\n\n";
} else {
    echo "❌ FAILED: Could not send message to Chat ID {$chatId}.\n\n";
    echo "💡 TROUBLESHOOTING:\n";
    echo "   1. Open Telegram and search for your bot: @{$botUsername}\n";
    echo "   2. Click 'START' or send '/start' to your bot.\n";
    echo "      (Telegram bots cannot send messages to users who haven't started a conversation with them!)\n";
    echo "   3. Double-check that your Chat ID ({$chatId}) is your exact numeric ID from @userinfobot.\n\n";
    exit(1);
}

echo "════════════════════════════════════════════════════════════════════\n";
echo "🎉 Telegram Bot Integration is FULLY FUNCTIONAL!\n";
echo "════════════════════════════════════════════════════════════════════\n\n";
exit(0);
