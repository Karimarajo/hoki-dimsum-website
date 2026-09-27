<?php
/**
 * run_migration_v7.php — Script SEKALI PAKAI untuk menjalankan migration SQL
 * V7.0 (Peer Review + Skor Total + Foto Laporan Lapak) ke database PRODUCTION,
 * karena akses remote MySQL diblok firewall Hostinger (tidak bisa dijalankan
 * langsung dari luar). Dijalankan sekali lewat browser/curl pakai token
 * rahasia di bawah, lalu file ini DIHAPUS dari repo & server setelah dipakai.
 *
 * Semua statement di 4 file migration ini idempotent (CREATE TABLE IF NOT
 * EXISTS / INSERT IGNORE / ALTER lewat cek information_schema dulu) - aman
 * dijalankan berkali-kali kalau perlu retry.
 */

header('Content-Type: application/json');

const SECRET = 'e112ca91413585260afe48c7d6a5eda1e4ff914eca891070';

if (($_GET['secret'] ?? '') !== SECRET) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token tidak valid.']);
    exit;
}

$conn = @new mysqli('localhost', 'u173485424_kurniarp', 'Alpukat19#', 'u173485424_hoki');
if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal: ' . ($conn ? $conn->connect_error : 'unknown')]);
    exit;
}
$conn->set_charset('utf8mb4');

$files = [
    'migration_penilaian_peer_review.sql',
    'migration_penilaian_dummy_seed_flag.sql',
    'migration_penilaian_target_omset.sql',
    'migration_laporan_stok_foto.sql',
];

$log = [];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        $log[] = ['file' => $file, 'status' => 'error', 'message' => 'File tidak ditemukan di server.'];
        continue;
    }
    $sql = file_get_contents($path);
    $sql = preg_replace('/^--.*$/m', '', $sql); // buang komentar baris supaya tidak ikut ke-split
    $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');

    foreach ($statements as $stmt) {
        $ok = $conn->query($stmt);
        $log[] = [
            'file'      => $file,
            'statement' => substr($stmt, 0, 90) . (strlen($stmt) > 90 ? '...' : ''),
            'status'    => $ok ? 'ok' : 'error',
            'error'     => $ok ? null : $conn->error,
        ];
    }
}

$hasError = (bool) array_filter($log, fn($l) => ($l['status'] ?? '') === 'error');
echo json_encode(['status' => $hasError ? 'partial_error' : 'success', 'log' => $log], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
