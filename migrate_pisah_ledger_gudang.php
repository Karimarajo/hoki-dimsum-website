<?php
/**
 * Migrasi SATU KALI PAKAI - memisahkan pencatatan Stok Gudang dari Bahan Baku.
 *
 * Sebelum : semua catatan ada di warehouse_ledger, dibedakan lewat tebakan nama/bahan_id.
 * Sesudah : - stok_gudang_ledger  = pencatatan MANUAL Stok Gudang (kunci: inventory_id)
 *           - warehouse_ledger    = HANYA Bahan Baku (kunci: bahan_id wajib terisi)
 *
 * Aturan per baris warehouse_ledger yang bahan_id-nya NULL:
 *   A. sku persis (trim, tanpa beda huruf besar/kecil) = inventory.nama_barang
 *        -> DIPINDAH ke stok_gudang_ledger (inventory_id).
 *   B. sku = "Lunch Box S (New)" / "Lunch Box M (New)"  (keputusan user: duplikat lama)
 *        -> DIHAPUS.
 *   C. sku persis = bahan_baku.nama, atau "Nama (Satuan)" dgn satuan cocok, dan tidak ambigu
 *        -> bahan_id DIISI (tetap di warehouse_ledger).
 *   D. selain itu -> TIDAK disentuh, hanya dilaporkan untuk dicek manual.
 * Baris yang bahan_id-nya sudah terisi tidak diubah.
 *
 * Sebelum menulis, dibuat tabel cadangan bak_warehouse_ledger_<tanggal>.
 *
 *   php migrate_pisah_ledger_gudang.php                       -> dry-run (DB dev lokal)
 *   php migrate_pisah_ledger_gudang.php --apply               -> tulis (DB dev lokal)
 *   php migrate_pisah_ledger_gudang.php --prod --apply        -> production (eksplisit)
 *   opsi tambahan: --port=3307
 *
 * Hapus file ini setelah dijalankan ke production.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Script ini hanya boleh dijalankan lewat CLI, bukan lewat browser.\n");
}

$apply = in_array('--apply', $argv, true);
$prod  = in_array('--prod', $argv, true);
$port  = 3306;
foreach ($argv as $a) if (preg_match('/^--port=(\d+)$/', $a, $m)) $port = (int)$m[1];

if ($prod) {
    $conn = @new mysqli("localhost", "u173485424_kurniarp", "Alpukat19#", "u173485424_hoki", $port);
} else {
    $conn = @new mysqli("127.0.0.1", "root", "", "u173485424_hoki", $port);
}
if (!$conn || $conn->connect_error) {
    die("Koneksi database gagal: " . ($conn ? $conn->connect_error : "unknown") . "\n");
}
$conn->set_charset("utf8mb4");

echo "=== Migrasi pisah ledger Stok Gudang / Bahan Baku ===\n";
echo "Target DB : " . ($prod ? "PRODUCTION" : "DEV lokal (port $port)") . "\n";
echo "Mode      : " . ($apply ? "APPLY (menulis perubahan)" : "DRY-RUN (tidak ada perubahan)") . "\n\n";

// Cegah dobel jalan: kalau tabel baru sudah berisi, berhenti.
$ex = $conn->query("SHOW TABLES LIKE 'stok_gudang_ledger'");
if ($ex && $ex->num_rows > 0) {
    $c = (int)$conn->query("SELECT COUNT(*) c FROM stok_gudang_ledger")->fetch_assoc()['c'];
    if ($c > 0) die("stok_gudang_ledger sudah berisi $c baris. Migrasi sudah pernah dijalankan - dihentikan.\n");
}

// ── Peta inventory & bahan_baku ──
$inv = [];                                   // lower(trim(nama)) => id
$r = $conn->query("SELECT id, nama_barang FROM inventory");
while ($row = $r->fetch_assoc()) $inv[strtolower(trim($row['nama_barang']))] = (int)$row['id'];

$bahan = []; $ambiguous = [];                // lower(nama) => ['id','satuan']
$r = $conn->query("SELECT id, nama, satuan FROM bahan_baku");
while ($row = $r->fetch_assoc()) {
    $n = strtolower(trim($row['nama']));
    if ($n === '') continue;
    if (isset($bahan[$n]) && $bahan[$n]['id'] !== (int)$row['id']) $ambiguous[$n] = true;
    $bahan[$n] = ['id' => (int)$row['id'], 'satuan' => strtolower(trim($row['satuan'] ?? ''))];
}

$hapusSku = ['lunch box s (new)' => true, 'lunch box m (new)' => true];

// ── Klasifikasi ──
$pindah = []; $hapus = []; $isiBid = []; $sisa = [];
$r = $conn->query("SELECT id, tgl, sku, masuk, keluar, catatan, created_at FROM warehouse_ledger
                   WHERE bahan_id IS NULL OR bahan_id = 0 ORDER BY id ASC");
while ($row = $r->fetch_assoc()) {
    $sku = trim($row['sku']); $low = strtolower($sku);
    if (isset($inv[$low])) {
        $row['inventory_id'] = $inv[$low]; $pindah[] = $row; continue;
    }
    if (isset($hapusSku[$low])) { $hapus[] = $row; continue; }
    $bid = null;
    if (isset($bahan[$low]) && !isset($ambiguous[$low])) {
        $bid = $bahan[$low]['id'];
    } elseif (preg_match('/^(.*?)\s*\(([^)]*)\)\s*$/', $sku, $m)) {
        $b = strtolower(trim($m[1])); $s = strtolower(trim($m[2]));
        if (isset($bahan[$b]) && !isset($ambiguous[$b]) && $bahan[$b]['satuan'] === $s) $bid = $bahan[$b]['id'];
    }
    if ($bid !== null) { $row['bahan_id_baru'] = $bid; $isiBid[] = $row; continue; }
    $sisa[] = $row;
}

// Peringatan: baris ber-bahan_id yang sku-nya persis barang inventory (tidak diubah)
$warnMix = [];
$r = $conn->query("SELECT id, sku, bahan_id FROM warehouse_ledger WHERE bahan_id > 0");
while ($row = $r->fetch_assoc()) {
    $low = strtolower(trim($row['sku']));
    if (isset($inv[$low])) $warnMix[] = $row;
}

function ringkas(array $rows, string $judul): void {
    echo "$judul: " . count($rows) . " baris\n";
    $g = [];
    foreach ($rows as $x) {
        $k = $x['sku'];
        $g[$k] = $g[$k] ?? [0, 0.0, 0.0];
        $g[$k][0]++; $g[$k][1] += (float)$x['masuk']; $g[$k][2] += (float)$x['keluar'];
    }
    ksort($g);
    foreach ($g as $k => $v) printf("   - %-38s %3d baris  masuk=%-8s keluar=%s\n", $k, $v[0], $v[1], $v[2]);
    echo "\n";
}
ringkas($pindah, "A. DIPINDAH ke stok_gudang_ledger");
ringkas($hapus,  "B. DIHAPUS (duplikat Lunch Box (New) lama)");
ringkas($isiBid, "C. bahan_id DIISI (tetap di warehouse_ledger)");
ringkas($sisa,   "D. TIDAK DISENTUH (cek manual)");
if ($warnMix) {
    echo "PERINGATAN: " . count($warnMix) . " baris ber-bahan_id memakai nama barang Stok Gudang (tidak diubah): ";
    echo implode(', ', array_map(fn($w) => '#' . $w['id'], $warnMix)) . "\n\n";
}

if (!$apply) {
    echo "DRY-RUN selesai. Jalankan lagi dengan --apply untuk menerapkan.\n";
    exit(0);
}

// ── APPLY ──
$bak = 'bak_warehouse_ledger_' . date('Ymd');
$conn->query("DROP TABLE IF EXISTS `$bak`");
if (!$conn->query("CREATE TABLE `$bak` AS SELECT * FROM warehouse_ledger")) die("Gagal membuat cadangan: " . $conn->error . "\n");
$nb = (int)$conn->query("SELECT COUNT(*) c FROM `$bak`")->fetch_assoc()['c'];
echo "Cadangan dibuat: $bak ($nb baris)\n";

$ok = $conn->query("CREATE TABLE IF NOT EXISTS stok_gudang_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tgl DATE NOT NULL,
    inventory_id INT NOT NULL,
    nama_barang VARCHAR(255) NOT NULL,
    masuk FLOAT DEFAULT 0,
    keluar FLOAT DEFAULT 0,
    catatan VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inv (inventory_id),
    INDEX idx_tgl (tgl)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if (!$ok) die("Gagal membuat stok_gudang_ledger: " . $conn->error . "\n");

$conn->begin_transaction();
try {
    $ins = $conn->prepare("INSERT INTO stok_gudang_ledger (tgl, inventory_id, nama_barang, masuk, keluar, catatan, created_at)
                           VALUES (?,?,?,?,?,?,?)");
    $del = $conn->prepare("DELETE FROM warehouse_ledger WHERE id = ?");
    foreach ($pindah as $x) {
        $namaResmi = $conn->query("SELECT nama_barang FROM inventory WHERE id=" . (int)$x['inventory_id'])->fetch_assoc()['nama_barang'];
        $masuk = (float)$x['masuk']; $keluar = (float)$x['keluar']; $id = (int)$x['id'];
        $inv_id = (int)$x['inventory_id'];
        $ins->bind_param('sisddss', $x['tgl'], $inv_id, $namaResmi, $masuk, $keluar, $x['catatan'], $x['created_at']);
        if (!$ins->execute()) throw new Exception($ins->error);
        $del->bind_param('i', $id); $del->execute();
    }
    foreach ($hapus as $x) { $id = (int)$x['id']; $del->bind_param('i', $id); $del->execute(); }
    $upd = $conn->prepare("UPDATE warehouse_ledger SET bahan_id = ? WHERE id = ? AND (bahan_id IS NULL OR bahan_id = 0)");
    foreach ($isiBid as $x) { $b = (int)$x['bahan_id_baru']; $id = (int)$x['id']; $upd->bind_param('ii', $b, $id); $upd->execute(); }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    die("GAGAL, di-rollback: " . $e->getMessage() . "\n(tabel stok_gudang_ledger sudah dibuat tapi kosong; cadangan: $bak)\n");
}

// keluar jadi FLOAT supaya bahan berdesimal (gr/ml) tidak terpotong
$conn->query("ALTER TABLE warehouse_ledger MODIFY keluar FLOAT DEFAULT 0");

echo "\nSELESAI.\n";
echo "  stok_gudang_ledger : " . $conn->query("SELECT COUNT(*) c FROM stok_gudang_ledger")->fetch_assoc()['c'] . " baris\n";
echo "  warehouse_ledger   : " . $conn->query("SELECT COUNT(*) c FROM warehouse_ledger")->fetch_assoc()['c'] . " baris\n";
echo "  bahan_id masih NULL: " . $conn->query("SELECT COUNT(*) c FROM warehouse_ledger WHERE bahan_id IS NULL OR bahan_id=0")->fetch_assoc()['c'] . " baris\n";
