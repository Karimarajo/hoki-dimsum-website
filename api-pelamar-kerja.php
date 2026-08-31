<?php
/**
 * api-pelamar-kerja.php — Backend "Data Pelamar Kerja" (menu Executive).
 * File terpisah dari api.php (mengikuti pola api-cek-order-baru.php dkk)
 * karena butuh handle upload file (multipart/form-data), bukan JSON biasa.
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

$conn->query("CREATE TABLE IF NOT EXISTS pelamar_kerja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    alamat VARCHAR(255) DEFAULT '',
    umur INT DEFAULT 0,
    no_wa VARCHAR(30) DEFAULT '',
    cv_file VARCHAR(255) DEFAULT '',
    created_by VARCHAR(100) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

define('UPLOAD_DIR', __DIR__ . '/uploads/pelamar_cv');
define('MAX_CV_SIZE', 5 * 1024 * 1024); // 5MB

/** Verifikasi identitas & role pemanggil ke DB (tidak percaya role dari client). */
function verify_actor(mysqli $conn, string $user, string $token): ?array
{
    if ($user === '' || $token === '') return null;
    $stmt = $conn->prepare("SELECT username, role FROM users WHERE LOWER(username) = LOWER(?) AND session_token = ? AND session_token != ''");
    $stmt->bind_param('ss', $user, $token);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

function upload_cv(array $file): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null]; // CV opsional
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload CV gagal (kode error ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_CV_SIZE) {
        throw new RuntimeException('Ukuran file CV maksimal 5MB.');
    }
    $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format CV harus PDF, JPG, atau PNG.');
    }
    $ext = $allowed[$mime];
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
        throw new RuntimeException('Gagal menyimpan file CV.');
    }
    return [$filename, $ext];
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    case 'list': {
        $res = $conn->query('SELECT * FROM pelamar_kerja ORDER BY id DESC');
        echo json_encode(['status' => 'success', 'data' => $res ? $res->fetch_all(MYSQLI_ASSOC) : []]);
        break;
    }

    case 'save': {
        $actor = verify_actor($conn, $_POST['user'] ?? '', $_POST['token'] ?? '');
        if (!$actor || !in_array($actor['role'], ['Owner', 'VIP'], true)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid atau tidak punya akses.']);
            break;
        }

        $nama  = trim($_POST['nama'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $umur  = (int)($_POST['umur'] ?? 0);
        $noWa  = trim($_POST['no_wa'] ?? '');

        if ($nama === '') {
            echo json_encode(['status' => 'error', 'message' => 'Nama wajib diisi.']);
            break;
        }

        try {
            [$cvFilename, ] = upload_cv($_FILES['cv'] ?? []);
        } catch (RuntimeException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            break;
        }

        $stmt = $conn->prepare('INSERT INTO pelamar_kerja (nama, alamat, umur, no_wa, cv_file, created_by) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('ssisss', $nama, $alamat, $umur, $noWa, $cvFilename, $actor['username']);
        $stmt->execute();

        echo json_encode(['status' => 'success', 'message' => 'Data pelamar disimpan.']);
        break;
    }

    case 'delete': {
        $actor = verify_actor($conn, $_POST['user'] ?? '', $_POST['token'] ?? '');
        if (!$actor || !in_array($actor['role'], ['Owner', 'VIP'], true)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid atau tidak punya akses.']);
            break;
        }

        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare('SELECT cv_file FROM pelamar_kerja WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            $stmt2 = $conn->prepare('DELETE FROM pelamar_kerja WHERE id = ?');
            $stmt2->bind_param('i', $id);
            $stmt2->execute();
            if (!empty($row['cv_file'])) {
                $path = UPLOAD_DIR . '/' . $row['cv_file'];
                if (is_file($path)) @unlink($path);
            }
        }
        echo json_encode(['status' => 'success', 'message' => 'Data pelamar dihapus.']);
        break;
    }

    default:
        echo json_encode(['status' => 'error', 'message' => "Action '$action' tidak dikenali."]);
}
