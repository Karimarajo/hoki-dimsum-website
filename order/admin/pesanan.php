<?php
$pageTitle = 'Pesanan';
$activeMenu = 'pesanan';
require __DIR__ . '/includes/admin-header.php';

$statusLabels = [
    'pending_payment' => 'Menunggu Bayar', 'paid' => 'Dibayar', 'preparing' => 'Disiapkan',
    'ready' => 'Siap', 'completed' => 'Selesai', 'cancelled' => 'Batal',
];

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (csrf_check()) {
        $orderId = (int)$_POST['order_id'];
        $newStatus = $_POST['status'];
        if (array_key_exists($newStatus, $statusLabels)) {
            $stmt = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $orderId]);
            flash('success', 'Status pesanan berhasil diperbarui.');

            // Begitu order ditandai 'paid', catat otomatis ke histori transaksi POS (idempotent di sisi POS).
            if ($newStatus === 'paid') {
                $posApiBase = $isDev
                    ? 'http://127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? 80) . '/api.php'
                    : 'https://pos-hokidimsum.com/api.php';
                $chSync = curl_init($posApiBase . '?action=sync_order_transaksi&order_id=' . $orderId);
                curl_setopt_array($chSync, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6]);
                curl_exec($chSync);
                curl_close($chSync);
            }
        }
    }
    redirect(BASE_URL . '/admin/pesanan.php' . (isset($_POST['order_id']) ? '?view=' . (int)$_POST['order_id'] : ''));
}

// Hapus pesanan (order_items ikut terhapus otomatis lewat ON DELETE CASCADE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order']) && csrf_check()) {
    $orderId = (int)$_POST['order_id'];
    db()->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
    flash('success', 'Pesanan berhasil dihapus.');
    redirect(BASE_URL . '/admin/pesanan.php');
}

$branches = db()->query("SELECT * FROM branches ORDER BY nama")->fetchAll();

$filterStatus = $_GET['status'] ?? '';
$filterBranch = (int)($_GET['branch'] ?? 0);
$filterDate = $_GET['date'] ?? '';

$where = [];
$params = [];
if ($filterStatus && array_key_exists($filterStatus, $statusLabels)) { $where[] = 'o.status = ?'; $params[] = $filterStatus; }
if ($filterBranch) { $where[] = 'o.branch_id = ?'; $params[] = $filterBranch; }
if ($filterDate) { $where[] = 'DATE(o.created_at) = ?'; $params[] = $filterDate; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare("SELECT o.*, b.nama AS branch_nama FROM orders o JOIN branches b ON b.id = o.branch_id $whereSql ORDER BY o.created_at DESC LIMIT 200");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$viewOrder = null;
$viewItems = [];
if (!empty($_GET['view'])) {
    $stmt = db()->prepare("SELECT o.*, b.nama AS branch_nama, b.wa_number AS branch_wa FROM orders o JOIN branches b ON b.id = o.branch_id WHERE o.id = ?");
    $stmt->execute([(int)$_GET['view']]);
    $viewOrder = $stmt->fetch();
    if ($viewOrder) {
        $itemStmt = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemStmt->execute([$viewOrder['id']]);
        $viewItems = $itemStmt->fetchAll();
    }
}
?>

<?php if ($viewOrder): ?>
<div class="panel">
  <div class="panel-head">
    <h3>Detail Order <?= e($viewOrder['order_code']) ?></h3>
    <a href="<?= BASE_URL ?>/admin/pesanan.php" class="btn btn-outline btn-sm">← Kembali ke Daftar</a>
  </div>
  <div class="panel-body">
    <div class="form-row cols-2">
      <div>
        <div class="summary-row"><span>Nama</span><strong><?= e($viewOrder['nama_customer']) ?></strong></div>
        <div class="summary-row"><span>No. WA</span><strong><?= e($viewOrder['no_wa']) ?></strong></div>
        <div class="summary-row"><span>Cabang</span><strong><?= e($viewOrder['branch_nama']) ?></strong></div>
        <div class="summary-row"><span>Metode Ambil</span><strong><?= $viewOrder['pickup_method'] === 'ojol' ? 'Diambil Ojol' : 'Ambil Sendiri' ?></strong></div>
        <div class="summary-row"><span>Jadwal</span><strong><?= date('d M Y', strtotime($viewOrder['pickup_date'])) ?>, <?= substr($viewOrder['pickup_time'], 0, 5) ?></strong></div>
        <?php if ($viewOrder['catatan']): ?><div class="summary-row"><span>Catatan</span><strong><?= e($viewOrder['catatan']) ?></strong></div><?php endif; ?>
      </div>
      <div>
        <h4 style="font-size:13.5px;">Item Pesanan</h4>
        <?php foreach ($viewItems as $it): ?>
        <div class="order-item-row"><span><?= e($it['nama_produk_snapshot']) ?> x<?= $it['qty'] ?></span><span><?= rupiah($it['harga_snapshot'] * $it['qty']) ?></span></div>
        <?php endforeach; ?>
        <div class="summary-row"><span>Subtotal</span><span><?= rupiah($viewOrder['subtotal']) ?></span></div>
        <div class="summary-row"><span>Kode Unik</span><span><?= (int)$viewOrder['kode_unik'] ?></span></div>
        <div class="summary-row total"><span>Total Bayar</span><span><?= rupiah($viewOrder['total_bayar']) ?></span></div>
      </div>
    </div>

    <form method="post" style="margin-top:16px; display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="order_id" value="<?= $viewOrder['id'] ?>">
      <div class="form-group mb-0" style="min-width:200px;">
        <label>Update Status</label>
        <select name="status" class="form-control">
          <?php foreach ($statusLabels as $key => $label): ?>
          <option value="<?= $key ?>" <?= $viewOrder['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" name="update_status" value="1" class="btn btn-primary">Simpan Status</button>
      <a href="<?= e(wa_link($viewOrder['branch_wa'] ?: get_setting('wa_pusat',''), 'Halo ' . $viewOrder['nama_customer'] . ', terkait order ' . $viewOrder['order_code'])) ?>" target="_blank" class="btn btn-wa">💬 Chat Customer</a>
    </form>

    <form method="post" data-confirm="Hapus pesanan <?= e($viewOrder['order_code']) ?> secara permanen? Tindakan ini tidak bisa dibatalkan." style="margin-top:10px;">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="order_id" value="<?= $viewOrder['id'] ?>">
      <button type="submit" name="delete_order" value="1" class="btn btn-outline" style="color:var(--red-600); border-color:var(--red-200,#f3c9c6);">🗑️ Hapus Pesanan Ini</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head"><h3>Semua Pesanan</h3></div>
  <div class="panel-body">
    <form method="get" class="filter-bar">
      <select name="status" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <?php foreach ($statusLabels as $key => $label): ?>
        <option value="<?= $key ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <select name="branch" onchange="this.form.submit()">
        <option value="">Semua Cabang</option>
        <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $filterBranch === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date" value="<?= e($filterDate) ?>" onchange="this.form.submit()">
      <?php if ($filterStatus || $filterBranch || $filterDate): ?><a href="<?= BASE_URL ?>/admin/pesanan.php" class="btn btn-outline btn-sm">Reset</a><?php endif; ?>
    </form>

    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Kode</th><th>Customer</th><th>Cabang</th><th>Total</th><th>Status</th><th>Waktu</th><th></th></tr></thead>
        <tbody>
          <?php if (!$orders): ?><tr><td colspan="7">Tidak ada pesanan sesuai filter.</td></tr><?php endif; ?>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= e($o['order_code']) ?></td>
            <td><?= e($o['nama_customer']) ?></td>
            <td><?= e($o['branch_nama']) ?></td>
            <td><?= rupiah($o['total_bayar']) ?></td>
            <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e($statusLabels[$o['status']] ?? $o['status']) ?></span></td>
            <td><?= date('d/m H:i', strtotime($o['created_at'])) ?></td>
            <td>
              <div class="table-actions">
                <a href="?view=<?= $o['id'] ?>" class="icon-btn edit" title="Lihat detail">👁️</a>
                <form method="post" data-confirm="Hapus pesanan <?= e($o['order_code']) ?> secara permanen? Tindakan ini tidak bisa dibatalkan.">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                  <button type="submit" name="delete_order" value="1" class="icon-btn danger" title="Hapus">🗑️</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
