<?php
require_once __DIR__ . '/includes/db_order.php';

header('Content-Type: application/json');

$user  = trim($_GET['user']  ?? '');
$token = trim($_GET['token'] ?? '');

if ($user === '' || $token === '') {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid']);
    exit;
}

// ── Koneksi database utama (sama seperti api.php) ──
$host  = $_SERVER['HTTP_HOST'] ?? '';
$isDev = (
    $host === 'localhost' ||
    $host === '127.0.0.1' ||
    substr($host, 0, 8) === '192.168.' ||
    strpos($host, 'localhost') !== false ||
    strpos($host, ':') !== false ||
    file_exists(__DIR__ . '/dev.flag')
);

if ($isDev) {
    $conn = @new mysqli("localhost", "root", "", "u173485424_hoki");
} else {
    $conn = @new mysqli("localhost", "u173485424_kurniarp", "Alpukat19#", "u173485424_hoki");
}

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database utama gagal']);
    exit;
}
$conn->set_charset("utf8mb4");

// ── Validasi user + token ──
$stmt = $conn->prepare("SELECT cabang FROM users WHERE LOWER(username) = LOWER(?) AND session_token = ? AND session_token != ''");
$stmt->bind_param('ss', $user, $token);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid']);
    exit;
}

$userRow    = $result->fetch_assoc();
$cabangRaw  = trim($userRow['cabang'] ?? '');
$cabangList = array_values(array_filter(array_map('trim', explode(',', $cabangRaw))));

$pdo = order_db();

// ── Cocokkan nama cabang milik user ke branch_id di database order ──
// Hanya nilai literal "Semua" yang berarti akses ke semua cabang.
// Cabang kosong/belum di-assign HARUS berarti tidak ada akses (bukan akses semua).
if ($cabangRaw === 'Semua') {
    $branchStmt = $pdo->query('SELECT id FROM branches');
    $branchIds  = $branchStmt->fetchAll(PDO::FETCH_COLUMN);
} elseif (empty($cabangList)) {
    $branchIds = [];
} else {
    $placeholders = implode(',', array_fill(0, count($cabangList), '?'));
    $branchStmt   = $pdo->prepare("SELECT id FROM branches WHERE nama IN ($placeholders)");
    $branchStmt->execute($cabangList);
    $branchIds = $branchStmt->fetchAll(PDO::FETCH_COLUMN);
}

if (empty($branchIds)) {
    echo json_encode(['status' => 'success', 'orders' => []]);
    exit;
}

// ── Ambil order dengan status aktif (menunggu bayar, sudah bayar, disiapkan) untuk cabang-cabang tersebut ──
$idPlaceholders = implode(',', array_fill(0, count($branchIds), '?'));
$sql = "SELECT orders.*, branches.nama AS cabang
        FROM orders
        JOIN branches ON branches.id = orders.branch_id
        WHERE orders.branch_id IN ($idPlaceholders)
        AND orders.status IN ('pending_payment', 'paid', 'preparing')
        ORDER BY orders.id DESC";

$orderStmt = $pdo->prepare($sql);
$orderStmt->execute($branchIds);
$orders = $orderStmt->fetchAll();

// ── Sertakan item pesanan (satu query bulk, bukan N+1) supaya staff bisa lihat rincian
// tanpa buka detail terpisah ──
if ($orders) {
    $orderIds = array_column($orders, 'id');
    $itemPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ($itemPlaceholders)");
    $itemStmt->execute($orderIds);
    $itemsByOrder = [];
    foreach ($itemStmt->fetchAll() as $item) {
        $itemsByOrder[(int)$item['order_id']][] = $item;
    }
    foreach ($orders as &$o) {
        $o['items'] = $itemsByOrder[(int)$o['id']] ?? [];
    }
    unset($o);
}

echo json_encode(['status' => 'success', 'orders' => $orders]);
