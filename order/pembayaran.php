<?php
require_once __DIR__ . '/includes/cart.php';
$currentPage = 'pembayaran.php';

$orderCode = $_GET['order'] ?? '';
$stmt = db()->prepare("SELECT o.*, b.nama AS branch_nama, b.alamat AS branch_alamat, b.wa_number AS branch_wa, b.qris_image AS branch_qris FROM orders o JOIN branches b ON b.id = o.branch_id WHERE o.order_code = ?");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order Tidak Ditemukan — ' . APP_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<div class="section container text-center"><h1>Order tidak ditemukan</h1><a href="' . BASE_URL . '/menu.php" class="btn btn-primary">Kembali ke Menu</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = 'Pembayaran ' . $order['order_code'] . ' — ' . APP_NAME;

$waTarget = $order['branch_wa'] ?: get_setting('wa_pusat', '');
$pesan = "Halo, saya konfirmasi pembayaran.\n"
    . "Order: {$order['order_code']}\n"
    . "Nama: {$order['nama_customer']}\n"
    . "Total: " . rupiah($order['total_bayar']) . "\n"
    . "Cabang: {$order['branch_nama']}\n"
    . "Mohon dicek ya, terima kasih.";
$waLink = wa_link($waTarget, $pesan);

$qrisImage = $order['branch_qris'] ?? '';
$pickupLabel = $order['pickup_method'] === 'ojol' ? 'Dikirim Ojol (titik jemput driver)' : 'Ambil Sendiri';

require __DIR__ . '/includes/header.php';
?>

<div class="section">
  <div class="container" style="max-width:520px;">
    <div class="pay-code">
      <span class="eyebrow">Kode Order</span>
      <div class="code"><?= e($order['order_code']) ?></div>
    </div>

    <div class="total-highlight">
      <div class="note">Total yang harus dibayar</div>
      <div class="amount"><?= rupiah($order['total_bayar']) ?></div>
      <div class="note">
        Subtotal <?= rupiah($order['subtotal']) ?> + kode unik <?= (int)$order['kode_unik'] ?>
        <?php if (!empty($order['diskon']) && $order['diskon'] > 0): ?>
          − diskon kupon <?= e($order['kupon_kode']) ?> <?= rupiah($order['diskon']) ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="unique-code-hint">
      ⚠️ Transfer <strong>PAS</strong> sesuai nominal di atas ya, termasuk 3 digit terakhir (kode unik). Ini membantu kami mengecek pembayaranmu lebih cepat!
    </div>

    <div class="panel-like" style="margin-bottom:20px;">
      <div class="order-item-row" style="border-bottom:none;">
        <span>📍 Titik Penjemputan</span><span><strong><?= e($order['branch_nama']) ?></strong></span>
      </div>
      <p class="form-hint mb-0" style="margin-top:4px;"><?= e($order['branch_alamat']) ?></p>
      <p class="form-hint mb-0" style="margin-top:6px;">🛵 <?= e($pickupLabel) ?></p>
    </div>

    <?php if ($qrisImage): ?>
    <div class="qris-box">
      <img src="<?= UPLOAD_URL . '/' . e($qrisImage) ?>" alt="QRIS <?= e($order['branch_nama']) ?>">
      <p class="form-hint mb-0">Scan QRIS di atas menggunakan aplikasi e-wallet atau m-banking favoritmu.</p>
    </div>
    <?php else: ?>
    <div class="alert alert-error">QRIS untuk cabang <?= e($order['branch_nama']) ?> belum tersedia. Hubungi kami via WhatsApp di bawah untuk info pembayaran.</div>
    <?php endif; ?>

    <a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="btn btn-wa btn-block" style="margin-bottom:12px;">
      💬 Konfirmasi Pembayaran via WhatsApp
    </a>
    <a href="<?= BASE_URL ?>/cek-status.php?order=<?= urlencode($order['order_code']) ?>" class="btn btn-outline btn-block">
      🔍 Cek Status Order Ini
    </a>

    <div class="panel-like" style="margin-top:28px;">
      <h3 style="font-size:14px; margin-bottom:10px;">Ringkasan Pesanan</h3>
      <?php
      $itemStmt = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
      $itemStmt->execute([$order['id']]);
      foreach ($itemStmt->fetchAll() as $it):
      ?>
      <div class="order-item-row">
        <span><?= e($it['nama_produk_snapshot']) ?> x<?= $it['qty'] ?></span>
        <span><?= rupiah($it['harga_snapshot'] * $it['qty']) ?></span>
      </div>
      <?php endforeach; ?>
      <div class="order-item-row" style="border-bottom:none;">
        <span>Cabang</span><span><?= e($order['branch_nama']) ?></span>
      </div>
      <div class="order-item-row" style="border-bottom:none;">
        <span>Ambil</span><span><?= date('d M Y', strtotime($order['pickup_date'])) ?>, <?= substr($order['pickup_time'], 0, 5) ?></span>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
