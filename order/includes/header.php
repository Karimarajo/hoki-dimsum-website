<?php
require_once __DIR__ . '/cart.php';
$currentPage = $currentPage ?? basename($_SERVER['SCRIPT_NAME']);
$cartCount = cart_count();
$pageTitle = $pageTitle ?? APP_NAME . ' — Order Dimsum Online';
$pageDesc = $pageDesc ?? get_setting('meta_description', 'Order dimsum homemade online.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta name="theme-color" content="#c8372d">
<link rel="icon" href="<?= BASE_URL ?>/assets/2.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="container">
    <a href="<?= BASE_URL ?>/index.php" class="brand">
      <span class="logo-mark"><img src="<?= BASE_URL ?>/assets/2.png" alt="<?= e(APP_NAME) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
      <span><?= e(APP_NAME) ?></span>
    </a>

    <nav class="nav-desktop">
      <ul>
        <li><a href="<?= BASE_URL ?>/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Beranda</a></li>
        <li><a href="<?= BASE_URL ?>/menu.php" class="<?= $currentPage === 'menu.php' ? 'active' : '' ?>">Menu</a></li>
        <li><a href="<?= BASE_URL ?>/cabang.php" class="<?= $currentPage === 'cabang.php' ? 'active' : '' ?>">Cabang</a></li>
        <li><a href="<?= BASE_URL ?>/promo.php" class="<?= $currentPage === 'promo.php' ? 'active' : '' ?>">Promo</a></li>
        <li><a href="<?= BASE_URL ?>/blog.php" class="<?= $currentPage === 'blog.php' ? 'active' : '' ?>">Blog</a></li>
        <li><a href="<?= BASE_URL ?>/cek-status.php" class="<?= $currentPage === 'cek-status.php' ? 'active' : '' ?>">Cek Status</a></li>
      </ul>
    </nav>

    <div class="header-actions">
      <a href="<?= BASE_URL ?>/checkout.php" class="btn-icon cart-btn" aria-label="Keranjang">
        🛒
        <?php if ($cartCount > 0): ?><span class="cart-badge" id="cartBadge"><?= $cartCount ?></span><?php endif; ?>
      </a>
      <button class="menu-toggle" id="menuToggle" aria-label="Buka menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
    </div>
  </div>
</header>

<div class="mobile-drawer" id="mobileDrawer">
  <div class="overlay" id="drawerOverlay"></div>
  <div class="panel">
    <div class="panel-head">
      <span class="brand"><span class="logo-mark"><img src="<?= BASE_URL ?>/assets/2.png" alt="<?= e(APP_NAME) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span><?= e(APP_NAME) ?></span>
      <button class="menu-toggle" id="drawerClose" aria-label="Tutup menu">✕</button>
    </div>
    <nav>
      <ul>
        <li><a href="<?= BASE_URL ?>/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">🏠 Beranda</a></li>
        <li><a href="<?= BASE_URL ?>/menu.php" class="<?= $currentPage === 'menu.php' ? 'active' : '' ?>">🍥 Menu</a></li>
        <li><a href="<?= BASE_URL ?>/cabang.php" class="<?= $currentPage === 'cabang.php' ? 'active' : '' ?>">📍 Cabang</a></li>
        <li><a href="<?= BASE_URL ?>/promo.php" class="<?= $currentPage === 'promo.php' ? 'active' : '' ?>">🏷️ Promo</a></li>
        <li><a href="<?= BASE_URL ?>/blog.php" class="<?= $currentPage === 'blog.php' ? 'active' : '' ?>">📰 Blog</a></li>
        <li><a href="<?= BASE_URL ?>/cek-status.php" class="<?= $currentPage === 'cek-status.php' ? 'active' : '' ?>">🔍 Cek Status Order</a></li>
      </ul>
    </nav>
    <div class="drawer-footer">
      <a href="<?= BASE_URL ?>/checkout.php" class="btn btn-primary btn-block">🛒 Keranjang (<?= $cartCount ?>)</a>
    </div>
  </div>
</div>
