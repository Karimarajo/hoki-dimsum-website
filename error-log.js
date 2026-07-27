/**
 * error-log.js — Diagnostic ringan sisi client untuk Hoki POS.
 *
 * Load file ini SEBELUM script utama tiap halaman (setelah config.js), supaya
 * window.onerror sudah terpasang sebelum script utama sempat gagal parse/eksekusi.
 * Tanpa endpoint API (belum ada endpoint logging di project ini) — error disimpan
 * ke localStorage (rotating, maks 20 entri terakhir) + dicetak ke console.
 *
 * Sengaja ditulis pakai syntax ES5 (var, function biasa, tanpa arrow function/
 * template literal) supaya logger ini sendiri tetap jalan walau browser yang
 * dipakai lebih tua dari Chrome 71 sekalipun — kalau logger ini sendiri gagal
 * parse, semua diagnostic ikut hilang.
 *
 * Cara lihat log tersimpan (dari console browser staf yang lapor bug):
 *   JSON.parse(localStorage.getItem('hoki_pos_error_log'))
 */
(function () {
    var STORAGE_KEY = 'hoki_pos_error_log';
    var MAX_ENTRIES = 20;

    function simpanEntry(entry) {
        try {
            var log = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            log.push(entry);
            if (log.length > MAX_ENTRIES) {
                log = log.slice(log.length - MAX_ENTRIES);
            }
            localStorage.setItem(STORAGE_KEY, JSON.stringify(log));
        } catch (e) {
            // localStorage penuh/disabled (mode incognito dll) — abaikan, jangan sampai logger sendiri crash
        }
    }

    function nowIso() {
        try {
            return new Date().toISOString();
        } catch (e) {
            return '';
        }
    }

    window.onerror = function (message, source, lineno, colno, error) {
        var entry = {
            type: 'error',
            message: message,
            source: source,
            lineno: lineno,
            colno: colno,
            stack: (error && error.stack) ? error.stack : null,
            userAgent: navigator.userAgent,
            page: location.pathname,
            timestamp: nowIso()
        };
        if (window.console && console.error) console.error('[ErrorLog]', entry);
        simpanEntry(entry);
        return false; // tetap biarkan browser tampilkan error asli di console
    };

    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        var entry = {
            type: 'unhandledrejection',
            message: (reason && reason.message) ? reason.message : String(reason),
            stack: (reason && reason.stack) ? reason.stack : null,
            userAgent: navigator.userAgent,
            page: location.pathname,
            timestamp: nowIso()
        };
        if (window.console && console.error) console.error('[ErrorLog]', entry);
        simpanEntry(entry);
    });
})();
