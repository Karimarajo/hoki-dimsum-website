<?php
/**
 * db_main.php — Koneksi ke database Hoki Dimsum (u173485424_hoki).
 * HANYA dipakai untuk verifikasi SSO (username + session_token dari
 * pos-hokidimsum.com) dan login fallback. Tidak pernah menulis ke sini.
 */

require_once __DIR__ . '/config.php';

if (MARAJO_IS_DEV) {
    define('MAIN_DB_HOST', '127.0.0.1');
    define('MAIN_DB_NAME', 'u173485424_hoki');
    define('MAIN_DB_USER', 'root');
    define('MAIN_DB_PASS', '');
} else {
    define('MAIN_DB_HOST', 'localhost');
    define('MAIN_DB_NAME', 'u173485424_hoki');
    define('MAIN_DB_USER', 'u173485424_kurniarp');
    define('MAIN_DB_PASS', 'Alpukat19#');
}

function main_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . MAIN_DB_HOST . ';dbname=' . MAIN_DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, MAIN_DB_USER, MAIN_DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Koneksi database utama gagal: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

/** Cek apakah kombinasi username + session_token masih valid & aktif di sistem utama. */
function verify_main_session(string $username, string $token): bool
{
    if ($username === '' || $token === '') return false;
    $stmt = main_db()->prepare('SELECT session_token FROM users WHERE LOWER(username) = LOWER(?)');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row && $row['session_token'] !== '' && hash_equals((string)$row['session_token'], $token);
}

/**
 * Verifikasi username + password langsung ke sistem utama. Dipakai form
 * login.php sebagai jalan masuk manual/fallback selain lewat SSO dari
 * menu Executive di dashboard utama.
 */
function verify_main_credentials(string $username, string $password): ?array
{
    $stmt = main_db()->prepare('SELECT * FROM users WHERE LOWER(username) = LOWER(?) AND password = ?');
    $stmt->execute([$username, $password]);
    $row = $stmt->fetch();
    return $row ?: null;
}
