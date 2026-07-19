<?php
require_once __DIR__ . '/includes/db_order.php';

header('Content-Type: application/json');

$user       = trim($_POST['user'] ?? '');
$token      = trim($_POST['token'] ?? '');
$orderId    = (int)($_POST['order_id'] ?? 0);
$statusBaru = trim($_POST['status_baru'] ?? '');

$allowedStatus = ['paid', 'preparing', 'ready', 'completed'];

if ($user === '' || $token === '' || $orderId <= 0 || !in_array($statusBaru, $allowedStatus, true)) {
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

// ── Ambil branch_id + nama cabang dari order yang mau diubah ──
$stmtOrder = $pdo->prepare('SELECT orders.branch_id, branches.nama AS cabang_nama
    FROM orders JOIN branches ON branches.id = orders.branch_id
    WHERE orders.id = ?');
$stmtOrder->execute([$orderId]);
$orderRow = $stmtOrder->fetch();

if (!$orderRow) {
    echo json_encode(['status' => 'error', 'message' => 'Order tidak ditemukan']);
    exit;
}

// ── Validasi akses cabang ──
// Hanya "Semua" yang berarti akses ke semua cabang. Cabang kosong = tidak ada akses.
$punyaAkses = ($cabangRaw === 'Semua') || in_array($orderRow['cabang_nama'], $cabangList, true);

if (!$punyaAkses) {
    echo json_encode(['status' => 'error', 'message' => 'Kamu tidak punya akses ke cabang order ini']);
    exit;
}

// ── Update status ──
$stmtUpdate = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
$stmtUpdate->execute([$statusBaru, $orderId]);

echo json_encode(['status' => 'success']);
