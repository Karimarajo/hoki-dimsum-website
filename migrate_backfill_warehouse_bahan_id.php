<?php
/**
 * Script migrasi SATU KALI PAKAI - backfill warehouse_ledger.bahan_id pada baris lama
 * yang masih NULL, dengan mencocokkan teks sku ke bahan_baku.nama+satuan.
 *
 * Aturan matching (lihat insiden: ledger "Plastik Frozen (Roll)" milik barang Stok Gudang/
 * inventory sempat ke-backfill salah ke bahan_baku "Plastik Frozen" / satuan "cm", krn versi
 * lama script ini asal strip APA PUN di dalam kurung lalu match by nama doang):
 *   1. Kalau sku (APA ADANYA, kurung-nya pun ikut) persis terdaftar di tabel `inventory`,
 *      JANGAN PERNAH dicocokkan ke bahan_baku manapun - itu barang Stok Gudang, bukan bahan
 *      baku resep, meski kebetulan nama/kurungnya mirip (mis. "Sticker Bulat" ada identik
 *      persis di bahan_baku DAN inventory sbg dua barang yg beda konteks).
 *   2. sku dianggap cocok ke sebuah bahan_baku kalau PERSIS sama persis (tanpa kurung), ATAU
 *      berformat "Nama (Satuan)" dgn Nama & Satuan-nya SAMA-SAMA cocok ke bahan_baku yg
 *      sama (bukan cuma nama-nya doang, kurungnya pun harus benar2 satuan bahan itu).
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

// Peta nama bahan_baku ternormalisasi -> ['id'=>, 'satuan'=>] (nama & satuan lowercase+trim).
// Nama yang ternormalisasi sama tapi beda id (tabrakan) ditandai ambigu dan DILEWATI
// sepenuhnya demi keamanan (tidak pernah ditebak).
$bahanInfo = [];
$ambiguous = [];
$resBahan = $conn->query("SELECT id, nama, satuan FROM bahan_baku");
while ($row = $resBahan->fetch_assoc()) {
    $norm = strtolower(trim($row['nama']));
    if ($norm === '') continue;
    if (isset($bahanInfo[$norm]) && $bahanInfo[$norm]['id'] !== (int)$row['id']) {
        $ambiguous[$norm] = true;
    }
    $bahanInfo[$norm] = ['id' => (int)$row['id'], 'satuan' => strtolower(trim($row['satuan'] ?? ''))];
}

// Set nama barang inventory (Stok Gudang/Logistik manual) - exact match ke ini SELALU
// mengalahkan kecocokan ke bahan_baku apa pun.
$inventoryNameSet = [];
$resInv = $conn->query("SELECT DISTINCT nama_barang FROM inventory");
while ($row = $resInv->fetch_assoc()) {
    $inventoryNameSet[strtolower(trim($row['nama_barang']))] = true;
}

$resLedger = $conn->query("SELECT id, tgl, sku FROM warehouse_ledger WHERE bahan_id IS NULL ORDER BY id ASC");

$matched   = [];
$unmatched = [];
while ($row = $resLedger->fetch_assoc()) {
    $sku      = trim($row['sku']);
    $skuLower = strtolower($sku);
    $bahanId  = null;

    if ($skuLower !== '' && !isset($inventoryNameSet[$skuLower])) {
        if (isset($bahanInfo[$skuLower]) && !isset($ambiguous[$skuLower])) {
            // Cocok persis tanpa kurung.
            $bahanId = $bahanInfo[$skuLower]['id'];
        } elseif (preg_match('/^(.*?)\s*\(([^)]*)\)\s*$/', $sku, $m)) {
            $before    = strtolower(trim($m[1]));
            $isiKurung = strtolower(trim($m[2]));
            if (isset($bahanInfo[$before]) && !isset($ambiguous[$before]) && $bahanInfo[$before]['satuan'] === $isiKurung) {
                // Format "Nama (Satuan)" dan satuannya benar2 cocok ke bahan_baku ini.
                $bahanId = $bahanInfo[$before]['id'];
            }
        }
    }

    if ($bahanId !== null) {
        $matched[] = ['id' => $row['id'], 'tgl' => $row['tgl'], 'sku' => $row['sku'], 'bahan_id' => $bahanId];
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
