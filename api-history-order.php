<?php
require_once __DIR__ . '/includes/db_order.php';

header('Content-Type: application/json');

$user   = trim($_GET['user']   ?? '');
$token  = trim($_GET['token']  ?? '');
$status = trim($_GET['status'] ?? '');
$branchFilter = (int)($_GET['branch_id'] ?? 0);
$date   = trim($_GET['date'] ?? '');

if ($user === '' || $token === '') {
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

// ── Cabang yang boleh diakses user ini (sama aturan seperti api-cek-order-baru.php) ──
if ($cabangRaw === 'Semua') {
    $branchStmt = $pdo->query('SELECT id, nama FROM branches ORDER BY nama ASC');
    $accessibleBranches = $branchStmt->fetchAll();
} elseif (empty($cabangList)) {
    $accessibleBranches = [];
} else {
    $placeholders = implode(',', array_fill(0, count($cabangList), '?'));
    $branchStmt   = $pdo->prepare("SELECT id, nama FROM branches WHERE nama IN ($placeholders) ORDER BY nama ASC");
    $branchStmt->execute($cabangList);
    $accessibleBranches = $branchStmt->fetchAll();
}

$accessibleIds = array_map(fn($b) => (int)$b['id'], $accessibleBranches);

if (empty($accessibleIds)) {
    echo json_encode(['status' => 'success', 'orders' => [], 'branches' => []]);
    exit;
}

// Kalau ada filter branch_id, pastikan tetap dalam batas cabang yang diizinkan
$branchIds = $accessibleIds;
if ($branchFilter > 0) {
    $branchIds = in_array($branchFilter, $accessibleIds, true) ? [$branchFilter] : [];
}

if (empty($branchIds)) {
    echo json_encode(['status' => 'success', 'orders' => [], 'branches' => $accessibleBranches]);
    exit;
}

$validStatuses = ['pending_payment', 'paid', 'preparing', 'ready', 'completed', 'cancelled'];

$idPlaceholders = implode(',', array_fill(0, count($branchIds), '?'));
$where  = ["orders.branch_id IN ($idPlaceholders)"];
$params = $branchIds;

if ($status !== '' && in_array($status, $validStatuses, true)) {
    $where[] = 'orders.status = ?';
    $params[] = $status;
}
if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $where[] = 'DATE(orders.created_at) = ?';
    $params[] = $date;
}

$sql = "SELECT orders.*, branches.nama AS cabang
        FROM orders
        JOIN branches ON branches.id = orders.branch_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY orders.id DESC
        LIMIT 200";

$orderStmt = $pdo->prepare($sql);
$orderStmt->execute($params);
$orders = $orderStmt->fetchAll();

echo json_encode(['status' => 'success', 'orders' => $orders, 'branches' => $accessibleBranches]);
