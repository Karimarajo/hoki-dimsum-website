<?php
/**
 * api.php — Backend Marajo (PT Marajo Barokah). Satu pintu, action-based,
 * mengikuti pola api.php di pos-hokidimsum.com.
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/db_main.php';

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: [];

// Semua action di bawah ini butuh login Marajo yang valid.
$currentUser = marajo_require_login_api();

$pdo = marajo_db();

ob_end_clean();

// ── HELPER ────────────────────────────────────────────────────────────

function latest_barang(PDO $pdo, string $nama): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM marajo_barang_masuk WHERE nama_barang = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$nama]);
    return $stmt->fetch() ?: null;
}

/**
 * Daftar nama barang dari Stock Gudang (Warehouse) sistem utama Hoki Dimsum —
 * jadi sumber Dropdown Nama Barang di Input Barang, dan juga acuan pencocokan
 * nama barang untuk Data Stock Barang. Dibaca langsung dari tabel
 * warehouse_ledger di database utama (read-only, tidak pernah ditulis dari sini).
 */
function warehouse_nama_list(): array
{
    $rows = main_db()->query('SELECT DISTINCT sku FROM warehouse_ledger ORDER BY sku ASC')->fetchAll();
    return array_column($rows, 'sku');
}

/**
 * Sisa stock semua item Stock Gudang saat ini (SUM masuk - SUM keluar per
 * sku) — persis sama caranya dengan action get_warehouse_stok_semua di
 * api.php pos-hokidimsum.com, supaya angkanya selalu identik dengan yang
 * ditampilkan di menu Warehouse / Bahan Baku.
 */
function warehouse_stock_map(): array
{
    $rows = main_db()->query("SELECT sku, COALESCE(SUM(masuk),0) - COALESCE(SUM(keluar),0) AS sisa
                               FROM warehouse_ledger GROUP BY sku")->fetchAll();
    $map = [];
    foreach ($rows as $r) $map[$r['sku']] = (float)$r['sisa'];
    return $map;
}

/**
 * History Penjualan dihitung langsung dari baris "keluar" di warehouse_ledger
 * (Riwayat Stock) untuk item yang ada di List Track Barang Marajo — bukan
 * dicatat manual. Profit & omset per baris pakai harga jual/modal satuan
 * terakhir dari List Track Barang untuk item tsb.
 */
function computed_penjualan(PDO $pdo): array
{
    $namaRows = $pdo->query('SELECT DISTINCT nama_barang FROM marajo_barang_masuk')->fetchAll();
    $names = array_column($namaRows, 'nama_barang');
    if (!$names) return [];

    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $stmt = main_db()->prepare("SELECT sku, tgl, keluar, catatan FROM warehouse_ledger
                                 WHERE sku IN ($placeholders) AND keluar > 0
                                 ORDER BY tgl DESC, id DESC");
    $stmt->execute($names);
    $rows = $stmt->fetchAll();

    $hargaCache = [];
    $result = [];
    foreach ($rows as $r) {
        $nama = $r['sku'];
        if (!array_key_exists($nama, $hargaCache)) {
            $lb = latest_barang($pdo, $nama);
            $hJual  = $lb ? (float)$lb['harga_jual_satuan'] : 0;
            $hModal = $lb ? (float)$lb['harga_modal_satuan'] : 0;
            $hargaCache[$nama] = ['jual' => $hJual, 'profit_satuan' => $hJual - $hModal];
        }
        $qty = (float)$r['keluar'];
        $result[] = [
            'nama_barang' => $nama,
            'qty'         => $qty,
            'omset'       => $qty * $hargaCache[$nama]['jual'],
            'profit'      => $qty * $hargaCache[$nama]['profit_satuan'],
            'keterangan'  => $r['catatan'] ?: 'Penjualan',
            'waktu'       => $r['tgl'],
        ];
    }
    return $result;
}

// Ambil angka dari input JSON secara aman (terima int/float/string berformat "1.234,56" atau "1234.56").
function n($v): float
{
    if (is_int($v) || is_float($v)) return (float)$v;
    $s = trim((string)($v ?? '0'));
    if ($s === '') return 0.0;
    // Hilangkan pemisah ribuan bila formatnya "1.234.567" (tanpa desimal koma)
    if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
        $s = str_replace('.', '', $s);
    }
    $s = str_replace(',', '.', $s);
    return (float)$s;
}

function respond(array $data): void
{
    echo json_encode($data);
    exit;
}

// ── ROUTER ────────────────────────────────────────────────────────────
switch ($action) {

    // ═══ DATA BARANG ═══════════════════════════════════════════════
    case 'barang_nama_list': {
        // Dropdown Nama Barang = daftar item Stock Gudang (Warehouse) sistem utama,
        // bukan nama bebas ketik — supaya nama barang di Marajo selalu nyambung
        // dengan data stock fisik yang sama.
        respond(['status' => 'success', 'data' => warehouse_nama_list()]);
    }

    case 'barang_masuk_list': {
        $rows = $pdo->query('SELECT * FROM marajo_barang_masuk ORDER BY id DESC')->fetchAll();
        respond(['status' => 'success', 'data' => $rows]);
    }

    case 'barang_masuk_save': {
        $id       = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : null;
        $nama     = trim($input['nama_barang'] ?? '');
        $hBarang  = n($input['harga_barang'] ?? 0);
        $qty      = (int)($input['qty'] ?? 0);
        $hJual    = n($input['harga_jual_satuan'] ?? 0);

        if ($nama === '' || $qty <= 0) {
            respond(['status' => 'error', 'message' => 'Nama barang dan Qty wajib diisi (Qty harus lebih dari 0).']);
        }
        if (!in_array($nama, warehouse_nama_list(), true)) {
            respond(['status' => 'error', 'message' => 'Nama barang harus dipilih dari daftar Stock Gudang.']);
        }

        // Harga Modal Satuan = Harga Barang ÷ Qty — dihitung di server (otoritatif),
        // bukan dipercaya dari input klien, walau di form sudah readonly & otomatis.
        $hModal = $qty > 0 ? round($hBarang / $qty, 2) : 0;

        // Profit di List Track Barang = per-satuan (Harga Jual - Harga Modal),
        // BUKAN dikali Qty — biar mencerminkan margin satuan barangnya.
        $profit = $hJual - $hModal;
        $margin = $hJual > 0 ? round(($profit / $hJual) * 100, 2) : 0;

        if ($id) {
            $stmt = $pdo->prepare('UPDATE marajo_barang_masuk SET nama_barang=?, harga_barang=?, qty=?, harga_modal_satuan=?, harga_jual_satuan=?, profit=?, margin=? WHERE id=?');
            $stmt->execute([$nama, $hBarang, $qty, $hModal, $hJual, $profit, $margin, $id]);
            respond(['status' => 'success', 'message' => 'Data barang diperbarui.']);
        } else {
            $stmt = $pdo->prepare('INSERT INTO marajo_barang_masuk (nama_barang, harga_barang, qty, harga_modal_satuan, harga_jual_satuan, profit, margin, created_by, created_at) VALUES (?,?,?,?,?,?,?,?, NOW())');
            $stmt->execute([$nama, $hBarang, $qty, $hModal, $hJual, $profit, $margin, $currentUser]);
            $newId = (int)$pdo->lastInsertId();
            respond(['status' => 'success', 'message' => 'Barang berhasil disimpan.', 'id' => $newId]);
        }
    }

    case 'barang_masuk_delete': {
        $id = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id FROM marajo_barang_masuk WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) respond(['status' => 'error', 'message' => 'Data tidak ditemukan.']);

        $pdo->prepare('DELETE FROM marajo_barang_masuk WHERE id = ?')->execute([$id]);
        respond(['status' => 'success', 'message' => 'Data barang dihapus.']);
    }

    // ═══ STOCK BARANG ══════════════════════════════════════════════
    // Stock Qty diambil dari Stock Gudang (Warehouse) sistem utama — nama
    // barang dari List Track Barang dicocokkan ke sana. Estimasi Harga &
    // Estimasi Profit dihitung dari harga modal/jual terakhir di List Track
    // Barang dikali Stock Qty tsb.
    case 'stock_list': {
        $namaRows = $pdo->query('SELECT DISTINCT nama_barang FROM marajo_barang_masuk ORDER BY nama_barang ASC')->fetchAll();
        $whStock  = warehouse_stock_map();
        $data = [];
        foreach ($namaRows as $r) {
            $nama  = $r['nama_barang'];
            $stock = $whStock[$nama] ?? 0;
            $lb    = latest_barang($pdo, $nama);
            $hJual = $lb ? (float)$lb['harga_jual_satuan'] : 0;
            $hModal = $lb ? (float)$lb['harga_modal_satuan'] : 0;
            $data[] = [
                'nama_barang'      => $nama,
                'stock_qty'        => $stock,
                'harga_jual_satuan'=> $hJual,
                'profit_satuan'    => $hJual - $hModal,
                'estimasi_harga'   => $stock * $hJual,
                'estimasi_profit'  => $stock * ($hJual - $hModal),
            ];
        }
        respond(['status' => 'success', 'data' => $data]);
    }

    // Riwayat Stock = cerminan langsung data Warehouse (warehouse_ledger) untuk
    // item tsb — bukan ledger internal Marajo lagi. Sisa dihitung kumulatif
    // persis seperti action get_warehouse_ledger di api.php pos-hokidimsum.com.
    case 'stock_riwayat': {
        $nama = trim($_GET['nama_barang'] ?? '');
        if ($nama === '') respond(['status' => 'error', 'message' => 'Nama barang wajib diisi.']);

        $stmt = main_db()->prepare("SELECT *, DATE_FORMAT(tgl, '%Y-%m-%d') AS tgl_fmt
                                     FROM warehouse_ledger
                                     WHERE sku = ?
                                     ORDER BY tgl ASC, created_at ASC, id ASC");
        $stmt->execute([$nama]);

        $out   = [];
        $saldo = 0;
        foreach ($stmt->fetchAll() as $row) {
            $masuk  = (float)$row['masuk'];
            $keluar = (float)$row['keluar'];
            $saldo  = $saldo + $masuk - $keluar;
            $out[] = [
                'waktu'      => $row['tgl_fmt'],
                'keterangan' => $row['catatan'] ?: ($masuk > 0 ? 'Input Manual' : 'Penjualan'),
                'masuk'      => $masuk,
                'keluar'     => $keluar,
                'sisa'       => $saldo,
            ];
        }
        respond(['status' => 'success', 'data' => array_reverse($out)]);
    }

    // ═══ HISTORY PENJUALAN ═════════════════════════════════════════
    // Diambil otomatis dari baris "keluar" di Riwayat Stock (Warehouse), bukan
    // dicatat manual — lihat computed_penjualan().
    case 'penjualan_list': {
        respond(['status' => 'success', 'data' => computed_penjualan($pdo)]);
    }

    // ═══ TRANSAKSI (Debit/Kredit) ══════════════════════════════════
    case 'transaksi_list': {
        $rows = $pdo->query('SELECT * FROM marajo_transaksi ORDER BY waktu DESC, id DESC')->fetchAll();
        respond(['status' => 'success', 'data' => $rows]);
    }

    case 'transaksi_save': {
        $id   = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : null;
        $ket  = trim($input['keterangan'] ?? '');
        $tipe = ($input['tipe'] ?? '') === 'kredit' ? 'kredit' : 'debit';
        $nom  = n($input['nominal'] ?? 0);

        if ($ket === '' || $nom <= 0) respond(['status' => 'error', 'message' => 'Keterangan dan Nominal wajib diisi.']);

        if ($id) {
            $stmt = $pdo->prepare('UPDATE marajo_transaksi SET keterangan=?, tipe=?, nominal=? WHERE id=?');
            $stmt->execute([$ket, $tipe, $nom, $id]);
            respond(['status' => 'success', 'message' => 'Transaksi diperbarui.']);
        } else {
            $stmt = $pdo->prepare('INSERT INTO marajo_transaksi (keterangan, tipe, nominal, created_by, waktu) VALUES (?,?,?,?, NOW())');
            $stmt->execute([$ket, $tipe, $nom, $currentUser]);
            respond(['status' => 'success', 'message' => 'Transaksi disimpan.']);
        }
    }

    case 'transaksi_delete': {
        $id = (int)($input['id'] ?? 0);
        $pdo->prepare('DELETE FROM marajo_transaksi WHERE id = ?')->execute([$id]);
        respond(['status' => 'success', 'message' => 'Transaksi dihapus.']);
    }

    // ═══ DASHBOARD ═════════════════════════════════════════════════
    // Debit = uang KELUAR, Kredit = uang MASUK (kebalikan istilah akuntansi
    // umum, tapi mengikuti definisi yang dipakai di Marajo).
    case 'dashboard_summary': {
        $saldoRow = $pdo->query("SELECT
                COALESCE(SUM(CASE WHEN tipe='debit'  THEN nominal ELSE 0 END),0) AS total_debit,
                COALESCE(SUM(CASE WHEN tipe='kredit' THEN nominal ELSE 0 END),0) AS total_kredit
            FROM marajo_transaksi")->fetch();
        $totalDebit  = (float)$saldoRow['total_debit'];   // uang keluar
        $totalKredit = (float)$saldoRow['total_kredit'];  // uang masuk

        $penjualan = computed_penjualan($pdo);
        $profitRealized = array_sum(array_column($penjualan, 'profit'));
        $totalOmsetPenjualan = array_sum(array_column($penjualan, 'omset'));

        // Saldo Total = Transaksi (Kredit - Debit) + nilai harga jual History Penjualan (omset)
        $saldoTotal = ($totalKredit - $totalDebit) + $totalOmsetPenjualan;

        $namaRows = $pdo->query('SELECT DISTINCT nama_barang FROM marajo_barang_masuk')->fetchAll();
        $whStock  = warehouse_stock_map();
        $totalEstimasiHarga  = 0;
        $totalEstimasiProfit = 0;
        $diagramStok = [];
        foreach ($namaRows as $r) {
            $nama  = $r['nama_barang'];
            $stock = $whStock[$nama] ?? 0;
            $lb    = latest_barang($pdo, $nama);
            $hJual = $lb ? (float)$lb['harga_jual_satuan'] : 0;
            $hModal= $lb ? (float)$lb['harga_modal_satuan'] : 0;
            $totalEstimasiHarga  += $stock * $hJual;
            $totalEstimasiProfit += $stock * ($hJual - $hModal);
            if ($stock > 0) $diagramStok[] = ['nama_barang' => $nama, 'stock_qty' => $stock];
        }
        usort($diagramStok, fn($a, $b) => $b['stock_qty'] <=> $a['stock_qty']);
        $diagramStok = array_slice($diagramStok, 0, 10);

        // Grafik Penjualan = total penjualan (omset) per hari, untuk bulan berjalan.
        $bulanIni = date('Y-m');
        $harian = [];
        foreach ($penjualan as $p) {
            if (substr($p['waktu'], 0, 7) !== $bulanIni) continue;
            $tgl = $p['waktu'];
            if (!isset($harian[$tgl])) $harian[$tgl] = ['tgl' => $tgl, 'omset' => 0, 'qty' => 0, 'profit' => 0];
            $harian[$tgl]['omset']  += $p['omset'];
            $harian[$tgl]['qty']    += $p['qty'];
            $harian[$tgl]['profit'] += $p['profit'];
        }
        ksort($harian);
        $penjualanHarian = array_values($harian);

        respond(['status' => 'success', 'data' => [
            'saldo'                      => $saldoTotal,
            'profit_realized'            => $profitRealized,
            // Estimasi Saldo Keseluruhan = total estimasi nilai jual seluruh stock.
            'estimasi_saldo_keseluruhan' => $totalEstimasiHarga,
            // Estimasi Profit = total profit kalau seluruh stock terjual.
            'estimasi_profit_keseluruhan'=> $totalEstimasiProfit,
            'total_estimasi_harga_stock' => $totalEstimasiHarga,
            'total_estimasi_profit_stock'=> $totalEstimasiProfit,
            'diagram_stok_barang'        => $diagramStok,
            'grafik_penjualan'           => $penjualanHarian,
            'bulan_label'                => $bulanIni,
        ]]);
    }

    default:
        respond(['status' => 'error', 'message' => "Action '$action' tidak dikenali."]);
}
