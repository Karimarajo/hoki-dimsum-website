<?php
require_once __DIR__ . '/includes/cart.php';
$currentPage = 'cek-status.php';
$pageTitle = 'Cek Status Order — ' . APP_NAME;

$orderCode = trim($_GET['order'] ?? $_POST['order_code'] ?? '');
$order = null;
$notFound = false;

if ($orderCode !== '') {
    $stmt = db()->prepare("SELECT o.*, b.nama AS branch_nama, b.alamat AS branch_alamat FROM orders o JOIN branches b ON b.id = o.branch_id WHERE o.order_code = ?");
    $stmt->execute([$orderCode]);
    $order = $stmt->fetch();
    if (!$order) $notFound = true;
}

$statusLabels = [
    'pending_payment' => 'Menunggu Pembayaran',
    'paid' => 'Pembayaran Diterima',
    'preparing' => 'Sedang Disiapkan',
    'ready' => 'Siap Diambil/Dikirim',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];
$steps = ['pending_payment', 'paid', 'preparing', 'ready', 'completed'];

require __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <span class="eyebrow">Lacak Pesanan</span>
    <h1>Cek Status Order</h1>
    <p>Masukkan kode order kamu (contoh: HD-<?= date('Ymd') ?>-0001)</p>
  </div>
</div>

<div class="section" style="padding-top:0;">
  <div class="container">
    <div class="status-check-box">
      <form method="get">
        <div class="form-group">
          <label>Kode Order</label>
          <input type="text" name="order" class="form-control" placeholder="HD-20260718-0001" value="<?= e($orderCode) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Cek Status</button>
      </form>

      <?php if ($notFound): ?>
        <div class="alert alert-error" style="margin-top:20px;">Order dengan kode tersebut tidak ditemukan.</div>
      <?php elseif ($order): ?>
        <?php $pickupLabelStatus = $order['pickup_method'] === 'ojol' ? 'Dikirim Ojol (titik jemput driver)' : 'Ambil Sendiri'; ?>
        <div style="margin-top:24px;">
          <div class="summary-row"><span>Kode Order</span><strong><?= e($order['order_code']) ?></strong></div>
          <div class="summary-row"><span>Nama</span><strong><?= e($order['nama_customer']) ?></strong></div>
          <div class="summary-row"><span>Cabang</span><strong><?= e($order['branch_nama']) ?></strong></div>
          <div class="summary-row"><span>Total</span><strong><?= rupiah($order['total_bayar']) ?></strong></div>
          <div class="summary-row">
            <span>Status</span>
            <span class="status-pill status-<?= e($order['status']) ?>"><?= e($statusLabels[$order['status']] ?? $order['status']) ?></span>
          </div>

          <?php if ($order['status'] !== 'cancelled'): ?>
          <div class="panel-like" style="margin-top:14px;">
            <div class="order-item-row" style="border-bottom:none;">
              <span>📍 Titik Penjemputan</span><span><strong><?= e($order['branch_nama']) ?></strong></span>
            </div>
            <p class="form-hint mb-0" style="margin-top:4px;"><?= e($order['branch_alamat']) ?></p>
            <p class="form-hint mb-0" style="margin-top:6px;">🛵 <?= e($pickupLabelStatus) ?></p>
          </div>
          <?php endif; ?>

          <?php if ($order['status'] !== 'cancelled'): ?>
          <div class="order-timeline">
            <?php
            $currentIndex = array_search($order['status'], $steps, true);
            foreach ($steps as $i => $s):
                $cls = $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : '');
            ?>
            <div class="tl-step <?= $cls ?>">
              <div class="tl-dot"><?= $i < $currentIndex ? '✓' : ($i + 1) ?></div>
              <div>
                <div class="tl-title"><?= e($statusLabels[$s]) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="alert alert-error" style="margin-top:16px;">Pesanan ini telah dibatalkan.</div>
          <?php endif; ?>

          <?php if ($order['status'] === 'pending_payment'): ?>
          <a href="<?= BASE_URL ?>/pembayaran.php?order=<?= urlencode($order['order_code']) ?>" class="btn btn-gold btn-block" style="margin-top:20px;">Lanjut ke Pembayaran</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
