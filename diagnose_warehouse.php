<?php
/**
 * diagnose_warehouse.php — Script diagnostik SEMENTARA, READ-ONLY (tidak ada
 * INSERT/UPDATE/DELETE sama sekali), untuk investigasi laporan data stok
 * gudang & bahan baku hilang/tidak sesuai di production. Dihapus lagi
 * setelah dipakai.
 */

header('Content-Type: application/json');

const SECRET = 'd4f9a2c8e6b1047253af90c1de7b6a8f5c3e9021';

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

$out = [];

// Koneksi & kapasitas server (relevan kalau dugaan-nya koneksi MySQL exhausted)
$res = $conn->query("SHOW VARIABLES LIKE 'max_connections'");
$out['max_connections'] = $res ? $res->fetch_assoc() : null;
$res = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
$out['threads_connected'] = $res ? $res->fetch_assoc() : null;
$res = $conn->query("SHOW STATUS LIKE 'Max_used_connections'");
$out['max_used_connections'] = $res ? $res->fetch_assoc() : null;
$res = $conn->query("SHOW STATUS LIKE 'Aborted_connects'");
$out['aborted_connects'] = $res ? $res->fetch_assoc() : null;

$tables = ['bahan_baku', 'warehouse_stok', 'warehouse_ledger', 'inventory', 'stok_master', 'hpp_produk', 'hpp_produk_detail'];

foreach ($tables as $t) {
    $tEsc = $conn->real_escape_string($t);
    $chk = $conn->query("SHOW TABLES LIKE '$tEsc'");
    if (!$chk || $chk->num_rows === 0) {
        $out['tables'][$t] = ['exists' => false];
        continue;
    }

    $cols = $conn->query("SHOW COLUMNS FROM `$tEsc`");
    $colList = $cols ? array_column($cols->fetch_all(MYSQLI_ASSOC), 'Field') : [];

    $cnt = $conn->query("SELECT COUNT(*) AS c FROM `$tEsc`");
    $count = $cnt ? (int)$cnt->fetch_assoc()['c'] : null;

    // Info engine/update_time dari information_schema - indikasi kapan tabel terakhir berubah
    $info = $conn->query("SELECT ENGINE, TABLE_ROWS, CREATE_TIME, UPDATE_TIME, CHECK_TIME
                           FROM information_schema.TABLES
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tEsc'");
    $infoRow = $info ? $info->fetch_assoc() : null;

    // Sample baris terakhir (by id DESC) biar kelihatan isinya masih ada / tidak.
    // Khusus warehouse_ledger, dump SEMUA baris (cuma 16, aman) buat lacak persis
    // kapan insiden kehilangan data terjadi dari gap id & tanggal yang bertahan.
    $sample = null;
    if (in_array('id', $colList, true)) {
        $limit = ($t === 'warehouse_ledger') ? 'ORDER BY id ASC' : 'ORDER BY id DESC LIMIT 5';
        $sampleRes = $conn->query("SELECT * FROM `$tEsc` $limit");
        $sample = $sampleRes ? $sampleRes->fetch_all(MYSQLI_ASSOC) : [];
    }
    if ($t === 'warehouse_ledger') {
        $minMax = $conn->query("SELECT MIN(id) min_id, MAX(id) max_id, MIN(tgl) min_tgl, MAX(tgl) max_tgl, MIN(created_at) min_created, MAX(created_at) max_created FROM `$tEsc`");
        $out['warehouse_ledger_minmax'] = $minMax ? $minMax->fetch_assoc() : null;
    }

    $out['tables'][$t] = [
        'exists'      => true,
        'columns'     => $colList,
        'row_count'   => $count,
        'table_info'  => $infoRow,
        'sample_last5'=> $sample,
    ];
}

echo json_encode(['status' => 'success', 'data' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
