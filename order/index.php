<?php
require_once __DIR__ . '/includes/cart.php';
$currentPage = 'index.php';
$pageTitle = APP_NAME . ' — ' . get_setting('tagline', 'Order Dimsum Online');

$promos = db()->query("SELECT * FROM promotions WHERE is_active = 1 AND (tanggal_selesai IS NULL OR tanggal_selesai >= CURDATE()) ORDER BY tanggal_mulai DESC LIMIT 3")->fetchAll();
$products = db()->query("SELECT * FROM products WHERE is_available = 1 ORDER BY RAND() LIMIT 8")->fetchAll();
$branches = db()->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY id LIMIT 2")->fetchAll();
$articles = db()->query("SELECT * FROM articles WHERE is_published = 1 ORDER BY published_at DESC LIMIT 2")->fetchAll();

$heroHeadline = get_setting('hero_headline', '');
$heroSubtext  = get_setting('hero_subtext', get_setting('tentang', ''));
$promoText    = get_setting('promo_text', '🔥 Fresh dikukus setiap hari');

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <div>
      <div class="hero-badge"><?= e($promoText) ?></div>
      <h1><?php if ($heroHeadline !== ''): ?><?= e($heroHeadline) ?><?php else: ?>Dimsum Homemade,<br><span>Hoki</span> Setiap Gigitan<?php endif; ?></h1>
      <p class="lead"><?= e($heroSubtext) ?></p>
      <div class="hero-actions">
        <a href="<?= BASE_URL ?>/menu.php" class="btn btn-primary">🥟 Lihat Menu &amp; Order</a>
        <a href="<?= BASE_URL ?>/cabang.php" class="btn btn-outline">📍 Cari Cabang Terdekat</a>
      </div>
      <div class="hero-stats">
        <div class="stat"><b>9+</b><span>Menu Dimsum</span></div>
        <div class="stat"><b><?= count($branches) > 0 ? count($branches) . '+' : '2+' ?></b><span>Cabang Aktif</span></div>
        <div class="stat"><b>100%</b><span>Homemade Fresh</span></div>
      </div>
    </div>
    <div class="hero-visual">
      <span class="steam-icon">🥟💨</span>
    </div>
  </div>
</section>

<?php if ($promos): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Promo Spesial</span>
      <h2>Lagi Hoki, Ada Promo!</h2>
      <p>Jangan sampai kelewatan, promo terbatas waktu.</p>
    </div>
    <div class="grid">
      <?php foreach ($promos as $p): ?>
      <div class="card promo-card article-card" style="grid-column: span 2;">
        <div class="promo-thumb" style="width:96px; height:96px; flex:none; border-radius:12px;">
          <?php if ($p['gambar']): ?><img src="<?= UPLOAD_URL . '/' . e($p['gambar']) ?>" alt="<?= e($p['judul']) ?>"><?php else: ?>🏷️<?php endif; ?>
        </div>
        <div class="promo-body" style="padding:0;">
          <div class="promo-dates"><?= date('d M', strtotime($p['tanggal_mulai'])) ?> - <?= $p['tanggal_selesai'] ? date('d M Y', strtotime($p['tanggal_selesai'])) : 'Selesai' ?></div>
          <h3><?= e($p['judul']) ?></h3>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:20px;"><a href="<?= BASE_URL ?>/promo.php" class="btn btn-outline btn-sm">Lihat Semua Promo →</a></div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="background:#fff;">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Menu Favorit</span>
      <h2>Pilihan Dimsum Kami</h2>
      <p>Dibuat fresh setiap hari, tanpa bahan pengawet.</p>
    </div>
    <div class="grid">
      <?php foreach ($products as $p): ?>
        <?php include __DIR__ . '/includes/partials/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:20px;"><a href="<?= BASE_URL ?>/menu.php" class="btn btn-primary btn-sm">Lihat Semua Menu →</a></div>
  </div>
</section>

<?php if ($branches): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Cabang Kami</span>
      <h2>Order Dekat dari Lokasimu</h2>
    </div>
    <div class="grid">
      <?php foreach ($branches as $b): ?>
      <div class="card branch-card" style="grid-column: span 2;">
        <h3><?= e($b['nama']) ?></h3>
        <div class="branch-meta"><span class="ic">📍</span><span><?= e($b['alamat']) ?></span></div>
        <div class="branch-meta"><span class="ic">🕒</span><span><?= e($b['jam_operasional']) ?></span></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:20px;"><a href="<?= BASE_URL ?>/cabang.php" class="btn btn-outline btn-sm">Lihat Semua Cabang →</a></div>
  </div>
</section>
<?php endif; ?>

<?php if ($articles): ?>
<section class="section" style="background:#fff;">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Blog</span>
      <h2>Cerita &amp; Tips Seputar Dimsum</h2>
    </div>
    <div class="grid">
      <?php foreach ($articles as $a): ?>
      <a href="<?= BASE_URL ?>/blog-detail.php?slug=<?= urlencode($a['slug']) ?>" class="card article-card">
        <div class="article-thumb">
          <?php if ($a['gambar_cover']): ?><img src="<?= UPLOAD_URL . '/' . e($a['gambar_cover']) ?>" alt=""><?php else: ?>📰<?php endif; ?>
        </div>
        <div class="article-body">
          <div class="date"><?= date('d M Y', strtotime($a['published_at'])) ?></div>
          <h3><?= e($a['judul']) ?></h3>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:20px;"><a href="<?= BASE_URL ?>/blog.php" class="btn btn-outline btn-sm">Baca Artikel Lainnya →</a></div>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/partials/sticky-cartbar.php'; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
