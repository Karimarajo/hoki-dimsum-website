<?php
/**
 * config.php — Konfigurasi umum aplikasi Marajo (PT Marajo Barokah).
 * marajo.pos-hokidimsum.com
 */

date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', 'Marajo');
define('COMPANY_NAME', 'PT Marajo Barokah');
define('MAIN_SITE_URL', 'https://pos-hokidimsum.com');

// Username yang boleh mengakses aplikasi ini (dibandingkan lowercase).
// Sesuai PDF arsitektur: "Kunci akses hanya untuk kurniarp & hanazaf".
const ALLOWED_USERS = ['kurniarp', 'hanazaf'];

function is_allowed_username(string $username): bool
{
    return in_array(strtolower(trim($username)), ALLOWED_USERS, true);
}

// ── AUTO DETECT: DEV (XAMPP lokal) atau PRODUCTION (Hostinger) ───────
// Pola deteksi sama persis dengan api.php di pos-hokidimsum.com, supaya
// perilaku dev/production konsisten antar kedua aplikasi.
$host = $_SERVER['HTTP_HOST'] ?? '';
define('MARAJO_IS_DEV', (
    $host === 'localhost' ||
    $host === '127.0.0.1' ||
    substr($host, 0, 8) === '192.168.' ||
    strpos($host, 'localhost') !== false ||
    strpos($host, ':') !== false ||
    file_exists(__DIR__ . '/../dev.flag')
));
