/**
 * session.js — Hoki POS Session Guard & Global Number Formatter
 * Runs after page load on every page (except index.html).
 * - Syncs logout across browser tabs via storage events
 * - Guards against session being cleared externally
 * - Automatically formats type="number" inputs with thousands separators
 */

(function () {
    const LOGIN_PAGE = 'index.html';

    function isLoginPage() {
        return window.location.pathname.endsWith(LOGIN_PAGE) ||
               window.location.pathname.endsWith('/');
    }

    if (isLoginPage()) return;

    /* Redirect to login if session is gone, or enforce Investor route locks */
    function guardSession() {
        const uStr = localStorage.getItem('currentUser');
        if (!uStr) {
            window.location.href = LOGIN_PAGE;
            return;
        }
        
        try {
            const u = JSON.parse(uStr);
            if (u && u.role === 'Investor') {
                const path = window.location.pathname;
                if (!path.endsWith('investor_omset.html') && !path.endsWith('investor_profit.html')) {
                    window.location.href = 'investor_omset.html';
                }
            }
        } catch (e) {
            // Ignore parse errors, let normal app flow handle invalid session data
        }

        // ── AUTOMATIC MIDNIGHT LOGOUT ──
        const todayStr = new Date().toDateString();
        let loginDateStr = localStorage.getItem('loginDate');
        
        if (!loginDateStr) {
            localStorage.setItem('loginDate', todayStr);
            loginDateStr = todayStr;
        }
        
        if (loginDateStr !== todayStr) {
            localStorage.removeItem('currentUser');
            localStorage.removeItem('loginDate');
            localStorage.removeItem('cabangHoki');
            localStorage.removeItem('sessionToken');
            window.location.href = LOGIN_PAGE;
            return;
        }
    }

    // Periksa setiap 1 menit (60000ms) apakah hari sudah berganti (melewati jam 12 malam)
    setInterval(() => {
        const todayStr = new Date().toDateString();
        const loginDateStr = localStorage.getItem('loginDate');
        if (loginDateStr && loginDateStr !== todayStr) {
            localStorage.removeItem('currentUser');
            localStorage.removeItem('loginDate');
            localStorage.removeItem('cabangHoki');
            localStorage.removeItem('sessionToken');
            window.location.href = LOGIN_PAGE;
        }
    }, 60000);

    /* Sync logout across tabs — if another tab clears currentUser, redirect here too */
    window.addEventListener('storage', function (e) {
        if (e.key === 'currentUser' && !e.newValue) {
            window.location.href = LOGIN_PAGE;
        }
    });

    /* Run guard once on load */
    guardSession();

    // ── SINGLE-DEVICE SESSION ENFORCEMENT ──
    // Polling ke check_session tiap 10 detik - paling cepat yang wajar tanpa infrastruktur
    // push/websocket (di luar stack PHP+vanilla JS project ini). Server sudah overwrite
    // session_token tiap login sukses (action 'login' di api.php), jadi begitu ada device
    // lain login pakai akun yang sama, polling berikutnya di sini dapat valid:false dan
    // sesi ini otomatis keluar. Berlaku semua role, tidak ada pengecualian.
    function checkSessionValidity() {
        const uStr  = localStorage.getItem('currentUser');
        const token = localStorage.getItem('sessionToken');
        if (!uStr || !token) return; // tidak/sudah tidak login - tidak ada yang perlu dicek

        let username;
        try { username = JSON.parse(uStr).user; } catch (e) { return; }
        if (!username) return;

        fetch(`${API_BASE}?action=check_session&user=${encodeURIComponent(username)}&token=${encodeURIComponent(token)}`)
            .then(res => res.json())
            .then(hasil => {
                if (!hasil || hasil.valid !== false) return; // valid, atau respons tak terduga - jangan paksa logout

                // Sesi ini sudah "dilangkahi" login dari device lain - hapus daftar yang
                // sama dengan auto-logout tengah malam di atas.
                localStorage.removeItem('currentUser');
                localStorage.removeItem('loginDate');
                localStorage.removeItem('cabangHoki');
                localStorage.removeItem('sessionToken');

                const pesan = 'Akun ini baru saja login di perangkat lain — sesi ini otomatis keluar.';
                if (typeof toast !== 'undefined' && toast.error) {
                    toast.error('Sesi Berakhir', pesan);
                    setTimeout(() => { window.location.href = LOGIN_PAGE; }, 1800);
                } else {
                    alert(pesan);
                    window.location.href = LOGIN_PAGE;
                }
            })
            .catch(() => {
                // Gagal koneksi ke server (network error sesaat) - jangan paksa logout
                // cuma gara-gara itu, coba lagi di polling berikutnya.
            });
    }

    setInterval(checkSessionValidity, 10000);

    // ── CEK LOKASI (VIP Access) — respon sisi klien ──
    // Polling tiap 5 detik: "apakah lokasi saya lagi diminta VIP?" Kalau iya,
    // ambil GPS device via navigator.geolocation lalu kirim balik ke server.
    // Cuma jalan kalau memang sedang ada permintaan aktif - tidak melacak
    // terus-menerus, dan device tetap munculkan izin lokasi browser standar
    // (tidak bisa diam-diam tanpa izin pengguna).
    function checkLocationRequest() {
        const uStr  = localStorage.getItem('currentUser');
        const token = localStorage.getItem('sessionToken');
        if (!uStr || !token) return;

        let username;
        try { username = JSON.parse(uStr).user; } catch (e) { return; }
        if (!username) return;

        fetch(`${API_BASE}?action=check_location_request&user=${encodeURIComponent(username)}&token=${encodeURIComponent(token)}`)
            .then(res => res.json())
            .then(hasil => {
                if (!hasil || !hasil.pending) return;
                if (!navigator.geolocation) return;

                if (typeof toast !== 'undefined' && toast.info) {
                    toast.info('Lokasi Diminta', 'Admin sedang memeriksa posisi Anda saat ini.');
                }

                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        fetch(`${API_BASE}?action=submit_location`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                user: username,
                                token: token,
                                lat: pos.coords.latitude,
                                lng: pos.coords.longitude,
                                accuracy: pos.coords.accuracy,
                            }),
                        }).catch(() => {});
                    },
                    () => {
                        // User menolak izin lokasi / GPS tidak tersedia - diamkan saja,
                        // VIP akan lihat hasilnya kosong/tidak ada respon.
                    },
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
                );
            })
            .catch(() => {});
    }

    setInterval(checkLocationRequest, 5000);

    // ── GLOBAL NUMBER FORMATTER ──
    function formatRibuan(val) {
        if (val === undefined || val === null || val === '') return '';
        let clean = String(val).replace(/\D/g, '');
        if (clean === '') return '';
        return parseInt(clean, 10).toLocaleString('id-ID');
    }

    // Override HTMLInputElement value property descriptor to return clean number
    const originalValueDescriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    Object.defineProperty(HTMLInputElement.prototype, 'value', {
        get: function () {
            const val = originalValueDescriptor.get.call(this);
            if (this.dataset && this.dataset.isNumericFormatted) {
                return val.replace(/\D/g, '');
            }
            return val;
        },
        set: function (val) {
            if (this.dataset && this.dataset.isNumericFormatted) {
                const formatted = formatRibuan(val);
                originalValueDescriptor.set.call(this, formatted);
            } else {
                originalValueDescriptor.set.call(this, val);
            }
        }
    });

    // Transform type="number" to text with thousands separators
    function processInput(input) {
        if (input.dataset.isNumericFormatted) return;

        // Skip input type="number" if it has decimal step (e.g. qty in HPP)
        const step = input.getAttribute('step');
        if (step && step.includes('.')) return;

        // Mark it as processed
        input.dataset.isNumericFormatted = "true";
        input.type = 'text';

        // Format initial value
        const initialVal = originalValueDescriptor.get.call(input);
        if (initialVal) {
            originalValueDescriptor.set.call(input, formatRibuan(initialVal));
        }

        // Add listener to auto format during typing
        input.addEventListener('input', function (e) {
            const cursorPosition = e.target.selectionStart;
            const originalLength = e.target.value.length;
            
            const rawValue = originalValueDescriptor.get.call(e.target);
            const formatted = formatRibuan(rawValue);
            originalValueDescriptor.set.call(e.target, formatted);
            
            const newLength = formatted.length;
            const newPosition = cursorPosition + (newLength - originalLength);
            e.target.setSelectionRange(newPosition, newPosition);
        });
    }

    function scanAndProcess() {
        const inputs = document.querySelectorAll('input[type="number"]');
        inputs.forEach(processInput);
    }

    scanAndProcess();
    document.addEventListener('DOMContentLoaded', scanAndProcess);
    window.addEventListener('load', scanAndProcess);

    // Watch for dynamically added inputs
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.addedNodes) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.tagName === 'INPUT' && node.type === 'number') {
                            processInput(node);
                        } else {
                            const childInputs = node.querySelectorAll('input[type="number"]');
                            childInputs.forEach(processInput);
                        }
                    }
                });
            }
        });
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
