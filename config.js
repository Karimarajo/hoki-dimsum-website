// ── API Configuration ──────────────────────────────────────
// Ganti ke URL Hostinger agar localhost baca data dari server asli
// Untuk kembali ke mode lokal, ganti ke: 'api.php'

const API_BASE = 'api.php';

// Helper function agar tidak perlu ubah semua fetch secara manual
function apiUrl(action) {
    return `${API_BASE}?action=${action}`;
}

// ── Versi Aplikasi (satu-satunya sumber kebenaran) ─────────────
// Ganti nilai ini setiap ada rilis baru - seluruh halaman (sidebar, footer,
// pesan WA, PDF/print) otomatis ikut karena semuanya narik dari sini.
// Aturan penomoran:
//   - Perubahan kecil / perbaikan bug -> naikkan angka BELAKANG titik
//     contoh: V4.7 -> V4.8
//   - Perubahan besar (fitur baru, dsb) -> naikkan angka DEPAN titik
//     contoh: V4.8 -> V5.0
const APP_VERSION = 'V6.0';

// Terapkan APP_VERSION ke semua elemen bertanda class .app-ver / .app-ver-lower,
// dan ke <img> yang alt-nya berisi versi (ditandai data-alt-template dengan
// token {VER} yang akan diganti APP_VERSION).
function applyAppVersion() {
    document.querySelectorAll('.app-ver').forEach(function (el) {
        el.textContent = APP_VERSION;
    });
    document.querySelectorAll('.app-ver-lower').forEach(function (el) {
        el.textContent = APP_VERSION.toLowerCase();
    });
    document.querySelectorAll('[data-alt-template]').forEach(function (el) {
        el.alt = el.getAttribute('data-alt-template').replace('{VER}', APP_VERSION);
    });
}
document.addEventListener('DOMContentLoaded', applyAppVersion);

// ── Buka link WhatsApp (kirim laporan/struk/slip) ──────────────────
// BUG: sejak app ini bisa di-install ke Home Screen iPhone (mode "standalone"),
// window.open(url,'_blank') diam saja tanpa efek apapun kalau dipanggil dari
// dalam app yang sudah di-install - ini keterbatasan WebKit/Safari yang memang
// sengaja menonaktifkan buka tab baru di luar app Safari biasa (bukan bug di
// kode kita). Makanya tombol "Kirim ke WA" terasa "gak bisa" khusus buat user
// yang sudah nge-install app-nya ke layar utama, padahal di browser tab biasa
// baik-baik saja. Solusi: kalau lagi jalan sebagai app standalone, pindah pakai
// window.location.href (tetap bisa buka app WhatsApp/WhatsApp Web di tab yang
// sama) - kalau masih di tab browser biasa, tetap window.open supaya halaman
// asal tidak ikut hilang.
function bukaLinkWA(url) {
    const isStandalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
        || window.navigator.standalone === true;
    if (isStandalone) {
        window.location.href = url;
    } else {
        window.open(url, '_blank');
    }
}
