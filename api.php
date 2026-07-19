<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

// ── AUTO DETECT: DEV atau PRODUCTION ──────────────────
$host  = $_SERVER['HTTP_HOST'] ?? '';
// Cek environment - dev jika tidak ada dot di host (localhost, 127.0.0.1, dll)
// atau jika file dev.flag ada di folder
$isDev = (
    $host === 'localhost' || 
    $host === '127.0.0.1' || 
    substr($host, 0, 8) === '192.168.' ||
    strpos($host, 'localhost') !== false ||
    strpos($host, ':') !== false ||
    file_exists(__DIR__ . '/dev.flag')
);

// Semua action sekarang langsung ke database (production DB sudah di-import ke XAMPP lokal)
// Dev dummy data block di-skip karena sudah tidak diperlukan lagi
if (false) {
    // ── MODE DEV: gunakan dummy data ──────────────────
    $dummyUsers = [
        ['id'=>1,'user'=>'admin',  'pass'=>'admin',  'role'=>'VIP',          'cabang'=>'Semua',                       'fullName'=>'Administrator'],
        ['id'=>2,'user'=>'owner',  'pass'=>'owner',  'role'=>'Owner',        'cabang'=>'Semua',                       'fullName'=>'Owner Hoki'],
        ['id'=>3,'user'=>'spv1',   'pass'=>'spv1',   'role'=>'SPV',          'cabang'=>'Pusat',                       'fullName'=>'Supervisor Pusat'],
        ['id'=>4,'user'=>'senior', 'pass'=>'senior', 'role'=>'Senior Staff', 'cabang'=>'Pusat,Cabang A',              'fullName'=>'Senior Staff'],
        ['id'=>5,'user'=>'staff1', 'pass'=>'staff1', 'role'=>'Staff',        'cabang'=>'Pusat,Cabang A,Cabang B',     'fullName'=>'Staff Kasir'],
    ];

    switch ($action) {
        case 'login':
            $u = $input['user'] ?? '';
            $p = $input['pass'] ?? '';
            $found = null;
            foreach ($dummyUsers as $user) {
                if (strtolower($user['user']) === strtolower($u) && $user['pass'] === $p) { $found = $user; break; }
            }
            if ($found) {
                echo json_encode(['status'=>'success','user'=>['user'=>$found['user'],'fullName'=>$found['fullName'],'role'=>$found['role'],'cabang'=>$found['cabang']],'token'=>bin2hex(random_bytes(32))]);
            } else {
                echo json_encode(['status'=>'error','message'=>'Username atau Password salah!']);
            }
            break;
        case 'check_session':
            echo json_encode(['valid'=>true]);
            break;
        case 'get_cabang': case 'get_branches':
            echo json_encode([['id'=>1,'nama_cabang'=>'Pusat'],['id'=>2,'nama_cabang'=>'Cabang A'],['id'=>3,'nama_cabang'=>'Cabang B']]);
            break;
        case 'get_produk':
            echo json_encode([
                ['id'=>1,'sku'=>'DMS','nama'=>'Dimsum Ayam',  'harga'=>15000,'hpp'=>8000,'dimsumPcs'=>4,'aluTrayPcs'=>2,'urutan'=>1],
                ['id'=>2,'sku'=>'SIW','nama'=>'Siomay',       'harga'=>15000,'hpp'=>7000,'dimsumPcs'=>4,'aluTrayPcs'=>2,'urutan'=>2],
                ['id'=>3,'sku'=>'HAK','nama'=>'Hakau',        'harga'=>18000,'hpp'=>9000,'dimsumPcs'=>3,'aluTrayPcs'=>2,'urutan'=>3],
                ['id'=>4,'sku'=>'CSP','nama'=>'Ceker Spesial','harga'=>20000,'hpp'=>0,   'dimsumPcs'=>2,'aluTrayPcs'=>1,'urutan'=>4],
                ['id'=>5,'sku'=>'NAS','nama'=>'Nasi Putih',   'harga'=>5000, 'hpp'=>0,   'dimsumPcs'=>0,'aluTrayPcs'=>0,'urutan'=>5],
            ]);
            break;
        case 'get_history':
            // Cek filter opsional untuk optimasi performa load data
            $tglMulai = isset($_GET['tgl_mulai']) ? $conn->real_escape_string($_GET['tgl_mulai']) : '';
            $tglSelesai = isset($_GET['tgl_selesai']) ? $conn->real_escape_string($_GET['tgl_selesai']) : '';
            $cab = isset($_GET['cabang']) ? $conn->real_escape_string($_GET['cabang']) : '';

            $where = [];
            if ($tglMulai) {
                $where[] = "waktu >= '{$tglMulai} 00:00:00'";
            }
            if ($tglSelesai) {
                $where[] = "waktu <= '{$tglSelesai} 23:59:59'";
            }
            if ($cab && strtolower($cab) !== 'semua') {
                $where[] = "LOWER(cabang) = LOWER('$cab')";
            }

            $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
            
            // Pengurutan waktu diserahkan langsung ke database agar browser tidak lag
            // Menggunakan LIMIT jika tidak ada filter agar pemuatan awal instan
            $limitClause = ($tglMulai || $tglSelesai) ? "" : "LIMIT 300";

            $res = $conn->query("SELECT id, waktu, cabang, petugas, total, metode, items_json FROM transaksi $whereClause ORDER BY waktu DESC, id DESC $limitClause");
            echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
            break;
        case 'get_users':
            echo json_encode(array_map(fn($u) => ['id'=>$u['id'],'username'=>$u['user'],'password'=>$u['pass'],'role'=>$u['role'],'cabang'=>$u['cabang'],'fullName'=>$u['fullName'],'docs_json'=>'{}'], $dummyUsers));
            break;
        case 'get_master_stok':
            echo json_encode([['id'=>1,'nama_item'=>'Tepung'],['id'=>2,'nama_item'=>'Ayam'],['id'=>3,'nama_item'=>'Udang'],['id'=>4,'nama_item'=>'Minyak'],['id'=>5,'nama_item'=>'Bumbu']]);
            break;
        case 'get_roles':
            echo json_encode([['id'=>1,'nama_role'=>'VIP'],['id'=>2,'nama_role'=>'Owner'],['id'=>3,'nama_role'=>'SPV'],['id'=>4,'nama_role'=>'Senior Staff'],['id'=>5,'nama_role'=>'Staff']]);
            break;
        case 'get_omset_harian':
            echo json_encode(['total'=>33000,'jumlah'=>2]);
            break;
        case 'get_bahan_baku':
            echo json_encode([
                ['id'=>1,'nama'=>'Mentai','harga'=>50000,'banyak'=>1000,'satuan'=>'gr','harga_satuan'=>50],
                ['id'=>2,'nama'=>'Ayam','harga'=>30000,'banyak'=>500,'satuan'=>'gr','harga_satuan'=>60],
                ['id'=>3,'nama'=>'Tepung','harga'=>10000,'banyak'=>1000,'satuan'=>'gr','harga_satuan'=>10],
                ['id'=>4,'nama'=>'Tray Aluminium','harga'=>25000,'banyak'=>100,'satuan'=>'pcs','harga_satuan'=>250],
            ]);
            break;
        case 'get_hpp_produk':
            echo json_encode([
                ['id'=>1,'nama_produk'=>'Dimsum Ayam','sku'=>'DMS','harga_pokok'=>8000,'detail_json'=>'[{"bahan_id":2,"nama":"Ayam","qty":100,"satuan":"gr","harga_satuan":60,"subtotal":6000},{"bahan_id":3,"nama":"Tepung","qty":50,"satuan":"gr","harga_satuan":10,"subtotal":500}]'],
                ['id'=>2,'nama_produk'=>'Hakau','sku'=>'HAK','harga_pokok'=>9000,'detail_json'=>'[{"bahan_id":1,"nama":"Mentai","qty":100,"satuan":"gr","harga_satuan":50,"subtotal":5000}]'],
            ]);
            break;
        case 'get_warehouse_stok':
            echo json_encode([]);
            break;
        case 'save_warehouse_stok':
            echo json_encode(['status'=>'success']);
            break;
        case 'get_warehouse_ledger':
            $sku = $_GET['sku'] ?? 'DMS';
            echo json_encode([
                ['tgl'=>date('Y-m-d'),'stok_awal'=>100,'masuk'=>50,'keluar'=>30,'sisa'=>120],
                ['tgl'=>date('Y-m-d',strtotime('-1 day')),'stok_awal'=>80,'masuk'=>50,'keluar'=>30,'sisa'=>100],
                ['tgl'=>date('Y-m-d',strtotime('-2 days')),'stok_awal'=>0,'masuk'=>80,'keluar'=>0,'sisa'=>80],
            ]);
            break;
        case 'save_warehouse_masuk':
            echo json_encode(['status'=>'success']);
            break;
        case 'save_bahan_baku': case 'del_bahan_baku':
        case 'save_hpp_produk': case 'del_hpp_produk':
            echo json_encode(['status'=>'success']);
            break;
        case 'save_produk': case 'del_produk': case 'update_urutan':
        case 'save_transaksi': case 'del_transaksi': case 'clear_history':
        case 'save_user': case 'del_user':
        case 'save_cabang': case 'del_cabang':
        case 'save_role': case 'del_role':
        case 'save_master_stok': case 'del_master_stok':
        case 'get_laporan_stok': case 'save_laporan_stok': case 'del_laporan_stok':
        case 'get_laporan_restock': case 'save_laporan_restock': case 'del_laporan_restock':
        case 'get_kas_jenis': case 'save_kas_jenis': case 'del_kas_jenis':
        case 'get_kas_data': case 'save_kas_data': case 'del_kas_data':
        case 'get_laporan_history': case 'save_laporan': case 'del_laporan':
            echo json_encode(['status'=>'success']);
            break;
        default:
            echo json_encode(['status'=>'error','message'=>"Action '$action' tidak dikenali (dev mode)."]);
    }
    exit();
}

// ── MODE PRODUCTION & DEVELOPMENT DATABASE CONNECTION ──
$conn = null;
if ($isDev) {
    // Koneksi khusus untuk XAMPP Lokal setelah import database production
    try {
        $conn = @new mysqli("localhost", "root", "", "u173485424_hoki");
    } catch (Exception $e) {
        $conn = null;
    }
} else {
    // Koneksi asli Hosting Production (Live)
    try {
        $conn = @new mysqli("localhost", "u173485424_kurniarp", "Alpukat19#", "u173485424_hoki");
    } catch (Exception $e) {
        $conn = null;
    }
}

if (!$conn || $conn->connect_error) {
    ob_end_clean();
    die(json_encode(["status" => "error", "message" => "Koneksi database gagal: " . ($conn ? $conn->connect_error : "Server mati")]));
}

$conn->query("SET time_zone = '+07:00'"); 
$conn->set_charset("utf8mb4");

// ── AUTO-CREATE ESSENTIAL TABLES ──────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE,
    password VARCHAR(255) DEFAULT '',
    role VARCHAR(50) DEFAULT 'Staff',
    fullName VARCHAR(200) DEFAULT '',
    cabang VARCHAR(255) DEFAULT '',
    docs_json TEXT,
    session_token VARCHAR(255) DEFAULT ''
)");

// Seed default users jika masih kosong
$checkUsers = $conn->query("SELECT COUNT(*) as count FROM users");
$row = $checkUsers ? $checkUsers->fetch_assoc() : ['count' => 0];
if ((int)$row['count'] === 0) {
    $conn->query("INSERT INTO users (username, password, role, fullName, cabang) VALUES 
        ('admin', 'admin', 'VIP', 'Administrator', 'Semua'),
        ('owner', 'owner', 'Owner', 'Owner Hoki', 'Semua'),
        ('spv1', 'spv1', 'SPV', 'Supervisor Pusat', 'Pusat'),
        ('senior', 'senior', 'Senior Staff', 'Senior Staff', 'Pusat,Cabang A'),
        ('staff1', 'staff1', 'Staff', 'Staff Kasir', 'Pusat,Cabang A,Cabang B')");
} else {
    // Kembalikan update nama asli (untuk mendeteksi nama asli dari manajemen user)
    $conn->query("UPDATE users SET fullName = 'Supervisor Pusat' WHERE username = 'spv1' AND fullName = 'Dwi Hartono'");
    $conn->query("UPDATE users SET fullName = 'Senior Staff' WHERE username = 'senior' AND fullName = 'Agus Prayogo'");
    $conn->query("UPDATE users SET fullName = 'Staff Kasir' WHERE username = 'staff1' AND fullName = 'Budi Santoso'");
}
$conn->query("CREATE TABLE IF NOT EXISTS produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(20) DEFAULT '',
    nama VARCHAR(150) DEFAULT '',
    harga INT DEFAULT 0,
    hpp INT DEFAULT 0,
    dimsumPcs INT DEFAULT 0,
    aluTrayPcs INT DEFAULT 0,
    urutan INT DEFAULT 0
)");
$conn->query("CREATE TABLE IF NOT EXISTS transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cabang VARCHAR(100) DEFAULT '',
    petugas VARCHAR(100) DEFAULT '',
    total BIGINT DEFAULT 0,
    metode VARCHAR(50) DEFAULT 'CASH',
    items_json TEXT
)");

$conn->query("CREATE TABLE IF NOT EXISTS logs_login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waktu TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    username VARCHAR(100) NOT NULL DEFAULT '',
    role VARCHAR(50) NOT NULL DEFAULT '',
    cabang VARCHAR(100) NOT NULL DEFAULT ''
)");
$conn->query("CREATE TABLE IF NOT EXISTS hoki_cabang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_cabang VARCHAR(100) UNIQUE
)");
$conn->query("CREATE TABLE IF NOT EXISTS hoki_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_role VARCHAR(100) UNIQUE
)");
$conn->query("CREATE TABLE IF NOT EXISTS bahan_baku (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) DEFAULT '',
    harga FLOAT DEFAULT 0,
    banyak FLOAT DEFAULT 0,
    satuan VARCHAR(20) DEFAULT 'gr',
    harga_satuan FLOAT DEFAULT 0
)");
$conn->query("CREATE TABLE IF NOT EXISTS hpp_produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_produk VARCHAR(150) DEFAULT '',
    sku VARCHAR(20) DEFAULT '',
    harga_pokok FLOAT DEFAULT 0
)");
$conn->query("CREATE TABLE IF NOT EXISTS hpp_produk_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hpp_id INT DEFAULT 0,
    bahan_id INT DEFAULT 0,
    qty FLOAT DEFAULT 0,
    subtotal FLOAT DEFAULT 0
)");
$conn->query("CREATE TABLE IF NOT EXISTS hoki_kas_jenis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_jenis VARCHAR(100) UNIQUE
)");
$conn->query("CREATE TABLE IF NOT EXISTS hoki_kas_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user VARCHAR(100) DEFAULT '',
    jenis VARCHAR(100) DEFAULT '',
    nama VARCHAR(200) DEFAULT '',
    qty INT DEFAULT 1,
    mode VARCHAR(20) DEFAULT '',
    nominal BIGINT DEFAULT 0,
    ket TEXT,
    cabang VARCHAR(100) DEFAULT ''
)");
$conn->query("CREATE TABLE IF NOT EXISTS laporan_settlement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    report_id VARCHAR(100) UNIQUE,
    petugas VARCHAR(100) DEFAULT '',
    cabang VARCHAR(100) DEFAULT '',
    metode_json TEXT,
    audit_json TEXT,
    pengeluaran_json TEXT,
    grand_total BIGINT DEFAULT 0
)");
$conn->query("ALTER TABLE laporan_settlement ADD COLUMN IF NOT EXISTS waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
$conn->query("CREATE TABLE IF NOT EXISTS stok_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_item VARCHAR(150) UNIQUE
)");
$conn->query("CREATE TABLE IF NOT EXISTS stok_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    report_id VARCHAR(100) DEFAULT '',
    petugas VARCHAR(100) DEFAULT '',
    cabang VARCHAR(100) DEFAULT '',
    items_json TEXT
)");
$conn->query("CREATE TABLE IF NOT EXISTS restock_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    report_id VARCHAR(100) DEFAULT '',
    waktu_teks VARCHAR(100) DEFAULT '',
    petugas VARCHAR(100) DEFAULT '',
    cabang VARCHAR(100) DEFAULT '',
    items_json TEXT
)");
$conn->query("CREATE TABLE IF NOT EXISTS warehouse_stok (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tgl DATE NOT NULL,
    bahan_id INT NOT NULL,
    stok_awal FLOAT DEFAULT 0,
    stok_masuk FLOAT DEFAULT 0,
    UNIQUE KEY uq_wh (tgl, bahan_id)
)");
$conn->query("CREATE TABLE IF NOT EXISTS warehouse_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tgl DATE NOT NULL,
    sku VARCHAR(50) NOT NULL,
    masuk FLOAT DEFAULT 0,
    catatan VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS hoki_salary_config (
    role_name VARCHAR(50) UNIQUE PRIMARY KEY,
    gaji_weekday INT DEFAULT 0,
    gaji_weekend INT DEFAULT 0,
    gaji_bulanan INT DEFAULT 0,
    tunjangan_jabatan INT DEFAULT 0,
    bonus_harian INT DEFAULT 0,
    bonus_mingguan INT DEFAULT 0,
    bonus_bulanan INT DEFAULT 0,
    bonus_harian_jabatan INT DEFAULT 0,
    bonus_mingguan_jabatan INT DEFAULT 0,
    bonus_bulanan_jabatan INT DEFAULT 0,
    target_omset_harian INT DEFAULT 450000,
    target_omset_mingguan INT DEFAULT 3500000,
    target_omset_bulanan_30 INT DEFAULT 15000000,
    target_omset_bulanan_31 INT DEFAULT 15500000,
    target_hadir_mingguan INT DEFAULT 5,
    target_hadir_bulanan INT DEFAULT 25
)");

$roles_default = ['Junior Staff', 'Senior Staff', 'Supervisor', 'Lead Supervisor', 'Manager Operasional', 'Finance & Accounting', 'HRD', 'Supply Chain & Logistics', 'Marketing', 'Magang'];
foreach ($roles_default as $r_def) {
    $conn->query("INSERT IGNORE INTO hoki_salary_config (role_name) VALUES ('$r_def')");
}

$conn->query("CREATE TABLE IF NOT EXISTS hoki_staff_hierarchy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    atasan_username VARCHAR(100) NOT NULL,
    bawahan_username VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_hierarchy (atasan_username, bawahan_username)
)");

$conn->query("CREATE TABLE IF NOT EXISTS hoki_investor_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investor_username VARCHAR(100) NOT NULL,
    nama_cabang VARCHAR(100) NOT NULL,
    persentase_bagi_hasil FLOAT DEFAULT 0,
    UNIQUE KEY uq_inv_cb (investor_username, nama_cabang)
)");

$conn->query("INSERT IGNORE INTO hoki_roles (nama_role) VALUES ('Investor')");

$conn->query("CREATE TABLE IF NOT EXISTS hoki_profit_sharing_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal_rekam DATE NOT NULL,
    bulan_tahun VARCHAR(50) NOT NULL,
    investor_username VARCHAR(100) NOT NULL,
    cabang TEXT,
    omset BIGINT DEFAULT 0,
    laba_kotor BIGINT DEFAULT 0,
    dana_operasional BIGINT DEFAULT 0,
    hak_investor BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ── Tabel Pelanggan (Member) ──
$conn->query("CREATE TABLE IF NOT EXISTS hoki_pelanggan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL DEFAULT '',
    telepon VARCHAR(50) UNIQUE
)");

// ── Tambah kolom pelanggan ke tabel transaksi secara aman ──
$checkCol1 = $conn->query("SHOW COLUMNS FROM transaksi LIKE 'pelanggan_nama'");
if ($checkCol1 && $checkCol1->num_rows === 0) {
    $conn->query("ALTER TABLE transaksi ADD COLUMN pelanggan_nama VARCHAR(150) DEFAULT 'No Cust'");
}

$checkCol2 = $conn->query("SHOW COLUMNS FROM transaksi LIKE 'pelanggan_telepon'");
if ($checkCol2 && $checkCol2->num_rows === 0) {
    $conn->query("ALTER TABLE transaksi ADD COLUMN pelanggan_telepon VARCHAR(50) DEFAULT ''");
}

// ── Migrasi data lama dari 'Umum / Non Member' menjadi 'No Cust' ──
$conn->query("UPDATE transaksi SET pelanggan_nama = 'No Cust' WHERE pelanggan_nama = 'Umum / Non Member'");

// ── Tabel Kupon/Promo ──
$conn->query("CREATE TABLE IF NOT EXISTS promo_kupon (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(50) UNIQUE NOT NULL,
    nama VARCHAR(150) NOT NULL,
    diskon_tipe ENUM('persen','nominal') NOT NULL DEFAULT 'nominal',
    diskon_nilai INT NOT NULL DEFAULT 0,
    min_belanja INT DEFAULT 0,
    kuota_harian INT NULL,
    kuota_total INT NULL,
    jam_mulai TIME NULL,
    jam_selesai TIME NULL,
    tanggal_mulai DATE NULL,
    tanggal_selesai DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS promo_kupon_cabang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kupon_id INT NOT NULL,
    cabang_nama VARCHAR(100) NOT NULL
)");
$conn->query("CREATE TABLE IF NOT EXISTS promo_kupon_produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kupon_id INT NOT NULL,
    produk_sku VARCHAR(20) NOT NULL
)");
$conn->query("CREATE TABLE IF NOT EXISTS promo_kupon_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kupon_id INT NOT NULL,
    sumber ENUM('kasir','order_online') NOT NULL,
    cabang_nama VARCHAR(100) NOT NULL,
    referensi VARCHAR(50) DEFAULT '',
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$checkColDiskon = $conn->query("SHOW COLUMNS FROM transaksi LIKE 'diskon'");
if ($checkColDiskon && $checkColDiskon->num_rows === 0) {
    $conn->query("ALTER TABLE transaksi ADD COLUMN diskon BIGINT DEFAULT 0");
}
$checkColKupon = $conn->query("SHOW COLUMNS FROM transaksi LIKE 'kupon_kode'");
if ($checkColKupon && $checkColKupon->num_rows === 0) {
    $conn->query("ALTER TABLE transaksi ADD COLUMN kupon_kode VARCHAR(50) DEFAULT ''");
}

// ── Tabel Edukasi & SOP ──
$conn->query("CREATE TABLE IF NOT EXISTS hoki_edukasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    kategori VARCHAR(100) NOT NULL DEFAULT 'SOP Umum',
    url_pdf TEXT NOT NULL,
    role_access VARCHAR(255) DEFAULT 'Semua',
    uploaded_by VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");


// ── Tabel History Rekap Keuangan ──
$conn->query("CREATE TABLE IF NOT EXISTS hoki_history_rekap (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal_rekap TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    filter_cabang VARCHAR(100),
    filter_tanggal VARCHAR(100),
    pemasukan_json LONGTEXT,
    pengeluaran_json LONGTEXT,
    total_pemasukan INT,
    total_pengeluaran INT,
    total_setoran INT,
    petugas VARCHAR(100)
)");
try {
    $conn->query("ALTER TABLE hoki_history_rekap ADD COLUMN total_setoran INT AFTER total_pengeluaran");
} catch (Exception $e) {
    // Abaikan jika kolom sudah ada
}

// ── Validasi kupon (dipakai oleh action cek_kupon & save_transaksi) ──
// $items: array of ['sku'=>string, 'qty'=>int]
function validasi_kupon_internal(mysqli $conn, string $kode, string $cabang, array $items, int $subtotal): array {
    $kode = trim($kode);
    if ($kode === '') {
        return ['valid' => false, 'message' => 'Kode kupon kosong'];
    }
    $kodeEsc = $conn->real_escape_string($kode);
    $res = $conn->query("SELECT * FROM promo_kupon WHERE UPPER(kode)=UPPER('$kodeEsc')");
    $kupon = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
    if (!$kupon) {
        return ['valid' => false, 'message' => 'Kode kupon tidak ditemukan'];
    }
    if (!(int)$kupon['is_active']) {
        return ['valid' => false, 'message' => 'Kupon ini sudah tidak aktif'];
    }

    $today = date('Y-m-d');
    if (!empty($kupon['tanggal_mulai']) && $today < $kupon['tanggal_mulai']) {
        return ['valid' => false, 'message' => 'Kupon belum berlaku (mulai ' . date('d/m/Y', strtotime($kupon['tanggal_mulai'])) . ')'];
    }
    if (!empty($kupon['tanggal_selesai']) && $today > $kupon['tanggal_selesai']) {
        return ['valid' => false, 'message' => 'Kupon sudah kadaluarsa'];
    }

    if (!empty($kupon['jam_mulai']) && !empty($kupon['jam_selesai'])) {
        $now = date('H:i:s');
        if ($now < $kupon['jam_mulai'] || $now > $kupon['jam_selesai']) {
            return ['valid' => false, 'message' => 'Kupon hanya berlaku jam ' . substr($kupon['jam_mulai'], 0, 5) . '-' . substr($kupon['jam_selesai'], 0, 5)];
        }
    }

    $kuponId = (int)$kupon['id'];

    $cabRes = $conn->query("SELECT cabang_nama FROM promo_kupon_cabang WHERE kupon_id=$kuponId");
    $cabangList = $cabRes ? array_map(fn($r) => strtolower($r['cabang_nama']), $cabRes->fetch_all(MYSQLI_ASSOC)) : [];
    if (!empty($cabangList) && !in_array(strtolower(trim($cabang)), $cabangList, true)) {
        return ['valid' => false, 'message' => 'Kupon tidak berlaku untuk cabang ini'];
    }

    $prodRes = $conn->query("SELECT produk_sku FROM promo_kupon_produk WHERE kupon_id=$kuponId");
    $skuList = $prodRes ? array_map(fn($r) => strtolower($r['produk_sku']), $prodRes->fetch_all(MYSQLI_ASSOC)) : [];
    if (!empty($skuList)) {
        $cartSkus = array_map(fn($it) => strtolower(trim($it['sku'] ?? '')), $items);
        if (!array_intersect($skuList, $cartSkus)) {
            return ['valid' => false, 'message' => 'Kupon tidak berlaku untuk menu di keranjangmu'];
        }
    }

    $minBelanja = (int)$kupon['min_belanja'];
    if ($subtotal < $minBelanja) {
        return ['valid' => false, 'message' => 'Kupon berlaku minimal belanja Rp' . number_format($minBelanja, 0, ',', '.')];
    }

    if ($kupon['kuota_harian'] !== null) {
        $r = $conn->query("SELECT COUNT(*) c FROM promo_kupon_usage WHERE kupon_id=$kuponId AND DATE(waktu)=CURDATE()");
        $used = $r ? (int)$r->fetch_assoc()['c'] : 0;
        if ($used >= (int)$kupon['kuota_harian']) {
            return ['valid' => false, 'message' => 'Kuota kupon hari ini sudah habis'];
        }
    }
    if ($kupon['kuota_total'] !== null) {
        $r = $conn->query("SELECT COUNT(*) c FROM promo_kupon_usage WHERE kupon_id=$kuponId");
        $used = $r ? (int)$r->fetch_assoc()['c'] : 0;
        if ($used >= (int)$kupon['kuota_total']) {
            return ['valid' => false, 'message' => 'Kuota kupon sudah habis'];
        }
    }

    $diskon = $kupon['diskon_tipe'] === 'persen'
        ? (int)round($subtotal * ((int)$kupon['diskon_nilai'] / 100))
        : (int)$kupon['diskon_nilai'];
    $diskon = min($diskon, $subtotal);

    return [
        'valid'    => true,
        'message'  => 'Kupon valid',
        'kupon_id' => $kuponId,
        'kode'     => $kupon['kode'],
        'diskon'   => $diskon,
    ];
}

// ── Bersihkan buffer sebelum mengirim JSON ──
ob_end_clean();

switch ($action) {

    // ── AUTH ──────────────────────────────────────────
    case 'login':
        $u = $conn->real_escape_string($input['user'] ?? '');
        $p = $conn->real_escape_string($input['pass'] ?? '');
        $res = $conn->query("SELECT * FROM users WHERE LOWER(username)=LOWER('$u') AND password='$p'");
        if ($res && $res->num_rows > 0) {
            $user  = $res->fetch_assoc();
            $token = md5(uniqid($user['username'], true) . time());
            $uid   = (int)$user['id'];
            $conn->query("UPDATE users SET session_token='$token' WHERE id=$uid");
            $fullName = $user['fullName'] ?? $user['fullname'] ?? $user['full_name'] ?? $user['username'];
            echo json_encode([
                "status" => "success",
                "user"   => [
                    "user"     => $user['username'],
                    "fullName" => $fullName,
                    "role"     => $user['role'],
                    "cabang"   => $user['cabang']
                ],
                "token" => $token
            ]);
        } else {
            echo json_encode(["status"=>"error","message"=>"Username atau Password salah!"]);
        }
        break;

    // ── SESSION CHECK ──────────────────────────────────
    case 'check_session':
        $u     = $conn->real_escape_string($_GET['user']  ?? '');
        $token = $conn->real_escape_string($_GET['token'] ?? '');
        if (empty($u) || empty($token)) {
            echo json_encode(["valid"=>false]);
            break;
        }
        $res = $conn->query("SELECT session_token FROM users WHERE LOWER(username)=LOWER('$u')");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            echo json_encode(["valid" => ($row['session_token'] === $token)]);
        } else {
            echo json_encode(["valid"=>false]);
        }
        break;

    // ── CABANG ────────────────────────────────────────
    case 'get_cabang':
    case 'get_branches':
        $res = $conn->query("SELECT id, nama_cabang FROM hoki_cabang ORDER BY nama_cabang ASC");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_cabang':
        $nama = $conn->real_escape_string($input['nama'] ?? '');
        if (!empty($nama)) {
            $conn->query("INSERT IGNORE INTO hoki_cabang (nama_cabang) VALUES ('$nama')");
            echo json_encode(["status"=>"success"]);
        } else {
            echo json_encode(["status"=>"error","message"=>"Nama cabang kosong"]);
        }
        break;

    case 'del_cabang':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM hoki_cabang WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    // ── ROLES ─────────────────────────────────────────
    case 'get_roles':
        $res = $conn->query("SELECT * FROM hoki_roles ORDER BY nama_role ASC");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_role':
        $nama = $conn->real_escape_string($input['nama'] ?? '');
        if (!empty($nama)) {
            $conn->query("INSERT IGNORE INTO hoki_roles (nama_role) VALUES ('$nama')");
            echo json_encode(["status"=>"success"]);
        } else {
            echo json_encode(["status"=>"error","message"=>"Nama role kosong"]);
        }
        break;

    case 'del_role':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM hoki_roles WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;


    case 'calculate_investor_profit':
        $tglMulai = $conn->real_escape_string($_GET['tgl_mulai'] ?? '');
        $tglSelesai = $conn->real_escape_string($_GET['tgl_selesai'] ?? '');
        $investor = $conn->real_escape_string($_GET['investor_username'] ?? '');
        
        if (empty($tglMulai) || empty($tglSelesai)) {
            echo json_encode(["status" => "error", "message" => "Rentang tanggal belum dipilih."]);
            break;
        }
        
        $resUser = $conn->query("SELECT cabang FROM users WHERE username = '$investor' AND role = 'Investor'");
        if (!$resUser || $resUser->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Investor tidak ditemukan atau role tidak sesuai."]);
            break;
        }
        $userData = $resUser->fetch_assoc();
        $cabangListStr = $userData['cabang'];
        $cabangArray = array_filter(array_map('trim', explode(',', $cabangListStr)));
        
        // Ambil data HPP Produk sekali
        $resProduk = $conn->query("SELECT sku, nama, harga, hpp FROM produk");
        $produkMap = [];
        if ($resProduk) {
            while ($p = $resProduk->fetch_assoc()) {
                $produkMap[] = $p;
            }
        }

        $results = [];
        $totalOmset = 0;
        $totalLabaKotor = 0;
        
        if (count($cabangArray) > 0) {
            $cabangQuoted = array_map(function($c) use ($conn) { return "'" . $conn->real_escape_string($c) . "'"; }, $cabangArray);
            $cabangIn = implode(',', $cabangQuoted);
            
            // Hitung Omset dan Kumpulkan JSON transaksi untuk Laba Kotor (HPP)
            $resLap = $conn->query("SELECT total, items_json FROM transaksi WHERE cabang IN ($cabangIn) AND waktu >= '{$tglMulai} 00:00:00' AND waktu <= '{$tglSelesai} 23:59:59'");
            
            if ($resLap) {
                while ($lap = $resLap->fetch_assoc()) {
                    $totalOmset += (int)$lap['total'];
                    
                    $itemsArr = json_decode($lap['items_json'], true) ?: [];
                    foreach ($itemsArr as $item) {
                        $skuTrx = trim($item['sku'] ?? '');
                        $namaTrx = strtolower(trim($item['nama'] ?? ''));
                        $qty = (int)($item['qty'] ?? 1);
                        
                        $found = null;
                        foreach ($produkMap as $pm) {
                            if (!empty($pm['sku']) && !empty($skuTrx) && $pm['sku'] === $skuTrx) {
                                $found = $pm;
                                break;
                            }
                            if (strtolower(trim($pm['nama'])) === $namaTrx) {
                                $found = $pm;
                                break;
                            }
                        }
                        
                        if ($found) {
                            $hargaJual = (int)$found['harga'];
                            $hpp = (int)$found['hpp'];
                            $margin = $hargaJual - $hpp;
                            $totalLabaKotor += ($margin * $qty);
                        }
                    }
                }
            }
        }
        
        $danaOperasional = $totalLabaKotor * 0.10;
        $sisaLaba = $totalLabaKotor - $danaOperasional;
        $hakInvestor = $sisaLaba * 0.50;
        
        // Return satu baris akumulasi
        $results[] = [
            'cabang' => implode(', ', $cabangArray),
            'omset' => $totalOmset,
            'laba_kotor' => $totalLabaKotor,
            'dana_operasional' => $danaOperasional,
            'hak_investor' => $hakInvestor
        ];
        
        echo json_encode($results);
        break;

    // ── USERS ─────────────────────────────────────────
    case 'get_users':
        $filter = $_GET['filter'] ?? '';
        if ($filter === 'salary') {
            $res = $conn->query("SELECT username, fullName, role, cabang FROM users WHERE role != 'Owner' AND role != 'VIP' AND role != 'Investor' ORDER BY fullName ASC");
        } else {
            $res = $conn->query("SELECT * FROM users ORDER BY id DESC");
        }
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_user':
        $u    = $conn->real_escape_string($input['username'] ?? '');
        $p    = $conn->real_escape_string($input['pass'] ?? '');
        $r    = $conn->real_escape_string($input['role'] ?? '');
        $fn   = $conn->real_escape_string($input['fullName'] ?? '');
        $cb   = $conn->real_escape_string($input['cabang'] ?? '');
        $docs = $conn->real_escape_string(json_encode($input['docs'] ?? []));
        $sql  = "INSERT INTO users (username, password, role, fullName, cabang, docs_json) VALUES ('$u','$p','$r','$fn','$cb','$docs') ON DUPLICATE KEY UPDATE password='$p', role='$r', fullName='$fn', cabang='$cb', docs_json='$docs'";
        echo $conn->query($sql)
            ? json_encode(["status"=>"success"])
            : json_encode(["status"=>"error","message"=>$conn->error]);
        break;

    case 'del_user':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM users WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    case 'update_user_cabang':
        $id     = (int)($input['id'] ?? 0);
        $cabang = $conn->real_escape_string($input['cabang'] ?? '');
        echo $conn->query("UPDATE users SET cabang='$cabang' WHERE id=$id")
            ? json_encode(["status"=>"success"])
            : json_encode(["status"=>"error","message"=>$conn->error]);
        break;

    // ── PRODUK ────────────────────────────────────────
    case 'get_produk':
        $res = $conn->query("SELECT * FROM produk ORDER BY urutan ASC, nama ASC");
        $produk_list = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        
        // Fetch all HPP formulas to build a real-time calculated HPP map by SKU
        $hpp_res = $conn->query("SELECT sku, id FROM hpp_produk");
        $hpp_list = $hpp_res ? $hpp_res->fetch_all(MYSQLI_ASSOC) : [];
        $hpp_map = [];
        foreach ($hpp_list as $hp) {
            $hid = (int)$hp['id'];
            $dr  = $conn->query("SELECT d.qty, b.harga_satuan
                                 FROM hpp_produk_detail d
                                 LEFT JOIN bahan_baku b ON b.id = d.bahan_id
                                 WHERE d.hpp_id = $hid");
            $real_hpp = 0;
            if ($dr) {
                while ($d = $dr->fetch_assoc()) {
                    $real_hpp += (float)($d['harga_satuan'] ?? 0) * (float)($d['qty'] ?? 0);
                }
            }
            $hpp_map[$hp['sku']] = round($real_hpp); // Bulatkan agar tidak ada desimal
        }
        
        // Override product HPP dynamically if SKU matches
        foreach ($produk_list as &$p) {
            if (!empty($p['sku']) && isset($hpp_map[$p['sku']])) {
                $p['hpp'] = $hpp_map[$p['sku']];
            } else {
                $p['hpp'] = round((float)($p['hpp'] ?? 0));
            }
        }
        unset($p);
        
        echo json_encode($produk_list);
        break;

    case 'save_produk':
        $id     = (int)($input['id'] ?? 0);
        $sku    = $conn->real_escape_string($input['sku']    ?? '');
        $nama   = $conn->real_escape_string($input['nama']   ?? '');
        $harga  = (int)($input['harga']     ?? 0);
        $hpp    = (int)($input['hpp']       ?? 0);
        $dimsum = (int)($input['dimsumPcs'] ?? 0);
        $alu    = (int)($input['aluTrayPcs'] ?? 0);
        if ($id > 0) {
            $sql = "UPDATE produk SET sku='$sku', nama='$nama', harga=$harga, hpp=$hpp, dimsumPcs=$dimsum, aluTrayPcs=$alu WHERE id=$id";
        } else {
            $sql = "INSERT INTO produk (sku, nama, harga, hpp, dimsumPcs, aluTrayPcs) VALUES ('$sku','$nama',$harga,$hpp,$dimsum,$alu)";
        }
        $conn->query($sql);
        echo json_encode(["status"=>"success"]);
        break;

    case 'del_produk':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM produk WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    case 'update_urutan':
        $ids = $input['ids'] ?? [];
        foreach ($ids as $urutan => $id) {
            $id = (int)$id;
            $ur = (int)$urutan + 1;
            $conn->query("UPDATE produk SET urutan=$ur WHERE id=$id");
        }
        echo json_encode(["status"=>"success"]);
        break;

    // ── TRANSAKSI ─────────────────────────────────────
    case 'save_transaksi':
        $cb = $conn->real_escape_string($input['cabang'] ?? '');
        $pt = $conn->real_escape_string($input['petugas'] ?? '');
        $tt = (int)($input['total'] ?? 0);
        $mt = $conn->real_escape_string($input['metode'] ?? '');
        $itemsArr = $input['items'] ?? [];
        $it = $conn->real_escape_string(json_encode($itemsArr));
        $pn = $conn->real_escape_string($input['pelanggan_nama'] ?? 'No Cust');
        $ph = $conn->real_escape_string($input['pelanggan_telepon'] ?? '');

        $diskon = 0;
        $kuponKodeSaved = '';
        $kuponIdUsed = null;
        $kuponKodeInput = trim($input['kupon_kode'] ?? '');
        if ($kuponKodeInput !== '') {
            $subtotalForCoupon = $tt; // total dikirim dari client sebelum diskon
            $cek = validasi_kupon_internal($conn, $kuponKodeInput, $cb, $itemsArr, $subtotalForCoupon);
            if ($cek['valid']) {
                $diskon = $cek['diskon'];
                $kuponKodeSaved = $conn->real_escape_string($cek['kode']);
                $kuponIdUsed = $cek['kupon_id'];
                $tt = max(0, $tt - $diskon);
            }
            // Kalau kupon tidak valid lagi saat submit (mis. kuota keburu habis), diam-diam abaikan
            // diskonnya (tetap simpan transaksi tanpa diskon) daripada gagal total di kasir.
        }

        $sql = "INSERT INTO transaksi (waktu, cabang, petugas, total, metode, items_json, pelanggan_nama, pelanggan_telepon, diskon, kupon_kode) VALUES (NOW(),'$cb','$pt',$tt,'$mt','$it','$pn','$ph',$diskon,'$kuponKodeSaved')";
        $ok = $conn->query($sql);
        if ($ok && $kuponIdUsed) {
            $trxId = $conn->insert_id;
            $conn->query("INSERT INTO promo_kupon_usage (kupon_id, sumber, cabang_nama, referensi) VALUES ($kuponIdUsed, 'kasir', '$cb', '$trxId')");
        }
        echo $ok
            ? json_encode(["status"=>"success","id"=>$conn->insert_id,"diskon"=>$diskon,"total"=>$tt])
            : json_encode(["status"=>"error","message"=>$conn->error]);
        break;

    // ── KUPON/PROMO ─────────────────────────────────────
    case 'cek_kupon':
        $kode = $_GET['kode'] ?? '';
        $cabangCek = $_GET['cabang'] ?? '';
        $subtotalCek = (int)($_GET['subtotal'] ?? 0);
        $itemsCek = json_decode($_GET['items'] ?? '[]', true);
        if (!is_array($itemsCek)) $itemsCek = [];
        $hasilCek = validasi_kupon_internal($conn, $kode, $cabangCek, $itemsCek, $subtotalCek);

        // Dipanggil server-to-server dari order.pos-hokidimsum.com setelah order berhasil dibuat,
        // supaya kuota kupon ikut tercatat untuk order online (bukan cuma kasir).
        if ($hasilCek['valid'] && !empty($_GET['pakai'])) {
            $sumberPakai = $conn->real_escape_string($_GET['sumber'] ?? 'order_online');
            $refPakai = $conn->real_escape_string($_GET['referensi'] ?? '');
            $cabangPakai = $conn->real_escape_string($cabangCek);
            $conn->query("INSERT INTO promo_kupon_usage (kupon_id, sumber, cabang_nama, referensi) VALUES ({$hasilCek['kupon_id']}, '$sumberPakai', '$cabangPakai', '$refPakai')");
        }

        echo json_encode($hasilCek);
        break;

    case 'get_kupon':
        $kupons = $conn->query("SELECT * FROM promo_kupon ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
        foreach ($kupons as &$k) {
            $kid = (int)$k['id'];
            $cabRes = $conn->query("SELECT cabang_nama FROM promo_kupon_cabang WHERE kupon_id=$kid");
            $k['cabang'] = $cabRes ? array_column($cabRes->fetch_all(MYSQLI_ASSOC), 'cabang_nama') : [];
            $prodRes = $conn->query("SELECT produk_sku FROM promo_kupon_produk WHERE kupon_id=$kid");
            $k['produk'] = $prodRes ? array_column($prodRes->fetch_all(MYSQLI_ASSOC), 'produk_sku') : [];
            $usedTotalRes = $conn->query("SELECT COUNT(*) c FROM promo_kupon_usage WHERE kupon_id=$kid");
            $k['terpakai_total'] = $usedTotalRes ? (int)$usedTotalRes->fetch_assoc()['c'] : 0;
            $usedTodayRes = $conn->query("SELECT COUNT(*) c FROM promo_kupon_usage WHERE kupon_id=$kid AND DATE(waktu)=CURDATE()");
            $k['terpakai_hari_ini'] = $usedTodayRes ? (int)$usedTodayRes->fetch_assoc()['c'] : 0;
        }
        unset($k);
        echo json_encode(["status"=>"success","data"=>$kupons]);
        break;

    case 'save_kupon':
        $tknK = $conn->real_escape_string($input['token'] ?? '');
        $unameK = $conn->real_escape_string($input['user'] ?? '');
        $chkK = $conn->query("SELECT role FROM users WHERE LOWER(username)=LOWER('$unameK') AND session_token='$tknK'");
        $actorK = ($chkK && $chkK->num_rows > 0) ? $chkK->fetch_assoc() : null;
        if (!$actorK || !in_array($actorK['role'], ['Owner','VIP'], true)) {
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Akses ditolak"]);
            break;
        }

        $kid = (int)($input['id'] ?? 0);
        $kode = $conn->real_escape_string(trim($input['kode'] ?? ''));
        $nama = $conn->real_escape_string(trim($input['nama'] ?? ''));
        $tipe = ($input['diskon_tipe'] ?? 'nominal') === 'persen' ? 'persen' : 'nominal';
        $nilai = (int)($input['diskon_nilai'] ?? 0);
        $minBelanja = (int)($input['min_belanja'] ?? 0);
        $kuotaHarian = $input['kuota_harian'] !== '' && $input['kuota_harian'] !== null ? (int)$input['kuota_harian'] : null;
        $kuotaTotal = $input['kuota_total'] !== '' && $input['kuota_total'] !== null ? (int)$input['kuota_total'] : null;
        $jamMulai = !empty($input['jam_mulai']) ? $conn->real_escape_string($input['jam_mulai']) : null;
        $jamSelesai = !empty($input['jam_selesai']) ? $conn->real_escape_string($input['jam_selesai']) : null;
        $tglMulai = !empty($input['tanggal_mulai']) ? $conn->real_escape_string($input['tanggal_mulai']) : null;
        $tglSelesai = !empty($input['tanggal_selesai']) ? $conn->real_escape_string($input['tanggal_selesai']) : null;
        $isActive = !empty($input['is_active']) ? 1 : 0;
        $cabangArr = is_array($input['cabang'] ?? null) ? $input['cabang'] : [];
        $produkArr = is_array($input['produk'] ?? null) ? $input['produk'] : [];

        if ($kode === '' || $nama === '' || $nilai <= 0) {
            echo json_encode(["status"=>"error","message"=>"Kode, nama, dan nilai diskon wajib diisi"]);
            break;
        }

        $jamMulaiSql = $jamMulai ? "'$jamMulai'" : "NULL";
        $jamSelesaiSql = $jamSelesai ? "'$jamSelesai'" : "NULL";
        $tglMulaiSql = $tglMulai ? "'$tglMulai'" : "NULL";
        $tglSelesaiSql = $tglSelesai ? "'$tglSelesai'" : "NULL";
        $kuotaHarianSql = $kuotaHarian !== null ? $kuotaHarian : "NULL";
        $kuotaTotalSql = $kuotaTotal !== null ? $kuotaTotal : "NULL";

        // Cek duplikat kode (selain diri sendiri saat edit)
        $dupCheck = $conn->query("SELECT id FROM promo_kupon WHERE UPPER(kode)=UPPER('$kode') AND id != $kid");
        if ($dupCheck && $dupCheck->num_rows > 0) {
            echo json_encode(["status"=>"error","message"=>"Kode kupon sudah dipakai kupon lain"]);
            break;
        }

        if ($kid > 0) {
            $conn->query("UPDATE promo_kupon SET kode='$kode', nama='$nama', diskon_tipe='$tipe', diskon_nilai=$nilai,
                min_belanja=$minBelanja, kuota_harian=$kuotaHarianSql, kuota_total=$kuotaTotalSql,
                jam_mulai=$jamMulaiSql, jam_selesai=$jamSelesaiSql, tanggal_mulai=$tglMulaiSql, tanggal_selesai=$tglSelesaiSql,
                is_active=$isActive WHERE id=$kid");
        } else {
            $conn->query("INSERT INTO promo_kupon (kode, nama, diskon_tipe, diskon_nilai, min_belanja, kuota_harian, kuota_total, jam_mulai, jam_selesai, tanggal_mulai, tanggal_selesai, is_active)
                VALUES ('$kode','$nama','$tipe',$nilai,$minBelanja,$kuotaHarianSql,$kuotaTotalSql,$jamMulaiSql,$jamSelesaiSql,$tglMulaiSql,$tglSelesaiSql,$isActive)");
            $kid = $conn->insert_id;
        }

        $conn->query("DELETE FROM promo_kupon_cabang WHERE kupon_id=$kid");
        foreach ($cabangArr as $cb) {
            $cbEsc = $conn->real_escape_string(trim($cb));
            if ($cbEsc !== '') $conn->query("INSERT INTO promo_kupon_cabang (kupon_id, cabang_nama) VALUES ($kid, '$cbEsc')");
        }
        $conn->query("DELETE FROM promo_kupon_produk WHERE kupon_id=$kid");
        foreach ($produkArr as $sku) {
            $skuEsc = $conn->real_escape_string(trim($sku));
            if ($skuEsc !== '') $conn->query("INSERT INTO promo_kupon_produk (kupon_id, produk_sku) VALUES ($kid, '$skuEsc')");
        }

        echo json_encode(["status"=>"success","id"=>$kid]);
        break;

    case 'del_kupon':
        $tknK = $conn->real_escape_string($_GET['token'] ?? '');
        $unameK = $conn->real_escape_string($_GET['user'] ?? '');
        $chkK = $conn->query("SELECT role FROM users WHERE LOWER(username)=LOWER('$unameK') AND session_token='$tknK'");
        $actorK = ($chkK && $chkK->num_rows > 0) ? $chkK->fetch_assoc() : null;
        if (!$actorK || !in_array($actorK['role'], ['Owner','VIP'], true)) {
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Akses ditolak"]);
            break;
        }
        $kid = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM promo_kupon WHERE id=$kid");
        $conn->query("DELETE FROM promo_kupon_cabang WHERE kupon_id=$kid");
        $conn->query("DELETE FROM promo_kupon_produk WHERE kupon_id=$kid");
        echo json_encode(["status"=>"success"]);
        break;

    case 'get_history':
        // Cek filter opsional untuk optimasi performa load data
        $tglMulai = isset($_GET['tgl_mulai']) ? $conn->real_escape_string($_GET['tgl_mulai']) : '';
        $tglSelesai = isset($_GET['tgl_selesai']) ? $conn->real_escape_string($_GET['tgl_selesai']) : '';
        $cab = isset($_GET['cabang']) ? $conn->real_escape_string($_GET['cabang']) : '';
        $role = isset($_GET['role']) ? $_GET['role'] : '';

        // Pastikan index di database dibuat demi kecepatan pencarian
        $conn->query("ALTER TABLE transaksi ADD INDEX IF NOT EXISTS idx_waktu_cabang (waktu, cabang)");

        $where = [];
        if ($tglMulai) {
            $where[] = "DATE(waktu) >= '$tglMulai'";
        }
        if ($tglSelesai) {
            $where[] = "DATE(waktu) <= '$tglSelesai'";
        }
        if ($cab && strtolower($cab) !== 'semua') {
            $where[] = "LOWER(cabang) = LOWER('$cab')";
        }

        $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
        
        // Batasi baris default (misal 500 teratas jika tidak difilter tanggal) agar load cepat,
        // namun tetap mengembalikan semua jika user secara spesifik memfilter rentang tanggal.
        $limitClause = ($tglMulai || $tglSelesai) ? "" : "LIMIT 500";

        $res = $conn->query("SELECT * FROM transaksi $whereClause ORDER BY id DESC $limitClause");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'del_transaksi':
        // ── GUARD: Owner/VIP kapan saja, role lain hanya dalam 5 menit setelah transaksi dibuat ──
        $tkn  = $conn->real_escape_string($_GET['token'] ?? '');
        $uname= $conn->real_escape_string($_GET['user']  ?? '');
        $chk  = $conn->query("SELECT role FROM users WHERE LOWER(username)=LOWER('$uname') AND session_token='$tkn'");
        $actor= ($chk && $chk->num_rows > 0) ? $chk->fetch_assoc() : null;
        if (!$actor) {
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Akses ditolak! Sesi tidak valid."]);
            break;
        }

        $id = (int)($_GET['id'] ?? 0);
        $trx = $conn->query("SELECT waktu FROM transaksi WHERE id=$id");
        $trxRow = ($trx && $trx->num_rows > 0) ? $trx->fetch_assoc() : null;
        if (!$trxRow) {
            echo json_encode(["status"=>"error","message"=>"Transaksi tidak ditemukan."]);
            break;
        }

        if (!in_array($actor['role'], ['Owner', 'VIP'])) {
            $menit = (time() - strtotime($trxRow['waktu'])) / 60;
            if ($menit > 5) {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Batas waktu hapus (5 menit) sudah lewat. Hubungi Owner/VIP."]);
                break;
            }
        }

        $conn->query("DELETE FROM transaksi WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    case 'clear_history':
        $conn->query("TRUNCATE TABLE transaksi");
        echo json_encode(["status"=>"success"]);
        break;

    // ── OMSET HARIAN (dashboard) ──────────────────────
    case 'get_omset_harian':
        $cabang = $conn->real_escape_string($_GET['cabang'] ?? '');
        $today  = date('Y-m-d');
        if (empty($cabang) || $cabang === 'Semua') {
            $sql = "SELECT SUM(total) as total, COUNT(*) as jumlah FROM transaksi WHERE DATE(waktu)='$today'";
        } else {
            $sql = "SELECT SUM(total) as total, COUNT(*) as jumlah FROM transaksi WHERE DATE(waktu)='$today' AND cabang='$cabang'";
        }
        $res  = $conn->query($sql);
        $data = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : [];
        echo json_encode(["total"=>(int)($data['total']??0),"jumlah"=>(int)($data['jumlah']??0)]);
        break;

    // ── LOGIN LOG ─────────────────────────────────────
    case 'add_log':
        $u = $conn->real_escape_string($input['user'] ?? '');
        $r = $conn->real_escape_string($input['role'] ?? '');
        $c = $conn->real_escape_string($input['cabang'] ?? '');
        $conn->query("INSERT INTO logs_login (waktu, username, role, cabang) VALUES (NOW(),'$u','$r','$c')");
        echo json_encode(["status"=>"success"]);
        break;

    case 'get_logs':
        $res = $conn->query("SELECT id, DATE_FORMAT(waktu,'%Y-%m-%d %H:%i:%s') as waktu, username, role, cabang FROM logs_login ORDER BY waktu DESC LIMIT 200");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'clear_logs':
        echo $conn->query("TRUNCATE TABLE logs_login")
            ? json_encode(["status"=>"success"])
            : json_encode(["status"=>"error","message"=>$conn->error]);
        break;

    // ── INVENTORY (LOGISTIK/GUDANG) ───────────────────
    case 'get_inventory':
        $res = $conn->query("SELECT * FROM inventory ORDER BY nama_barang ASC");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;
        
    case 'add_inventory':
        $nama_barang = $conn->real_escape_string($input['nama_barang'] ?? '');
        $satuan = $conn->real_escape_string($input['satuan'] ?? '');
        
        if (empty($nama_barang) || empty($satuan)) {
            echo json_encode(["status"=>"error", "message"=>"Nama barang dan satuan harus diisi."]);
            break;
        }

        // Check if exists
        $check = $conn->query("SELECT id FROM inventory WHERE nama_barang = '$nama_barang'");
        if ($check && $check->num_rows > 0) {
            echo json_encode(["status"=>"error", "message"=>"Barang sudah ada di inventory."]);
            break;
        }

        $res = $conn->query("INSERT INTO inventory (nama_barang, satuan) VALUES ('$nama_barang', '$satuan')");
        if ($res) {
            echo json_encode(["status"=>"success"]);
        } else {
            echo json_encode(["status"=>"error", "message"=>$conn->error]);
        }
        break;

    case 'del_inventory':
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM inventory WHERE id=$id");
        }
        echo json_encode(["status"=>"success"]);
        break;

    // ── MASTER STOK ───────────────────────────────────
    case 'get_master_stok':
        $res = $conn->query("SELECT * FROM stok_master ORDER BY nama_item ASC");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_master_stok':
        $nama = $conn->real_escape_string($input['nama'] ?? '');
        $conn->query("INSERT IGNORE INTO stok_master (nama_item) VALUES ('$nama')");
        echo json_encode(["status"=>"success"]);
        break;

    case 'del_master_stok':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM stok_master WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    // ── LAPORAN STOK ──────────────────────────────────
    case 'get_laporan_stok':
        $role  = $_GET['role'] ?? 'Staff';
        $akses = $_GET['cabang'] ?? '';
        if ($role === 'Owner' || $role === 'VIP' || $akses === 'Semua') {
            $sql = "SELECT * FROM stok_history ORDER BY waktu DESC LIMIT 100";
        } elseif (empty($akses)) {
            echo json_encode([]); break;
        } else {
            $cabangArr  = explode(',', $akses);
            $cleanCabang = array_map(fn($i) => "'".$conn->real_escape_string(trim($i))."'", $cabangArr);
            $cabangList  = implode(',', $cleanCabang);
            $sql = "SELECT * FROM stok_history WHERE cabang IN ($cabangList) ORDER BY waktu DESC LIMIT 50";
        }
        $res = $conn->query($sql);
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_laporan_stok':
        $rid = $conn->real_escape_string($input['report_id'] ?? '');
        $pt  = $conn->real_escape_string($input['petugas'] ?? '');
        $cb  = $conn->real_escape_string($input['cabang'] ?? '');
        $it  = $conn->real_escape_string(json_encode($input['items'] ?? []));
        $sql = "INSERT INTO stok_history (report_id, petugas, cabang, items_json) VALUES ('$rid','$pt','$cb','$it')";
        echo $conn->query($sql)
            ? json_encode(["status"=>"success"])
            : json_encode(["status"=>"error","message"=>$conn->error]);
        break;

    case 'del_laporan_stok':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM stok_history WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    // ── LAPORAN RESTOCK ───────────────────────────────
    case 'get_laporan_restock':
        $role  = $_GET['role'] ?? 'Staff';
        $akses = $_GET['cabang'] ?? '';
        if ($role === 'Owner' || $role === 'VIP' || $akses === 'Semua') {
            $sql = "SELECT * FROM restock_history ORDER BY id DESC LIMIT 100";
        } else {
            $cabangArr  = explode(',', $akses);
            $cleanCabang = array_map(fn($i) => "'".$conn->real_escape_string(trim($i))."'", $cabangArr);
            $cabangList  = implode(',', $cleanCabang);
            $sql = "SELECT * FROM restock_history WHERE cabang IN ($cabangList) ORDER BY id DESC LIMIT 50";
        }
        $res = $conn->query($sql);
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_laporan_restock':
        $rid = $conn->real_escape_string($input['id'] ?? '');
        $wkt = $conn->real_escape_string($input['waktu'] ?? '');
        $pt  = $conn->real_escape_string($input['petugas'] ?? '');
        $cb  = $conn->real_escape_string($input['cabang'] ?? '');
        $it  = $conn->real_escape_string(json_encode($input['items'] ?? []));
        $sql = "INSERT INTO restock_history (report_id, waktu_teks, petugas, cabang, items_json) VALUES ('$rid','$wkt','$pt','$cb','$it')";
        $conn->query($sql);
        echo json_encode(["status"=>"success"]);
        break;

    case 'del_laporan_restock':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM restock_history WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    // ── BUKU KAS / BELANJA ────────────────────────────
    case 'get_kas_jenis':
        $res = $conn->query("SELECT nama_jenis FROM hoki_kas_jenis ORDER BY nama_jenis ASC");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_kas_jenis':
        $nama = $conn->real_escape_string($input['nama'] ?? '');
        $conn->query("INSERT IGNORE INTO hoki_kas_jenis (nama_jenis) VALUES ('$nama')");
        echo json_encode(["status"=>"success"]);
        break;

    case 'del_kas_jenis':
        $nama = $conn->real_escape_string($_GET['nama'] ?? '');
        $conn->query("DELETE FROM hoki_kas_jenis WHERE nama_jenis='$nama'");
        echo json_encode(["status"=>"success"]);
        break;

    case 'get_kas_data':
        $role  = $_GET['role'] ?? '';
        $akses = $_GET['cabang'] ?? '';
        $tglMulai   = isset($_GET['tgl_mulai']) ? $conn->real_escape_string($_GET['tgl_mulai']) : '';
        $tglSelesai = isset($_GET['tgl_selesai']) ? $conn->real_escape_string($_GET['tgl_selesai']) : '';
        
        $where = [];
        if (!($role === 'Owner' || $role === 'VIP' || $akses === 'Semua')) {
            $cabangArr  = explode(',', $akses);
            $cleanCabang = array_map(fn($i) => "'".$conn->real_escape_string(trim($i))."'", $cabangArr);
            $cabangList  = implode(',', $cleanCabang);
            $where[] = "cabang IN ($cabangList)";
        }

        if ($tglMulai) {
            $where[] = "waktu >= '{$tglMulai} 00:00:00'";
        }
        if ($tglSelesai) {
            $where[] = "waktu <= '{$tglSelesai} 23:59:59'";
        }

        $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
        $limitClause = ($tglMulai || $tglSelesai) ? "" : "LIMIT 2000";

        $sql = "SELECT * FROM hoki_kas_data $whereClause ORDER BY waktu DESC $limitClause";
        
        $res = $conn->query($sql);
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_kas_data':
        $wkt = $conn->real_escape_string($input['waktu'] ?? '');
        $usr = $conn->real_escape_string($input['user'] ?? '');
        $jns = $conn->real_escape_string($input['jenis'] ?? '');
        $nam = $conn->real_escape_string($input['nama'] ?? '');
        $qty = (int)($input['qty'] ?? 1);
        $mod = $conn->real_escape_string($input['mode'] ?? '');
        $nom = (int)($input['nominal'] ?? 0);
        $ket = $conn->real_escape_string($input['ket'] ?? '');
        $cab = $conn->real_escape_string($input['cabang'] ?? '');
        $conn->query("INSERT INTO hoki_kas_data (waktu, user, jenis, nama, qty, mode, nominal, ket, cabang) VALUES ('$wkt','$usr','$jns','$nam',$qty,'$mod',$nom,'$ket','$cab')");
        echo json_encode(["status"=>"success"]);
        break;

    case 'edit_kas_data':
        $id  = (int)($input['id'] ?? 0);
        $wkt = $conn->real_escape_string($input['waktu'] ?? '');
        $usr = $conn->real_escape_string($input['user'] ?? '');
        $jns = $conn->real_escape_string($input['jenis'] ?? '');
        $nam = $conn->real_escape_string($input['nama'] ?? '');
        $qty = (int)($input['qty'] ?? 1);
        $mod = $conn->real_escape_string($input['mode'] ?? '');
        $nom = (int)($input['nominal'] ?? 0);
        $ket = $conn->real_escape_string($input['ket'] ?? '');
        $cab = $conn->real_escape_string($input['cabang'] ?? '');
        
        $conn->query("UPDATE hoki_kas_data SET waktu='$wkt', user='$usr', jenis='$jns', nama='$nam', qty=$qty, mode='$mod', nominal=$nom, ket='$ket', cabang='$cab' WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    case 'del_kas_data':
        // ── GUARD: hanya role VIP ──
        $tkn  = $conn->real_escape_string($_GET['token'] ?? '');
        $uname= $conn->real_escape_string($_GET['user']  ?? '');
        $chk  = $conn->query("SELECT role FROM users WHERE LOWER(username)=LOWER('$uname') AND session_token='$tkn'");
        $actor= ($chk && $chk->num_rows > 0) ? $chk->fetch_assoc() : null;
        if (!$actor || $actor['role'] !== 'VIP') {
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Akses ditolak! Hanya VIP yang dapat menghapus catatan."]);
            break;
        }
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM hoki_kas_data WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    // ── LAPORAN SETTLEMENT / KEUANGAN ─────────────────
    case 'get_laporan_history':
        $role  = $_GET['role'] ?? 'Staff';
        $akses = $_GET['cabang'] ?? '';
        
        // Tangkap parameter tanggal yang dikirim dari tombol Cari
        $tglMulai   = isset($_GET['tgl_mulai']) ? $conn->real_escape_string($_GET['tgl_mulai']) : '';
        $tglSelesai = isset($_GET['tgl_selesai']) ? $conn->real_escape_string($_GET['tgl_selesai']) : '';

        $where = [];

        // Filter berdasarkan hak akses cabang
        if (!($role === 'Owner' || $role === 'VIP' || $akses === 'Semua')) {
            $cabangArr   = explode(',', $akses);
            $cleanCabang = array_map(fn($i) => "'".$conn->real_escape_string(trim($i))."'", $cabangArr);
            $cabangList  = implode(',', $cleanCabang);
            $where[] = "cabang IN ($cabangList)";
        }

        // Filter berdasarkan tanggal jika parameter dikirim dari tombol Cari
        if ($tglMulai) {
            $where[] = "waktu >= '{$tglMulai} 00:00:00'";
        }
        if ($tglSelesai) {
            $where[] = "waktu <= '{$tglSelesai} 23:59:59'";
        }

        // Gabungkan semua filter kondisi WHERE
        $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
        
        // Batasi default 200 data teratas jika tidak sedang memfilter tanggal agar query tetap instan
        $limitClause = ($tglMulai || $tglSelesai) ? "" : "LIMIT 200";

        $sql = "SELECT *, DATE_FORMAT(waktu,'%Y-%m-%d %H:%i:%s') as waktu 
                FROM laporan_settlement 
                $whereClause 
                ORDER BY waktu DESC 
                $limitClause";

        $res = $conn->query($sql);
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_laporan':
        $rid     = $conn->real_escape_string($input['report_id'] ?? '');
        $petugas = $conn->real_escape_string($input['petugas'] ?? '');
        $cb      = $conn->real_escape_string($input['cabang'] ?? '');
        $mt      = $conn->real_escape_string(json_encode($input['metode'] ?? []));
        $au_data = $input['audit'] ?? []; 
        $au      = $conn->real_escape_string(json_encode($au_data));
        $ex      = $conn->real_escape_string(json_encode($input['expens'] ?? []));
        $tt      = (int)($input['total'] ?? 0);
        
        // AMBIL WAKTU: Jika dari JS ada, pakai itu. Jika tidak ada, baru pakai jam sekarang.
        $wkt     = $conn->real_escape_string($input['waktu'] ?? date('Y-m-d H:i:s'));
        
        // Konversi ke format YYYY-MM-DD untuk tabel ledger
        $tglOnly = date('Y-m-d', strtotime($wkt)); 

        $sql = "INSERT INTO laporan_settlement (report_id, waktu, petugas, cabang, metode_json, audit_json, pengeluaran_json, grand_total) 
                VALUES ('$rid','$wkt','$petugas','$cb','$mt','$au','$ex',$tt) 
                ON DUPLICATE KEY UPDATE waktu='$wkt', petugas='$petugas', cabang='$cb', metode_json='$mt', audit_json='$au', pengeluaran_json='$ex', grand_total=$tt";
        
        if ($conn->query($sql)) {
            $conn->query("DELETE FROM warehouse_ledger WHERE catatan LIKE '%$rid%'");

            foreach ($au_data as $item) {
                $laku = (float)($item['laku'] ?? 0);
                if ($laku > 0) {
                    $namaItem = $conn->real_escape_string($item['nama']);
                    $ket = "Laporan $cb ($rid)";
                    
                    // PERBAIKAN: Gunakan '$tglOnly', bukan CURDATE()
                    $conn->query("INSERT INTO warehouse_ledger (tgl, sku, masuk, keluar, cabang, catatan) 
                                  VALUES ('$tglOnly', '$namaItem', 0, $laku, '$cb', '$ket')");
                }
            }
            echo json_encode(["status" => "success"]);
        }
        break;

    case 'del_laporan':
        $rid = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM laporan_settlement WHERE id=$rid");
        echo json_encode(["status"=>"success"]);
        break;

    // ── BAHAN BAKU ────────────────────────────────────
    case 'get_bahan_baku':
        $res = $conn->query("SELECT * FROM bahan_baku ORDER BY nama ASC");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_bahan_baku':
        $id   = (int)($input['id'] ?? 0);
        $nama = $conn->real_escape_string($input['nama'] ?? '');
        $hrg  = (float)($input['harga'] ?? 0);
        $byk  = (float)($input['banyak'] ?? 0);
        $sat  = $conn->real_escape_string($input['satuan'] ?? '');
        $hs   = $byk > 0 ? $hrg / $byk : 0;

        if ($id > 0) {
            $sql = "UPDATE bahan_baku SET nama='$nama', harga=$hrg, banyak=$byk, satuan='$sat', harga_satuan=$hs WHERE id=$id";
        } else {
            $sql = "INSERT INTO bahan_baku (nama, harga, banyak, satuan, harga_satuan) VALUES ('$nama',$hrg,$byk,'$sat',$hs)";
        }

        if ($conn->query($sql)) {
            // ── LOGIKA OTOMATISASI HPP ──
            // 1. Update subtotal di semua rincian HPP yang menggunakan bahan ini
            $conn->query("UPDATE hpp_produk_detail d 
                          JOIN bahan_baku b ON d.bahan_id = b.id 
                          SET d.subtotal = d.qty * b.harga_satuan 
                          WHERE d.bahan_id = " . ($id > 0 ? $id : $conn->insert_id));

            // 2. Hitung ulang total harga_pokok di tabel hpp_produk
            $conn->query("UPDATE hpp_produk h 
                          SET harga_pokok = (
                              SELECT SUM(subtotal) 
                              FROM hpp_produk_detail 
                              WHERE hpp_id = h.id
                          )");

            // 3. Sinkronkan nilai HPP baru ke tabel produk utama berdasarkan SKU
            $conn->query("UPDATE produk p 
                          JOIN hpp_produk h ON p.sku = h.sku 
                          SET p.hpp = h.harga_pokok");

            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        break;

    case 'del_bahan_baku':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM bahan_baku WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    // ── HPP PRODUK ────────────────────────────────────
    case 'get_hpp_produk':
        $res  = $conn->query("SELECT * FROM hpp_produk ORDER BY nama_produk ASC");
        $list = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        foreach ($list as &$h) {
            $hid = (int)$h['id'];
            $dr  = $conn->query("SELECT d.bahan_id, d.qty, d.subtotal, b.nama, b.satuan, b.harga_satuan
                                 FROM hpp_produk_detail d
                                 LEFT JOIN bahan_baku b ON b.id = d.bahan_id
                                 WHERE d.hpp_id = $hid");
            $details = $dr ? $dr->fetch_all(MYSQLI_ASSOC) : [];
            
            // Recalculate real HPP dynamically based on current raw material prices
            $real_hpp = 0;
            foreach ($details as $d) {
                $real_hpp += (float)($d['harga_satuan'] ?? 0) * (float)($d['qty'] ?? 0);
            }
            $h['harga_pokok'] = round($real_hpp); // Bulatkan agar tidak ada desimal
            $h['detail_json'] = json_encode($details);
        }
        unset($h);
        echo json_encode($list);
        break;

    case 'save_hpp_produk':
        $id    = (int)($input['id'] ?? 0);
        $nama  = $conn->real_escape_string($input['nama_produk'] ?? '');
        $sku   = $conn->real_escape_string($input['sku'] ?? '');
        $hpp   = (float)($input['harga_pokok'] ?? 0);
        $bahan = $input['bahan'] ?? [];

        if ($id > 0) {
            $conn->query("UPDATE hpp_produk SET nama_produk='$nama', sku='$sku', harga_pokok=$hpp WHERE id=$id");
            $conn->query("DELETE FROM hpp_produk_detail WHERE hpp_id=$id");
        } else {
            $conn->query("INSERT INTO hpp_produk (nama_produk, sku, harga_pokok) VALUES ('$nama','$sku',$hpp)");
            $id = $conn->insert_id;
        }

        foreach ($bahan as $b) {
            $bid  = (int)($b['bahan_id'] ?? 0);
            $qty  = (float)($b['qty'] ?? 0);
            $sub  = (float)($b['subtotal'] ?? 0);
            $conn->query("INSERT INTO hpp_produk_detail (hpp_id, bahan_id, qty, subtotal) VALUES ($id,$bid,$qty,$sub)");
        }

        // Sync HPP ke tabel produk berdasarkan SKU
        $conn->query("UPDATE produk SET hpp=$hpp WHERE sku='$sku'");

        echo json_encode(["status"=>"success"]);
        break;

    case 'del_hpp_produk':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM hpp_produk_detail WHERE hpp_id=$id");
        $conn->query("DELETE FROM hpp_produk WHERE id=$id");
        echo json_encode(["status"=>"success"]);
        break;

    // ── WAREHOUSE STOK ────────────────────────────────
    case 'get_warehouse_stok':
        $tgl = $conn->real_escape_string($_GET['tgl'] ?? date('Y-m-d'));
        $res = $conn->query("SELECT bahan_id, stok_awal, stok_masuk FROM warehouse_stok WHERE tgl='$tgl'");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;

    case 'save_warehouse_stok':
        $tgl   = $conn->real_escape_string($input['tgl'] ?? date('Y-m-d'));
        $items = $input['items'] ?? [];
        $ok    = true;
        foreach ($items as $item) {
            $bid  = (int)($item['bahan_id'] ?? 0);
            $awal = (float)($item['stok_awal'] ?? 0);
            if ($bid <= 0) continue;
            $r = $conn->query("INSERT INTO warehouse_stok (tgl, bahan_id, stok_awal)
                VALUES ('$tgl', $bid, $awal)
                ON DUPLICATE KEY UPDATE stok_awal=$awal");
            if (!$r) $ok = false;
        }
        echo json_encode($ok ? ["status"=>"success"] : ["status"=>"error","message"=>$conn->error]);
        break;

    // ── WAREHOUSE LEDGER ──────────────────────────────
    case 'get_warehouse_ledger':
        $namaItem = $conn->real_escape_string($_GET['sku'] ?? '');
        if (!$namaItem) { echo json_encode([]); break; }

        // PERBAIKAN: Ambil dari kolom 'tgl' dan urutkan berdasarkan 'tgl'
        $res = $conn->query("SELECT *, DATE_FORMAT(tgl, '%d/%m/%Y') as waktu_tampil 
                             FROM warehouse_ledger 
                             WHERE sku='$namaItem' 
                             ORDER BY tgl ASC, created_at ASC");
        
        $history = [];
        $saldo = 0;
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $masuk  = (float)$row['masuk'];
                $keluar = (float)$row['keluar'];
                $saldo  = $saldo + $masuk - $keluar;
                
                $history[] = [
                    'id'      => $row['id'],
                    'tgl'     => $row['waktu_tampil'], // Menampilkan tanggal transaksi asli
                    'catatan' => $row['catatan'] ?: ($masuk > 0 ? 'Input Manual' : 'Penjualan'),
                    'masuk'   => $masuk,
                    'keluar'  => $keluar,
                    'sisa'    => $saldo
                ];
            }
        }
        echo json_encode(array_reverse($history)); 
        break;
        
    case 'delete_warehouse_ledger':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
    
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID tidak ditemukan di server']);
            break;
        }
    
        // Ambil SKU/Nama sebelum dihapus untuk hitung ulang nanti
        $stmt = $conn->query("SELECT sku FROM warehouse_ledger WHERE id = '$id'");
        $row = $stmt->fetch_assoc();
    
        if ($row) {
            $sku = $row['sku'];
            // Hapus datanya
            $conn->query("DELETE FROM warehouse_ledger WHERE id = '$id'");
            
            // Logika Re-calculate (Opsional, tapi bagus agar sisa stok di database sinkron)
            // Karena di fungsi 'get' Mas sudah menghitung saldo secara LIVE (looping), 
            // sebenarnya menghapus saja sudah cukup untuk memperbaiki tampilan di browser.
            
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Data sudah tidak ada']);
        }
        break;        

    case 'save_warehouse_masuk':
        $sku     = $conn->real_escape_string($input['sku']     ?? '');
        $masuk   = (float)($input['masuk']   ?? 0);
        $keluar  = (float)($input['keluar']  ?? 0);
        $catatan = $conn->real_escape_string($input['catatan'] ?? '');
        $tgl     = $conn->real_escape_string($input['tgl']     ?? date('Y-m-d'));
        if (!$sku || ($masuk <= 0 && $keluar <= 0)) { echo json_encode(['status'=>'error','message'=>'Data tidak valid']); break; }
        $ok = $conn->query("INSERT INTO warehouse_ledger (tgl, sku, masuk, keluar, catatan) VALUES ('$tgl','$sku',$masuk,$keluar,'$catatan')");
        echo json_encode($ok ? ['status'=>'success'] : ['status'=>'error','message'=>$conn->error]);
        break;


    case 'get_salary_config':
        header('Content-Type: application/json');
        $res = $conn->query("SELECT * FROM hoki_salary_config ORDER BY role_name ASC");
        $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(is_array($data) ? $data : []);
        break;

    case 'save_salary_config':
        header('Content-Type: application/json');
        $role_name = $conn->real_escape_string($input['role'] ?? '');
        $gaji_weekday = (int)($input['gaji_weekday'] ?? 0);
        $gaji_weekend = (int)($input['gaji_weekend'] ?? 0);
        $gaji_bulanan = (int)($input['gaji_bulanan'] ?? 0);
        $tunjangan_jabatan = (int)($input['tunjangan_jabatan'] ?? 0);
        $bonus_harian = (int)($input['bonus_harian'] ?? 0);
        $bonus_mingguan = (int)($input['bonus_mingguan'] ?? 0);
        $bonus_bulanan = (int)($input['bonus_bulanan'] ?? 0);
        $bonus_harian_jabatan = (int)($input['bonus_harian_jabatan'] ?? 0);
        $bonus_mingguan_jabatan = (int)($input['bonus_mingguan_jabatan'] ?? 0);
        $bonus_bulanan_jabatan = (int)($input['bonus_bulanan_jabatan'] ?? 0);
        $target_omset_harian = (int)($input['target_omset_harian'] ?? 450000);
        $target_omset_mingguan = (int)($input['target_omset_mingguan'] ?? 3500000);
        $target_omset_bulanan_30 = (int)($input['target_omset_bulanan_30'] ?? 15000000);
        $target_omset_bulanan_31 = (int)($input['target_omset_bulanan_31'] ?? 15500000);
        $target_hadir_mingguan = (int)($input['target_hadir_mingguan'] ?? 5);
        $target_hadir_bulanan = (int)($input['target_hadir_bulanan'] ?? 25);

        if (empty($role_name)) { echo json_encode(['status'=>'error','message'=>'Role kosong']); break; }

        $sql = "INSERT INTO hoki_salary_config (role_name, gaji_weekday, gaji_weekend, gaji_bulanan, tunjangan_jabatan, bonus_harian, bonus_mingguan, bonus_bulanan, bonus_harian_jabatan, bonus_mingguan_jabatan, bonus_bulanan_jabatan, target_omset_harian, target_omset_mingguan, target_omset_bulanan_30, target_omset_bulanan_31, target_hadir_mingguan, target_hadir_bulanan)
                VALUES ('$role_name', $gaji_weekday, $gaji_weekend, $gaji_bulanan, $tunjangan_jabatan, $bonus_harian, $bonus_mingguan, $bonus_bulanan, $bonus_harian_jabatan, $bonus_mingguan_jabatan, $bonus_bulanan_jabatan, $target_omset_harian, $target_omset_mingguan, $target_omset_bulanan_30, $target_omset_bulanan_31, $target_hadir_mingguan, $target_hadir_bulanan)
                ON DUPLICATE KEY UPDATE 
                    gaji_weekday = $gaji_weekday,
                    gaji_weekend = $gaji_weekend,
                    gaji_bulanan = $gaji_bulanan,
                    tunjangan_jabatan = $tunjangan_jabatan,
                    bonus_harian = $bonus_harian,
                    bonus_mingguan = $bonus_mingguan,
                    bonus_bulanan = $bonus_bulanan,
                    bonus_harian_jabatan = $bonus_harian_jabatan,
                    bonus_mingguan_jabatan = $bonus_mingguan_jabatan,
                    bonus_bulanan_jabatan = $bonus_bulanan_jabatan,
                    target_omset_harian = $target_omset_harian,
                    target_omset_mingguan = $target_omset_mingguan,
                    target_omset_bulanan_30 = $target_omset_bulanan_30,
                    target_omset_bulanan_31 = $target_omset_bulanan_31,
                    target_hadir_mingguan = $target_hadir_mingguan,
                    target_hadir_bulanan = $target_hadir_bulanan";
        $ok = $conn->query($sql);
        echo json_encode($ok ? ['status'=>'success'] : ['status'=>'error','message'=>$conn->error]);
        break;

    case 'get_hierarchy':
        header('Content-Type: application/json');
        $res = $conn->query("SELECT * FROM hoki_staff_hierarchy ORDER BY atasan_username ASC");
        $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(is_array($data) ? $data : []);
        break;

    case 'save_hierarchy':
        header('Content-Type: application/json');
        $atasan = $conn->real_escape_string($input['atasan'] ?? '');
        $bawahan = $conn->real_escape_string($input['bawahan'] ?? '');
        if (empty($atasan) || empty($bawahan)) { echo json_encode(['status'=>'error','message'=>'Form tidak lengkap']); break; }
        if ($atasan === $bawahan) { echo json_encode(['status'=>'error','message'=>'Atasan dan Bawahan tidak boleh sama']); break; }

        $ok = $conn->query("INSERT IGNORE INTO hoki_staff_hierarchy (atasan_username, bawahan_username) VALUES ('$atasan', '$bawahan')");
        echo json_encode($ok ? ['status'=>'success'] : ['status'=>'error','message'=>$conn->error]);
        break;

    case 'del_hierarchy':
        header('Content-Type: application/json');
        $id = (int)($_GET['id'] ?? 0);
        $ok = $conn->query("DELETE FROM hoki_staff_hierarchy WHERE id=$id");
        echo json_encode($ok ? ['status'=>'success'] : ['status'=>'error','message'=>$conn->error]);
        break;

    case 'calculate_payroll':
        header('Content-Type: application/json');
        $tgl_mulai = $conn->real_escape_string($_GET['tgl_mulai'] ?? '');
        $tgl_selesai = $conn->real_escape_string($_GET['tgl_selesai'] ?? '');
        
        if (empty($tgl_mulai) || empty($tgl_selesai)) {
            echo json_encode(["status"=>"error","message"=>"Rentang tanggal belum dipilih."]);
            break;
        }

        $satuan_minggu_ini = $tgl_selesai;
        $sabtu_kemarin = date('Y-m-d', strtotime('-1 day', strtotime($satuan_minggu_ini)));
        $minggu_lalu = date('Y-m-d', strtotime('-7 days', strtotime($satuan_minggu_ini)));
        $senin_dua_minggu_lalu = date('Y-m-d', strtotime('-7 days', strtotime($tgl_mulai)));
        $minggu_lalu_penuh = date('Y-m-d', strtotime('-7 days', strtotime($tgl_selesai)));

        if (!$conn) {
            echo json_encode(["status"=>"error","message"=>"Koneksi database gagal atau tidak tersedia."]);
            break;
        }

        $resUsers = $conn->query("SELECT username, fullName, role, cabang FROM users WHERE role != 'Owner' AND role != 'VIP' AND role != 'Investor' ORDER BY fullName ASC");
        if (!$resUsers) {
            echo json_encode(["status"=>"error","message"=>"Query users gagal: " . $conn->error]);
            break;
        }
        $users = $resUsers->fetch_all(MYSQLI_ASSOC);

        $resSalConfig = $conn->query("SELECT * FROM hoki_salary_config");
        if (!$resSalConfig) {
            echo json_encode(["status"=>"error","message"=>"Query salary config gagal: " . $conn->error]);
            break;
        }
        $salConfigsLower = [];
        while ($sc = $resSalConfig->fetch_assoc()) {
            $salConfigsLower[strtolower(trim($sc['role_name']))] = $sc;
        }

        $resHierarchy = $conn->query("SELECT * FROM hoki_staff_hierarchy");
        if (!$resHierarchy) {
            echo json_encode(["status"=>"error","message"=>"Query hierarchy gagal: " . $conn->error]);
            break;
        }
        $atasanMapping = [];
        while ($h = $resHierarchy->fetch_assoc()) {
            $atasanMapping[$h['atasan_username']][] = $h['bawahan_username'];
        }

        // 4. Fetch data settlement for daily salary & daily bonus ($minggu_lalu s/d $sabtu_kemarin)
        $resSettlementGP = $conn->query("SELECT waktu, petugas, cabang, grand_total, DAYOFWEEK(waktu) as dow FROM laporan_settlement WHERE waktu >= '{$minggu_lalu} 00:00:00' AND waktu <= '{$sabtu_kemarin} 23:59:59'");
        if (!$resSettlementGP) {
            echo json_encode(["status"=>"error","message"=>"Query settlement GP gagal: " . $conn->error]);
            break;
        }
        $settlementsGP = $resSettlementGP->fetch_all(MYSQLI_ASSOC);

        $omsetHarianCabangGP = [];
        $kehadiranStaffGP = [];

        foreach ($settlementsGP as $set) {
            $tgl = date('Y-m-d', strtotime($set['waktu']));
            $cab = $set['cabang'];
            $omsetHarianCabangGP[$tgl][$cab] = ($omsetHarianCabangGP[$tgl][$cab] ?? 0) + (int)$set['grand_total'];

            $petugasNames = array_map('trim', preg_split('/[,&]/', $set['petugas']));
            foreach ($users as $u) {
                if (in_array(strtolower($u['username']), array_map('strtolower', $petugasNames)) || in_array(strtolower($u['fullName']), array_map('strtolower', $petugasNames))) {
                    $kehadiranStaffGP[$u['username']][$tgl] = [
                        'cabang' => $cab,
                        'dow' => (int)$set['dow']
                    ];
                }
            }
        }

        // 5. Fetch data settlement for weekly bonus ($senin_dua_minggu_lalu s/d $minggu_lalu_penuh)
        $resSettlementBM = $conn->query("SELECT waktu, petugas, cabang, grand_total FROM laporan_settlement WHERE waktu >= '{$senin_dua_minggu_lalu} 00:00:00' AND waktu <= '{$minggu_lalu_penuh} 23:59:59'");
        if (!$resSettlementBM) {
            echo json_encode(["status"=>"error","message"=>"Query settlement BM gagal: " . $conn->error]);
            break;
        }
        $settlementsBM = $resSettlementBM->fetch_all(MYSQLI_ASSOC);

        $omsetHarianCabangBM = [];
        $kehadiranStaffBM = [];

        foreach ($settlementsBM as $set) {
            $tgl = date('Y-m-d', strtotime($set['waktu']));
            $cab = $set['cabang'];
            $omsetHarianCabangBM[$tgl][$cab] = ($omsetHarianCabangBM[$tgl][$cab] ?? 0) + (int)$set['grand_total'];

            $petugasNames = array_map('trim', preg_split('/[,&]/', $set['petugas']));
            foreach ($users as $u) {
                if (in_array(strtolower($u['username']), array_map('strtolower', $petugasNames)) || in_array(strtolower($u['fullName']), array_map('strtolower', $petugasNames))) {
                    $kehadiranStaffBM[$u['username']][$tgl] = [
                        'cabang' => $cab
                    ];
                }
            }
        }

        // 6. Fetch data settlement for monthly bonus (if transition week)
        $bulan_filter = date('m', strtotime($tgl_mulai));
        $bulan_sekarang = date('m');
        $is_transisi_bulan = ($bulan_filter !== $bulan_sekarang);
        
        $settlementsBB = [];
        $omsetHarianCabangBB = [];
        $kehadiranStaffBB = [];
        $tgl_awal_bulan_lalu = '';
        $tgl_akhir_bulan_lalu = '';

        if ($is_transisi_bulan) {
            $tgl_awal_bulan_lalu = date('Y-m-01', strtotime($tgl_mulai));
            $tgl_akhir_bulan_lalu = date('Y-m-t', strtotime($tgl_mulai));

            $resSettlementBB = $conn->query("SELECT waktu, petugas, cabang, grand_total FROM laporan_settlement WHERE waktu >= '{$tgl_awal_bulan_lalu} 00:00:00' AND waktu <= '{$tgl_akhir_bulan_lalu} 23:59:59'");
            if (!$resSettlementBB) {
                echo json_encode(["status"=>"error","message"=>"Query settlement BB gagal: " . $conn->error]);
                break;
            }
            $settlementsBB = $resSettlementBB->fetch_all(MYSQLI_ASSOC);

            foreach ($settlementsBB as $set) {
                $tgl = date('Y-m-d', strtotime($set['waktu']));
                $cab = $set['cabang'];
                $omsetHarianCabangBB[$tgl][$cab] = ($omsetHarianCabangBB[$tgl][$cab] ?? 0) + (int)$set['grand_total'];

                $petugasNames = array_map('trim', preg_split('/[,&]/', $set['petugas']));
                foreach ($users as $u) {
                    if (in_array(strtolower($u['username']), array_map('strtolower', $petugasNames)) || in_array(strtolower($u['fullName']), array_map('strtolower', $petugasNames))) {
                        $kehadiranStaffBB[$u['username']][$tgl] = [
                            'cabang' => $cab
                        ];
                    }
                }
            }
        }

        $payrollResults = [];
        foreach ($users as $u) {
            $username = $u['username'];
            $role = trim($u['role']);
            $role_lower = strtolower($role);
            if ($role_lower === 'staff' || $role_lower === 'junior staff') {
                $role_lower = 'junior staff';
            }
            $conf = $salConfigsLower[$role_lower] ?? [
                'gaji_weekday' => 0,
                'gaji_weekend' => 0,
                'gaji_bulanan' => 0,
                'tunjangan_jabatan' => 0,
                'bonus_harian' => 0,
                'bonus_mingguan' => 0,
                'bonus_bulanan' => 0,
                'bonus_harian_jabatan' => 0,
                'bonus_mingguan_jabatan' => 0,
                'bonus_bulanan_jabatan' => 0,
                'target_omset_harian' => 450000,
                'target_omset_mingguan' => 3500000,
                'target_omset_bulanan_30' => 15000000,
                'target_omset_bulanan_31' => 15500000,
                'target_hadir_mingguan' => 5,
                'target_hadir_bulanan' => 25
            ];

            // 1. Gaji Pokok & Bonus Harian ($minggu_lalu s/d $sabtu_kemarin)
            $logsKehadiranGP = $kehadiranStaffGP[$username] ?? [];
            $daysWeekday = 0;
            $daysWeekend = 0;
            $subtotalWeekday = 0;
            $subtotalWeekend = 0;
            $totalBonusHarian = 0;
            $qtyBonusHarian = 0;
            $totalOmsetStaffGP = 0;
            $rincian = [];

            foreach ($logsKehadiranGP as $tgl => $log) {
                $cabOmset = $omsetHarianCabangGP[$tgl][$log['cabang']] ?? 0;
                $totalOmsetStaffGP += $cabOmset;

                $dow = (int)$log['dow'];
                if ($dow === 1 || $dow === 7) { // 1 = Minggu, 7 = Sabtu -> WEEKEND
                    $daysWeekend++;
                    $subtotalWeekend += (int)$conf['gaji_weekend'];
                } else if ($dow >= 2 && $dow <= 6) { // 2 s/d 6 = Senin s/d Jumat -> WEEKDAY
                    $daysWeekday++;
                    $subtotalWeekday += (int)$conf['gaji_weekday'];
                }

                if ($cabOmset >= (int)$conf['target_omset_harian'] && (int)$conf['bonus_harian'] > 0) {
                    $totalBonusHarian += (int)$conf['bonus_harian'];
                    $qtyBonusHarian++;
                }
            }

            if ($daysWeekday > 0) {
                $rincian[] = ["kategori" => "Gaji Weekday (Cut-off)", "nominal" => (int)$conf['gaji_weekday'], "qty" => $daysWeekday, "subtotal" => $subtotalWeekday];
            }
            if ($daysWeekend > 0) {
                $rincian[] = ["kategori" => "Gaji Weekend (Cut-off)", "nominal" => (int)$conf['gaji_weekend'], "qty" => $daysWeekend, "subtotal" => $subtotalWeekend];
            }
            if ((int)$conf['gaji_bulanan'] > 0) {
                $rincian[] = ["kategori" => "Gaji Bulanan", "nominal" => (int)$conf['gaji_bulanan'], "qty" => 1, "subtotal" => (int)$conf['gaji_bulanan']];
            }
            if ((int)$conf['tunjangan_jabatan'] > 0) {
                $rincian[] = ["kategori" => "Tunjangan Jabatan", "nominal" => (int)$conf['tunjangan_jabatan'], "qty" => 1, "subtotal" => (int)$conf['tunjangan_jabatan']];
            }
            if ($totalBonusHarian > 0) {
                $rincian[] = ["kategori" => "Bonus Harian Cabang", "nominal" => (int)$conf['bonus_harian'], "qty" => $qtyBonusHarian, "subtotal" => $totalBonusHarian];
            }

            $totalMasuk = count($logsKehadiranGP);

            // 2. Bonus Mingguan ($senin_dua_minggu_lalu s/d $minggu_lalu_penuh)
            $logsKehadiranBM = $kehadiranStaffBM[$username] ?? [];
            $totalMasukBM = count($logsKehadiranBM);
            $totalOmsetStaffBM = 0;
            foreach ($logsKehadiranBM as $tgl => $log) {
                $totalOmsetStaffBM += $omsetHarianCabangBM[$tgl][$log['cabang']] ?? 0;
            }

            $targetHadirMingguan = isset($conf['target_hadir_mingguan']) ? (int)$conf['target_hadir_mingguan'] : 6;
            $winMingguan = ($totalOmsetStaffBM >= (int)$conf['target_omset_mingguan'] && $totalMasukBM >= $targetHadirMingguan);
            $totalBonusMingguan = $winMingguan ? (int)$conf['bonus_mingguan'] : 0;
            if ($totalBonusMingguan > 0) {
                $rincian[] = ["kategori" => "Bonus Mingguan (Periode Lalu)", "nominal" => $totalBonusMingguan, "qty" => 1, "subtotal" => $totalBonusMingguan];
            }

            // 3. Bonus Bulanan (Cut-off Tanggal 1 s/d Tanggal Terakhir bulan lalu)
            $winBulanan = false;
            $totalBonusBulanan = 0;
            if ($is_transisi_bulan) {
                $logsKehadiranBB = $kehadiranStaffBB[$username] ?? [];
                $totalMasukBB = count($logsKehadiranBB);
                $totalOmsetStaffBB = 0;
                foreach ($logsKehadiranBB as $tgl => $log) {
                    $totalOmsetStaffBB += $omsetHarianCabangBB[$tgl][$log['cabang']] ?? 0;
                }

                $daysInMonth = (int)date('t', strtotime($tgl_mulai));
                $targetBulan = ($daysInMonth >= 31) ? (int)$conf['target_omset_bulanan_31'] : (int)$conf['target_omset_bulanan_30'];
                $targetHadirBulanan = isset($conf['target_hadir_bulanan']) ? (int)$conf['target_hadir_bulanan'] : 25;

                $winBulanan = ($totalOmsetStaffBB >= $targetBulan && $totalMasukBB >= $targetHadirBulanan);
                $totalBonusBulanan = $winBulanan ? (int)$conf['bonus_bulanan'] : 0;
                if ($totalBonusBulanan > 0) {
                    $rincian[] = ["kategori" => "Bonus Bulanan (Bulan Lalu)", "nominal" => $totalBonusBulanan, "qty" => 1, "subtotal" => $totalBonusBulanan];
                }
            }

            $totalGajiFix = $subtotalWeekday + $subtotalWeekend + (int)$conf['gaji_bulanan'] + (int)$conf['tunjangan_jabatan'] + $totalBonusHarian + $totalBonusMingguan + $totalBonusBulanan;

            $payrollResults[$username] = [
                'username' => $username,
                'fullName' => $u['fullName'],
                'role' => $role,
                'cabang' => $u['cabang'],
                'days_weekday' => $daysWeekday,
                'days_weekend' => $daysWeekend,
                'total_masuk' => $totalMasuk,
                'total_omset' => $totalOmsetStaffGP,
                'win_bonus_harian_qty' => $qtyBonusHarian,
                'win_bonus_mingguan' => $winMingguan,
                'win_bonus_bulanan' => $winBulanan,
                'total_gapok' => $subtotalWeekday + $subtotalWeekend + (int)$conf['gaji_bulanan'] + (int)$conf['tunjangan_jabatan'],
                'total_bonus_harian' => $totalBonusHarian,
                'total_bonus_mingguan' => $totalBonusMingguan,
                'total_bonus_bulanan' => $totalBonusBulanan,
                'total_bonus_jabatan' => 0,
                'rincian' => $rincian,
                'total_gaji' => $totalGajiFix
            ];
        }

        // 5. Kalkulasi Tambahan untuk Bonus Jabatan (Atasan yang memantau Bawahan)
        foreach ($payrollResults as $username => &$pData) {
            if (isset($atasanMapping[$username])) {
                $bawahans = $atasanMapping[$username];
                $role = trim($pData['role']);
                $role_lower = strtolower($role);
                if ($role_lower === 'staff' || $role_lower === 'junior staff') {
                    $role_lower = 'junior staff';
                }
                $conf = $salConfigsLower[$role_lower] ?? [
                    'gaji_weekday' => 0,
                    'gaji_weekend' => 0,
                    'gaji_bulanan' => 0,
                    'tunjangan_jabatan' => 0,
                    'bonus_harian' => 0,
                    'bonus_mingguan' => 0,
                    'bonus_bulanan' => 0,
                    'bonus_harian_jabatan' => 0,
                    'bonus_mingguan_jabatan' => 0,
                    'bonus_bulanan_jabatan' => 0,
                    'target_omset_harian' => 450000,
                    'target_omset_mingguan' => 3500000,
                    'target_omset_bulanan_30' => 15000000,
                    'target_omset_bulanan_31' => 15500000,
                    'target_hadir_mingguan' => 5,
                    'target_hadir_bulanan' => 25
                ];

                $qtyJabHarian = 0;
                $qtyJabMingguan = 0;
                $qtyJabBulanan = 0;

                foreach ($bawahans as $bUser) {
                    if (isset($payrollResults[$bUser])) {
                        $qtyJabHarian += $payrollResults[$bUser]['win_bonus_harian_qty'];
                        if ($payrollResults[$bUser]['win_bonus_mingguan']) { $qtyJabMingguan++; }
                        if ($payrollResults[$bUser]['win_bonus_bulanan']) { $qtyJabBulanan++; }
                    }
                }

                $bonusJabatanNominal = 0;
                if ($qtyJabHarian > 0 && (int)$conf['bonus_harian_jabatan'] > 0) {
                    $sub = (int)$conf['bonus_harian_jabatan'] * $qtyJabHarian;
                    $bonusJabatanNominal += $sub;
                    $pData['rincian'][] = ["kategori" => "Bonus Jabatan Harian (Bawahan Tembus)", "nominal" => (int)$conf['bonus_harian_jabatan'], "qty" => $qtyJabHarian, "subtotal" => $sub];
                }
                if ($qtyJabMingguan > 0 && (int)$conf['bonus_mingguan_jabatan'] > 0) {
                    $sub = (int)$conf['bonus_mingguan_jabatan'] * $qtyJabMingguan;
                    $bonusJabatanNominal += $sub;
                    $pData['rincian'][] = ["kategori" => "Bonus Jabatan Mingguan (Bawahan Tembus)", "nominal" => (int)$conf['bonus_mingguan_jabatan'], "qty" => $qtyJabMingguan, "subtotal" => $sub];
                }
                if ($qtyJabBulanan > 0 && (int)$conf['bonus_bulanan_jabatan'] > 0) {
                    $sub = (int)$conf['bonus_bulanan_jabatan'] * $qtyJabBulanan;
                    $bonusJabatanNominal += $sub;
                    $pData['rincian'][] = ["kategori" => "Bonus Jabatan Bulanan (Bawahan Tembus)", "nominal" => (int)$conf['bonus_bulanan_jabatan'], "qty" => $qtyJabBulanan, "subtotal" => $sub];
                }

                $pData['total_bonus_jabatan'] = $bonusJabatanNominal;
                $pData['total_gaji'] += $bonusJabatanNominal; // Akumulasi final ke total_gaji
            }
        }
        unset($pData);

        echo json_encode(array_values($payrollResults));
        break;

    // ── INVESTOR PROFIT HISTORY ───────────────────────
    case 'save_profit_history':
        $tgl_rekam = $conn->real_escape_string($input['tanggal_rekam'] ?? date('Y-m-d'));
        $bln_thn = $conn->real_escape_string($input['bulan_tahun'] ?? '');
        $inv = $conn->real_escape_string($input['investor_username'] ?? '');
        $cab = $conn->real_escape_string($input['cabang'] ?? '');
        $omset = (int)($input['omset'] ?? 0);
        $lk = (int)($input['laba_kotor'] ?? 0);
        $do = (int)($input['dana_operasional'] ?? 0);
        $hi = (int)($input['hak_investor'] ?? 0);
        
        $sql = "INSERT INTO hoki_profit_sharing_history (tanggal_rekam, bulan_tahun, investor_username, cabang, omset, laba_kotor, dana_operasional, hak_investor) 
                VALUES ('$tgl_rekam', '$bln_thn', '$inv', '$cab', $omset, $lk, $do, $hi)";
        if ($conn->query($sql)) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        break;
        
    case 'get_profit_history':
        $res = $conn->query("SELECT *, DATE_FORMAT(tanggal_rekam, '%d %M %Y') as tgl_fmt FROM hoki_profit_sharing_history ORDER BY id DESC");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        break;
        
    case 'del_profit_history':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM hoki_profit_sharing_history WHERE id=$id");
        echo json_encode(["status" => "success"]);
        break;

    // ── PELANGGAN (MEMBER) ────────────────────────────
    case 'get_pelanggan':
        $res = $conn->query("SELECT * FROM hoki_pelanggan ORDER BY nama ASC");
        $data = [];
        if ($res) { while ($r = $res->fetch_assoc()) $data[] = $r; }
        echo json_encode(["status"=>"success","data"=>$data]);
        break;

    case 'save_pelanggan':
        $nama = $conn->real_escape_string(trim($input['nama'] ?? ''));
        $telepon = preg_replace('/[^0-9]/', '', $input['telepon'] ?? '');
        if (!$nama || !$telepon) {
            echo json_encode(["status"=>"error","message"=>"Nama dan No. HP wajib diisi."]);
            break;
        }
        $telepon = $conn->real_escape_string($telepon);
        $conn->query("INSERT INTO hoki_pelanggan (nama, telepon) VALUES ('$nama','$telepon') ON DUPLICATE KEY UPDATE nama = VALUES(nama)");
        $newId = $conn->insert_id ?: 0;
        if (!$newId) {
            $r = $conn->query("SELECT id FROM hoki_pelanggan WHERE telepon='$telepon'")->fetch_assoc();
            $newId = $r['id'] ?? 0;
        }
        echo json_encode(["status"=>"success","id"=>$newId,"nama"=>$nama,"telepon"=>$telepon]);
        break;

    case 'get_customer_loyalty':
        $sql = "
            SELECT 
                pelanggan_telepon as telepon,
                COUNT(id) as total_order,
                COALESCE(SUM(total), 0) as total_belanja
            FROM transaksi
            WHERE pelanggan_telepon IS NOT NULL AND pelanggan_telepon != ''
            GROUP BY pelanggan_telepon
        ";
        $res = $conn->query($sql);
        $data = [];
        if ($res) { 
            while ($r = $res->fetch_assoc()) {
                $r['total_order'] = (int)$r['total_order'];
                $r['total_belanja'] = (float)$r['total_belanja'];
                $data[] = $r;
            }
        }
        echo json_encode(["status"=>"success","data"=>$data]);
        break;

    case 'get_top_customers':
        $sql = "
            SELECT 
                pelanggan_nama as nama,
                pelanggan_telepon as telepon,
                COUNT(id) as total_order,
                SUM(total) as total_rupiah,
                GROUP_CONCAT(DISTINCT cabang SEPARATOR ', ') as cabang_belanja
            FROM transaksi
            WHERE pelanggan_nama != 'Umum / Non Member' AND pelanggan_nama != 'No Cust' AND pelanggan_nama != '' AND pelanggan_telepon != ''
            GROUP BY pelanggan_telepon, pelanggan_nama
            ORDER BY total_order DESC, total_rupiah DESC
            LIMIT 10
        ";
        $res = $conn->query($sql);
        $data = [];
        if ($res) { 
            while ($r = $res->fetch_assoc()) {
                $r['total_order'] = (int)$r['total_order'];
                $r['total_rupiah'] = (float)$r['total_rupiah'];
                $data[] = $r;
            }
        }
        echo json_encode(["status"=>"success","data"=>$data]);
        break;


    case 'update_pelanggan':
        $id = (int)($input['id'] ?? 0);
        $nama = $conn->real_escape_string(trim($input['nama'] ?? ''));
        $telepon = preg_replace('/[^0-9]/', '', $input['telepon'] ?? '');
        
        if (!$id || !$nama || !$telepon) {
            echo json_encode(["status"=>"error","message"=>"ID, Nama, dan No. HP wajib diisi."]);
            break;
        }
        
        $telepon = $conn->real_escape_string($telepon);
        
        // Cek duplikasi no telp pada ID lain
        $cek = $conn->query("SELECT id FROM hoki_pelanggan WHERE telepon='$telepon' AND id != $id");
        if ($cek && $cek->num_rows > 0) {
            echo json_encode(["status"=>"error","message"=>"Nomor telepon ini sudah digunakan pelanggan lain."]);
            break;
        }
        
        $sql = "UPDATE hoki_pelanggan SET nama='$nama', telepon='$telepon' WHERE id=$id";
        if ($conn->query($sql)) {
            echo json_encode(["status"=>"success"]);
        } else {
            echo json_encode(["status"=>"error","message"=>$conn->error]);
        }
        break;

    case 'delete_pelanggan':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(["status"=>"error","message"=>"ID pelanggan tidak valid."]);
            break;
        }
        
        $sql = "DELETE FROM hoki_pelanggan WHERE id=$id";
        if ($conn->query($sql)) {
            echo json_encode(["status"=>"success"]);
        } else {
            echo json_encode(["status"=>"error","message"=>$conn->error]);
        }
        break;

    // ── EDUKASI & SOP ─────────────────────────────────
    case 'get_edukasi':
        $role = $conn->real_escape_string($_GET['role'] ?? '');
        $sql = "SELECT * FROM hoki_edukasi WHERE role_access = 'Semua' OR FIND_IN_SET('$role', role_access) ORDER BY id DESC";
        $res = $conn->query($sql);
        $data = [];
        if ($res) { while ($r = $res->fetch_assoc()) $data[] = $r; }
        echo json_encode(["status"=>"success","data"=>$data]);
        break;

    case 'save_edukasi':
        $judul = $conn->real_escape_string(trim($input['judul'] ?? ''));
        $kategori = $conn->real_escape_string(trim($input['kategori'] ?? 'SOP Umum'));
        $url_pdf = $conn->real_escape_string(trim($input['url_pdf'] ?? ''));
        $role_access = $conn->real_escape_string(trim($input['role_access'] ?? 'Semua'));
        $uploaded_by = $conn->real_escape_string(trim($input['uploaded_by'] ?? ''));
        if (!$judul || !$url_pdf) {
            echo json_encode(["status"=>"error","message"=>"Judul dan URL PDF wajib diisi."]);
            break;
        }
        $sql = "INSERT INTO hoki_edukasi (judul, kategori, url_pdf, role_access, uploaded_by) VALUES ('$judul','$kategori','$url_pdf','$role_access','$uploaded_by')";
        if ($conn->query($sql)) {
            echo json_encode(["status"=>"success","id"=>$conn->insert_id]);
        } else {
            echo json_encode(["status"=>"error","message"=>$conn->error]);
        }
        break;

    case 'del_edukasi':
        $id = (int)($_GET['id'] ?? 0);
        $role = $_GET['role'] ?? '';
        if ($role !== 'VIP') {
            echo json_encode(["status"=>"error","message"=>"Hanya VIP yang dapat menghapus dokumen."]);
            break;
        }
        if (!$id) {
            echo json_encode(["status"=>"error","message"=>"ID dokumen tidak valid."]);
            break;
        }
        if ($conn->query("DELETE FROM hoki_edukasi WHERE id=$id")) {
            echo json_encode(["status"=>"success"]);
        } else {
            echo json_encode(["status"=>"error","message"=>$conn->error]);
        }
        break;

    // ── HISTORY REKAP KEUANGAN ────────────────────────
    case 'save_rekap_keuangan':
        $filter_cabang = $conn->real_escape_string($input['filter_cabang'] ?? '');
        $filter_tanggal = $conn->real_escape_string($input['filter_tanggal'] ?? '');
        $pemasukan_json = $conn->real_escape_string(json_encode($input['pemasukan'] ?? []));
        $pengeluaran_json = $conn->real_escape_string(json_encode($input['pengeluaran'] ?? []));
        $total_pemasukan = (int)($input['total_pemasukan'] ?? 0);
        $total_pengeluaran = (int)($input['total_pengeluaran'] ?? 0);
        $total_setoran = $total_pemasukan - $total_pengeluaran;
        $petugas = $conn->real_escape_string($input['petugas'] ?? '');

        $sql = "INSERT INTO hoki_history_rekap (filter_cabang, filter_tanggal, pemasukan_json, pengeluaran_json, total_pemasukan, total_pengeluaran, total_setoran, petugas) 
                VALUES ('$filter_cabang', '$filter_tanggal', '$pemasukan_json', '$pengeluaran_json', $total_pemasukan, $total_pengeluaran, $total_setoran, '$petugas')";
        if ($conn->query($sql)) {
            echo json_encode(["status"=>"success"]);
        } else {
            echo json_encode(["status"=>"error","message"=>$conn->error]);
        }
        break;

    case 'get_history_rekap':
        $res = $conn->query("SELECT * FROM hoki_history_rekap ORDER BY id DESC");
        $data = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $r['pemasukan'] = json_decode($r['pemasukan_json'], true);
                $r['pengeluaran'] = json_decode($r['pengeluaran_json'], true);
                unset($r['pemasukan_json']);
                unset($r['pengeluaran_json']);
                $data[] = $r;
            }
        }
        echo json_encode(["status"=>"success","data"=>$data]);
        break;

    case 'del_history_rekap':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(["status"=>"error","message"=>"ID rekap tidak valid."]);
            break;
        }
        if ($conn->query("DELETE FROM hoki_history_rekap WHERE id=$id")) {
            echo json_encode(["status"=>"success"]);
        } else {
            echo json_encode(["status"=>"error","message"=>$conn->error]);
        }
        break;

    // ─────────────────────────────────────────────────
    default:
        echo json_encode(["status"=>"error","message"=>"Action '$action' tidak dikenali."]);
        break;
        
        
    // ── AUDIT ITEM ──────────────────────────────    
    case 'get_audit_items_master':
        // 1. Daftar Item Audit Default (Samakan dengan laporan_staff.html)
        $items = ['Dimsum Shaomai (Pcs)', 'Alu Tray AX-350 (Pcs)'];

        // 3. Ambil dari Ledger (Guna menangkap custom item yang mungkin pernah diketik staff)
        $res2 = $conn->query("SELECT DISTINCT sku FROM warehouse_ledger");
        if ($res2) {
            while($r = $res2->fetch_assoc()) {
                $items[] = $r['sku'];
            }
        }

        // Hilangkan duplikasi nama dan urutkan
        $finalList = array_unique($items);
        sort($finalList);

        $output = [];
        foreach ($finalList as $name) {
            if (!empty($name)) $output[] = ["nama" => $name];
        }
        echo json_encode($output);
        break;    
}

$conn->close();
?>