<?php
require_once __DIR__ . '/includes/cart.php';
$currentPage = 'menu.php';
$pageTitle = 'Menu — ' . APP_NAME;

$categories = db()->query("SELECT * FROM product_categories ORDER BY urutan ASC, id ASC")->fetchAll();
$products = db()->query("
    SELECT p.*, c.nama AS category_nama
    FROM products p
    LEFT JOIN product_categories c ON c.id = p.category_id
    ORDER BY p.urutan ASC, p.id ASC
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <span class="eyebrow">Menu Kami</span>
    <h1>Semua Dimsum Hoki</h1>
    <p>Pilih menu favoritmu, tambahkan ke keranjang, lalu checkout.</p>
  </div>
</div>

<div class="section" style="padding-top:0;">
  <div class="container">
    <div class="chip-row">
      <button class="chip active" data-filter-chip="all">Semua</button>
      <?php foreach ($categories as $c): ?>
        <button class="chip" data-filter-chip="<?= (int)$c['id'] ?>"><?= e($c['nama']) ?></button>
      <?php endforeach; ?>
    </div>

    <?php if (!$products): ?>
      <div class="empty-state"><div class="ic">🥟</div><p>Belum ada produk tersedia saat ini.</p></div>
    <?php else: ?>
    <div class="grid">
      <?php foreach ($products as $p): ?>
        <?php include __DIR__ . '/includes/partials/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/partials/sticky-cartbar.php'; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
