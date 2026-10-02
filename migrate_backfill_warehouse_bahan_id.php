<?php
/**
 * Script migrasi SATU KALI PAKAI - backfill warehouse_ledger.bahan_id pada baris lama
 * yang masih NULL, dengan mencocokkan teks sku (suffix "(satuan)" dibuang, trim, lowercase)
 * ke bahan_baku.nama yang dinormalisasi sama persis.
 *
 * HANYA jalan lewat CLI, dan HANYA menyentuh DB dev lokal secara default:
 *   php migrate_backfill_warehouse_bahan_id.php            -> dry-run, preview saja (dev)
 *   php migrate_backfill_warehouse_bahan_id.php --apply     -> benar2 tulis perubahan (dev)
 *   php migrate_backfill_warehouse_bahan_id.php --prod --apply -> ke production (eksplisit)
 *
 * Baris yang TIDAK ketemu pasangannya TIDAK di-auto-merge/ditebak - cuma dilaporkan utk
 * direview manual. Hapus file ini setelah dijalankan ke production (ikuti kebiasaan project
 * ini utk script migrasi sekali-pakai, lihat riwayat commit).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Script ini hanya boleh dijalankan lewat command line (CLI), bukan lewat browser.\n");
}

$apply = in_array('--apply', $argv, true);
$prod  = in_array('--prod', $argv, true);

if ($prod) {
    $conn = @new mysqli("localhost", "u173485424_kurniarp", "Alpukat19#", "u173485424_hoki");
} else {
    $conn = @new mysqli("localhost", "root", "", "u173485424_hoki");
}

if (!$conn || $conn->connect_error) {
    die("Koneksi database gagal: " . ($conn ? $conn->connect_error : "unknown") . "\n");
}
$conn->set_charset("utf8mb4");

echo "=== Migrasi backfill warehouse_ledger.bahan_id ===\n";
echo "Target DB : " . ($prod ? "PRODUCTION" : "DEV lokal") . "\n";
echo "Mode      : " . ($apply ? "APPLY (akan menulis perubahan)" : "DRY-RUN (preview saja, tidak ada perubahan)") . "\n\n";

// Pastikan kolom bahan_id sudah ada (migration idempoten di api.php seharusnya sudah bikin ini
// jalan duluan - kalau belum, berhenti daripada nebak-nebak skema).
$check = $conn->query("SHOW COLUMNS FROM warehouse_ledger LIKE 'bahan_id'");
if (!$check || $check->num_rows === 0) {
    die("Kolom bahan_id belum ada di warehouse_ledger. Buka/hit api.php dulu (migration otomatis jalan di awal request) sebelum menjalankan script ini.\n");
}

function normalisasi_nama_bahan_migrasi(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s*\([^)]*\)\s*$/', '', $s);
    return strtolower(trim($s));
}

// Peta nama bahan_baku ternormalisasi -> id. Nama yang ternormalisasi sama tapi beda id
// (tabrakan) ditandai ambigu dan DILEWATI sepenuhnya demi keamanan (tidak pernah ditebak).
$bahanMap  = [];
$ambiguous = [];
$resBahan = $conn->query("SELECT id, nama FROM bahan_baku");
while ($row = $resBahan->fetch_assoc()) {
    $norm = normalisasi_nama_bahan_migrasi($row['nama']);
    if ($norm === '') continue;
    if (isset($bahanMap[$norm]) && $bahanMap[$norm] !== (int)$row['id']) {
        $ambiguous[$norm] = true;
    }
    $bahanMap[$norm] = (int)$row['id'];
}

$resLedger = $conn->query("SELECT id, tgl, sku FROM warehouse_ledger WHERE bahan_id IS NULL ORDER BY id ASC");

$matched   = [];
$unmatched = [];
while ($row = $resLedger->fetch_assoc()) {
    $norm = normalisasi_nama_bahan_migrasi($row['sku']);
    if ($norm !== '' && isset($bahanMap[$norm]) && !isset($ambiguous[$norm])) {
        $matched[] = ['id' => $row['id'], 'tgl' => $row['tgl'], 'sku' => $row['sku'], 'bahan_id' => $bahanMap[$norm]];
    } else {
        $unmatched[] = ['id' => $row['id'], 'tgl' => $row['tgl'], 'sku' => $row['sku']];
    }
}

echo "Baris cocok (akan di-backfill)   : " . count($matched) . "\n";
echo "Baris TIDAK cocok (perlu review) : " . count($unmatched) . "\n";
if (!empty($ambiguous)) {
    echo "Nama bahan_baku ambigu (dilewati): " . implode(', ', array_keys($ambiguous)) . "\n";
}
echo "\n";

if (count($unmatched) > 0) {
    echo "--- Daftar baris TIDAK cocok (review manual, TIDAK disentuh) ---\n";
    foreach ($unmatched as $u) {
        echo "  id={$u['id']}  tgl={$u['tgl']}  sku=\"{$u['sku']}\"\n";
    }
    echo "\n";
}

if (!$apply) {
    echo "Dry-run selesai. Tidak ada perubahan ditulis. Jalankan ulang dengan --apply untuk benar-benar backfill " . count($matched) . " baris di atas.\n";
    exit(0);
}

$stmt = $conn->prepare("UPDATE warehouse_ledger SET bahan_id = ? WHERE id = ? AND bahan_id IS NULL");
$okCount = 0;
foreach ($matched as $m) {
    $stmt->bind_param('ii', $m['bahan_id'], $m['id']);
    if ($stmt->execute()) $okCount++;
}

echo "Backfill selesai: $okCount dari " . count($matched) . " baris berhasil di-update.\n";
$conn->close();
