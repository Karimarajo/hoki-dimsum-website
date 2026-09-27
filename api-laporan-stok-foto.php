<?php
/**
 * api-laporan-stok-foto.php — Backend "Foto Laporan Lapak" (sesi kamera
 * in-browser setelah staff simpan Stok Harian) + setting global "Nomor/Link
 * Grup WA Tujuan Laporan Stok". File terpisah dari api.php (mengikuti pola
 * api-pelamar-kerja.php dkk) karena butuh handle upload file multipart.
 *
 * INDEPENDEN dari fitur Peer Review (api-penilaian.php) - tidak menyentuh
 * tabel penilaian_* sama sekali.
 */

header('Content-Type: application/json');

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
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}
$conn->set_charset('utf8mb4');

define('FOTO_UPLOAD_DIR', __DIR__ . '/uploads/laporan_stok_foto');
define('MAX_FOTO_SIZE', 8 * 1024 * 1024); // 8MB - hasil canvas.toBlob JPEG kualitas wajar jauh di bawah ini

function respond(array $data): void { echo json_encode($data); exit; }

/** Verifikasi identitas & role ke DB via session_token - tidak pernah percaya role dari client. */
function verify_actor(mysqli $conn, string $user, string $token): ?array
{
    if ($user === '' || $token === '') return null;
    $stmt = $conn->prepare("SELECT id, username, fullName, role FROM users WHERE LOWER(username) = LOWER(?) AND session_token = ? AND session_token != ''");
    $stmt->bind_param('ss', $user, $token);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

function require_actor(mysqli $conn): array
{
    $u = trim($_GET['user'] ?? $_POST['user'] ?? '');
    $t = trim($_GET['token'] ?? $_POST['token'] ?? '');
    $actor = verify_actor($conn, $u, $t);
    if (!$actor) {
        http_response_code(401);
        respond(['status' => 'error', 'message' => 'Sesi tidak valid atau sudah berakhir. Silakan login ulang.']);
    }
    return $actor;
}

function require_vip(mysqli $conn): array
{
    $actor = require_actor($conn);
    if ($actor['role'] !== 'VIP') {
        http_response_code(403);
        respond(['status' => 'error', 'message' => 'Akses ditolak. Fitur ini khusus VIP.']);
    }
    return $actor;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    // Setting "Nomor/Link Grup WA Tujuan Laporan Stok" - dibaca semua staff
    // yang login (ditampilkan sebagai instruksi di layar kirim), diubah VIP saja.
    case 'get_setting_wa_stok': {
        require_actor($conn);
        $res = $conn->query("SELECT nilai FROM pengaturan_sistem WHERE nama_setting = 'wa_tujuan_laporan_stok'");
        $row = $res ? $res->fetch_assoc() : null;
        respond(['status' => 'success', 'nilai' => $row['nilai'] ?? '']);
    }

    case 'save_setting_wa_stok': {
        require_vip($conn);
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $nilai = trim($input['nilai'] ?? '');
        $stmt = $conn->prepare("INSERT INTO pengaturan_sistem (nama_setting, nilai) VALUES ('wa_tujuan_laporan_stok', ?)
                                 ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        $stmt->bind_param('s', $nilai);
        $stmt->execute();
        respond(['status' => 'success', 'message' => 'Setting WA tujuan laporan stok disimpan.']);
    }

    // Upload 1 foto laporan lapak (multipart/form-data) - dipanggil sekali per
    // foto yang diambil staff, TERLEPAS dari apakah foto itu akhirnya dicentang
    // untuk dikirim WA atau tidak (supaya semua foto punya jejak di sistem).
    case 'upload_foto': {
        $actor = require_actor($conn);

        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            respond(['status' => 'error', 'message' => 'File foto tidak diterima server.']);
        }
        $file = $_FILES['foto'];
        if ($file['size'] > MAX_FOTO_SIZE) {
            respond(['status' => 'error', 'message' => 'Ukuran foto maksimal 8MB.']);
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!isset($allowed[$mime])) {
            respond(['status' => 'error', 'message' => 'Format foto harus JPG, PNG, atau WEBP.']);
        }

        $cabangNama = trim($_POST['cabang'] ?? '');
        $waktuAmbil = trim($_POST['waktu_ambil'] ?? '');
        $lokasi     = trim($_POST['lokasi_koordinat'] ?? '');

        // Validasi format datetime dari client ('Y-m-d H:i:s') - kalau tidak
        // sesuai/kosong, fallback ke waktu server supaya baris tetap tercatat.
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $waktuAmbil)) {
            $waktuAmbil = date('Y-m-d H:i:s');
        }
        if ($lokasi === '') $lokasi = null;

        $cabangId = null;
        if ($cabangNama !== '') {
            $stmtC = $conn->prepare("SELECT id FROM hoki_cabang WHERE nama_cabang = ? LIMIT 1");
            $stmtC->bind_param('s', $cabangNama);
            $stmtC->execute();
            $rowC = $stmtC->get_result()->fetch_assoc();
            if ($rowC) $cabangId = (int)$rowC['id'];
        }

        $ext      = $allowed[$mime];
        $filename = 'stok-foto_' . $actor['id'] . '_' . date('Ymd-His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!is_dir(FOTO_UPLOAD_DIR)) {
            mkdir(FOTO_UPLOAD_DIR, 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], FOTO_UPLOAD_DIR . '/' . $filename)) {
            respond(['status' => 'error', 'message' => 'Gagal menyimpan file foto di server.']);
        }

        $stmt = $conn->prepare("INSERT INTO laporan_stok_foto (staff_id, cabang_id, cabang_nama, nama_file, waktu_ambil, lokasi_koordinat)
                                 VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('iissss', $actor['id'], $cabangId, $cabangNama, $filename, $waktuAmbil, $lokasi);
        $stmt->execute();

        respond([
            'status'    => 'success',
            'id'        => $conn->insert_id,
            'nama_file' => $filename,
            'url'       => 'uploads/laporan_stok_foto/' . $filename,
        ]);
    }

    default:
        respond(['status' => 'error', 'message' => "Action '$action' tidak dikenali."]);
}
