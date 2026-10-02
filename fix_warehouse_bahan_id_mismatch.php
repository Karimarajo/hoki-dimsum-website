<?php
/**
 * Script perbaikan data SATU KALI PAKAI - re-validasi baris warehouse_ledger yang bahan_id-nya
 * SUDAH TERISI (hasil migrate_backfill_warehouse_bahan_id.php versi lama yang buggy, atau
 * sumber lain), lalu reset balik ke NULL baris yang ternyata salah tautan menurut aturan baru:
 *
 *   1. sku (apa adanya, termasuk kurung) persis terdaftar di tabel `inventory` -> itu barang
 *      Stok Gudang/Logistik, BUKAN bahan_baku resep, walau kebetulan nama/kurungnya mirip
 *      (contoh nyata: "Plastik Frozen (Roll)" ke-link ke bahan_baku "Plastik Frozen" / satuan
 *      cm; "Sticker Bulat" & "Sticker Frozen" ke-link ke bahan_baku meski nama itu JUGA ada
 *      persis di inventory sbg barang logistik yg beda konteks).
 *   2. sku tidak cocok (persis, atau lewat pola "Nama (Satuan)" dgn satuan yg BENERAN milik
 *      bahan_baku itu) ke nama+satuan bahan_baku yang ditunjuk oleh bahan_id-nya.
 *
 * TIDAK PERNAH menghapus baris apa pun - cuma mengosongkan kolom bahan_id pada baris yang
 * salah. Baris itu otomatis balik fallback ke grouping sku-text ternormalisasi seperti
 * sebelum backfill terjadi - histori ledger & sisa stoknya tidak berubah/hilang.
 *
 * HANYA jalan lewat CLI, dan HANYA menyentuh DB dev lokal secara default:
 *   php fix_warehouse_bahan_id_mismatch.php                 -> dry-run, preview saja (dev)
 *   php fix_warehouse_bahan_id_mismatch.php --apply          -> benar2 reset ke NULL (dev)
 *   php fix_warehouse_bahan_id_mismatch.php --prod --apply   -> ke production (eksplisit)
 *
 * Hapus file ini setelah dijalankan ke production (ikuti kebiasaan project ini utk script
 * migrasi/perbaikan sekali-pakai, lihat riwayat commit).
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

echo "=== Perbaikan mismatch warehouse_ledger.bahan_id ===\n";
echo "Target DB : " . ($prod ? "PRODUCTION" : "DEV lokal") . "\n";
echo "Mode      : " . ($apply ? "APPLY (akan menulis perubahan)" : "DRY-RUN (preview saja, tidak ada perubahan)") . "\n\n";

$check = $conn->query("SHOW COLUMNS FROM warehouse_ledger LIKE 'bahan_id'");
if (!$check || $check->num_rows === 0) {
    die("Kolom bahan_id belum ada di warehouse_ledger.\n");
}

// Peta bahan_baku id -> [nama, satuan] ternormalisasi (lowercase+trim).
$bahanById = [];
$resBahan = $conn->query("SELECT id, nama, satuan FROM bahan_baku");
while ($row = $resBahan->fetch_assoc()) {
    $bahanById[(int)$row['id']] = [
        'nama'   => strtolower(trim($row['nama'])),
        'satuan' => strtolower(trim($row['satuan'] ?? '')),
    ];
}

// Set nama barang inventory (Stok Gudang/Logistik manual) - exact match ke ini SELALU
// membatalkan kecocokan ke bahan_baku apa pun, berapa pun bahan_id yang sekarang tertaut.
$inventoryNameSet = [];
$resInv = $conn->query("SELECT DISTINCT nama_barang FROM inventory");
while ($row = $resInv->fetch_assoc()) {
    $inventoryNameSet[strtolower(trim($row['nama_barang']))] = true;
}

$resLedger = $conn->query("SELECT id, sku, bahan_id FROM warehouse_ledger WHERE bahan_id IS NOT NULL ORDER BY id ASC");

$toReset    = [];
$validCount = 0;
while ($row = $resLedger->fetch_assoc()) {
    $sku      = trim($row['sku']);
    $skuLower = strtolower($sku);
    $bid      = (int)$row['bahan_id'];
    $reason   = null;

    if (isset($inventoryNameSet[$skuLower])) {
        $reason = 'nama cocok di tabel inventory';
    } elseif (!isset($bahanById[$bid])) {
        $reason = 'bahan_baku tujuan sudah tidak ada (id terhapus)';
    } else {
        $namaBahan   = $bahanById[$bid]['nama'];
        $satuanBahan = $bahanById[$bid]['satuan'];

        if ($skuLower === $namaBahan) {
            // Cocok persis tanpa kurung - valid, biarkan.
        } elseif (preg_match('/^(.*?)\s*\(([^)]*)\)\s*$/', $sku, $m)) {
            $before    = strtolower(trim($m[1]));
            $isiKurung = strtolower(trim($m[2]));
            if ($before === $namaBahan && $isiKurung === $satuanBahan) {
                // Format "Nama (Satuan)" dan keduanya cocok ke bahan_baku ini - valid.
            } else {
                $reason = 'satuan di kurung tidak cocok';
            }
        } else {
            $reason = 'sku tidak cocok dengan nama bahan_baku yang ditautkan';
        }
    }

    if ($reason !== null) {
        $toReset[] = ['id' => $row['id'], 'sku' => $sku, 'bahan_id_lama' => $bid, 'reason' => $reason];
    } else {
        $validCount++;
    }
}

echo "Baris bahan_id valid (dibiarkan)    : $validCount\n";
echo "Baris bahan_id SALAH (akan direset) : " . count($toReset) . "\n\n";

if (count($toReset) > 0) {
    echo "--- Daftar baris yang akan direset bahan_id-nya jadi NULL ---\n";
    foreach ($toReset as $r) {
        echo "  id={$r['id']}  sku=\"{$r['sku']}\"  bahan_id_lama={$r['bahan_id_lama']}  alasan: {$r['reason']}\n";
    }
    echo "\n";
}

if (!$apply) {
    echo "Dry-run selesai. Tidak ada perubahan ditulis. Jalankan ulang dengan --apply untuk benar-benar reset " . count($toReset) . " baris di atas ke bahan_id = NULL.\n";
    exit(0);
}

$stmt = $conn->prepare("UPDATE warehouse_ledger SET bahan_id = NULL WHERE id = ?");
$okCount = 0;
foreach ($toReset as $r) {
    $stmt->bind_param('i', $r['id']);
    if ($stmt->execute()) $okCount++;
}

echo "Reset selesai: $okCount dari " . count($toReset) . " baris berhasil direset ke bahan_id = NULL.\n";
$conn->close();
