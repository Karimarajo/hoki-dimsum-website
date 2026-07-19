<?php
require_once __DIR__ . '/includes/db_order.php';

header('Content-Type: application/json');

$user    = trim($_GET['user']  ?? '');
$token   = trim($_GET['token'] ?? '');
$orderId = (int)($_GET['order_id'] ?? 0);

if ($user === '' || $token === '' || $orderId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid']);
    exit;
}

// ── Koneksi database utama (sama seperti api-cek-order-baru.php) ──
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

// ── Ambil order + info cabang ──
$stmtOrder = $pdo->prepare('SELECT orders.*, branches.nama AS cabang
    FROM orders JOIN branches ON branches.id = orders.branch_id
    WHERE orders.id = ?');
$stmtOrder->execute([$orderId]);
$order = $stmtOrder->fetch();

if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Order tidak ditemukan']);
    exit;
}

// ── Validasi akses cabang (aturan sama seperti api-update-status-order.php) ──
$punyaAkses = ($cabangRaw === 'Semua') || in_array($order['cabang'], $cabangList, true);
if (!$punyaAkses) {
    echo json_encode(['status' => 'error', 'message' => 'Kamu tidak punya akses ke cabang order ini']);
    exit;
}

$stmtItems = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$stmtItems->execute([$orderId]);
$items = $stmtItems->fetchAll();

echo json_encode(['status' => 'success', 'order' => $order, 'items' => $items]);
