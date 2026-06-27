// ── API Configuration ──────────────────────────────────────
// Ganti ke URL Hostinger agar localhost baca data dari server asli
// Untuk kembali ke mode lokal, ganti ke: 'api.php'

const API_BASE = 'api.php';

// Helper function agar tidak perlu ubah semua fetch secara manual
function apiUrl(action) {
    return `${API_BASE}?action=${action}`;
}
