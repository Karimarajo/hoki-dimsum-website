<?php
require_once __DIR__ . '/db.php';

function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function rupiah($angka): string
{
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function get_setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $stmt = db()->query('SELECT setting_key, setting_value FROM site_settings');
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function generate_order_code(): string
{
    $prefix = 'HD-' . date('Ymd') . '-';
    $stmt = db()->prepare("SELECT order_code FROM orders WHERE order_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last) {
        $seq = (int)substr($last, -4) + 1;
    }
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function generate_kode_unik(string $tanggal): int
{
    // Ambil kode unik yang sudah dipakai hari itu supaya tidak bentrok
    $stmt = db()->prepare("SELECT kode_unik FROM orders WHERE pickup_date = ? OR DATE(created_at) = CURDATE()");
    $stmt->execute([$tanggal]);
    $used = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    do {
        $kode = random_int(1, 999);
    } while (in_array($kode, $used, true));
    return $kode;
}

function wa_link(string $number, string $message): string
{
    $number = preg_replace('/[^0-9]/', '', $number);
    if (substr($number, 0, 1) === '0') {
        $number = '62' . substr($number, 1);
    }
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
}

function upload_image(array $file, string $subdir): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas maksimal server (' . ini_get('upload_max_filesize') . '). Kompres dulu gambarnya lalu coba lagi.',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas maksimal form.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian. Coba upload ulang.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server tidak punya folder sementara untuk upload.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk server.',
            UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi PHP di server.',
        ];
        throw new RuntimeException($errorMessages[$file['error']] ?? 'Upload file gagal (kode error ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Ukuran file maksimal 2MB.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format file harus JPG, PNG, atau WEBP.');
    }
    $ext = $allowed[$mime];
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $targetDir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $filename)) {
        throw new RuntimeException('Gagal menyimpan file upload.');
    }
    return $subdir . '/' . $filename;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function day_id(string $englishDay): string
{
    $map = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
    ];
    return $map[$englishDay] ?? $englishDay;
}

function day_names(): array
{
    // Kunci mengikuti DateTime::format('w'): 0=Minggu ... 6=Sabtu
    return [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
}

function get_branch_hours(int $branchId): array
{
    $stmt = db()->prepare('SELECT * FROM branch_hours WHERE branch_id = ?');
    $stmt->execute([$branchId]);
    $hours = [];
    foreach ($stmt->fetchAll() as $row) {
        $hours[(int)$row['hari']] = $row;
    }
    return $hours;
}

function branch_is_open_now(array $hoursByDay): bool
{
    $now = new DateTime();
    $today = $hoursByDay[(int)$now->format('w')] ?? null;
    if (!$today) return true; // belum diatur -> tidak dibatasi
    if ((int)$today['is_closed'] === 1) return false;
    if (empty($today['buka']) || empty($today['tutup'])) return true; // jam kosong -> tidak dibatasi
    $nowTime = $now->format('H:i:s');
    return $nowTime >= $today['buka'] && $nowTime <= $today['tutup'];
}

function products_unavailable_at_branch(array $productIds, int $branchId): array
{
    $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
    if (!$productIds || $branchId <= 0) return [];
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = db()->prepare("SELECT p.nama FROM product_branch_unavailable u
        JOIN products p ON p.id = u.product_id
        WHERE u.branch_id = ? AND u.product_id IN ($placeholders)");
    $stmt->execute([$branchId, ...$productIds]);
    return array_column($stmt->fetchAll(), 'nama');
}
