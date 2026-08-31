<?php
/**
 * db.php — Koneksi ke database Marajo. Numpang di database
 * u173485424_Order_Hoki (bukan database baru — limit jumlah database di
 * Hostinger sudah penuh), semua tabel Marajo diberi prefix "marajo_" di
 * schema.sql supaya tidak bentrok dengan tabel order yang sudah ada.
 */

require_once __DIR__ . '/config.php';

if (MARAJO_IS_DEV) {
    // Database lokal XAMPP untuk testing — buat database "marajo_dev" di
    // phpMyAdmin lokal lalu import marajo/schema.sql ke situ. Dibiarkan
    // terpisah dari Order Hoki di lokal supaya tidak perlu import dump
    // Order Hoki cuma untuk testing Marajo.
    define('MARAJO_DB_HOST', '127.0.0.1');
    define('MARAJO_DB_NAME', 'marajo_dev');
    define('MARAJO_DB_USER', 'root');
    define('MARAJO_DB_PASS', '');
} else {
    // ── PRODUCTION (Hostinger) — database u173485424_Order_Hoki ──
    define('MARAJO_DB_HOST', '127.0.0.1');
    define('MARAJO_DB_NAME', 'u173485424_Order_Hoki');
    define('MARAJO_DB_USER', 'u173485424_Order_Hoki');
    define('MARAJO_DB_PASS', 'OrderHoki95');
}

function marajo_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . MARAJO_DB_HOST . ';dbname=' . MARAJO_DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, MARAJO_DB_USER, MARAJO_DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Koneksi database Marajo gagal: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
