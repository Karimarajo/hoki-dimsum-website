<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin_login();
$admin = current_admin();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> — Admin <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= BASE_URL ?>/assets/2.png">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<div class="admin-shell">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="brand"><span class="logo-mark"><img src="<?= BASE_URL ?>/assets/2.png" alt="<?= e(APP_NAME) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span><span><?= e(APP_NAME) ?></span></div>
    <nav class="admin-nav">
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= $activeMenu === 'dashboard' ? 'active' : '' ?>"><span class="ic">📊</span> Dashboard</a>
      <a href="<?= BASE_URL ?>/admin/pesanan.php" class="<?= $activeMenu === 'pesanan' ? 'active' : '' ?>"><span class="ic">🧾</span> Pesanan</a>
      <a href="<?= BASE_URL ?>/admin/produk.php" class="<?= $activeMenu === 'produk' ? 'active' : '' ?>"><span class="ic">🥟</span> Produk</a>
      <a href="<?= BASE_URL ?>/admin/cabang.php" class="<?= $activeMenu === 'cabang' ? 'active' : '' ?>"><span class="ic">📍</span> Cabang</a>
      <a href="<?= BASE_URL ?>/admin/promo.php" class="<?= $activeMenu === 'promo' ? 'active' : '' ?>"><span class="ic">🏷️</span> Promo</a>
      <a href="<?= BASE_URL ?>/admin/artikel.php" class="<?= $activeMenu === 'artikel' ? 'active' : '' ?>"><span class="ic">📰</span> Artikel</a>
      <div class="admin-nav-group">
        <div class="admin-nav-label">Pengaturan</div>
        <a href="<?= BASE_URL ?>/admin/setting.php" class="<?= $activeMenu === 'setting' ? 'active' : '' ?>"><span class="ic">⚙️</span> Setting</a>
        <a href="<?= BASE_URL ?>/index.php" target="_blank"><span class="ic">🌐</span> Lihat Website</a>
        <a href="<?= BASE_URL ?>/admin/logout.php"><span class="ic">🚪</span> Logout</a>
      </div>
    </nav>
  </aside>

  <div class="admin-main">
    <div class="admin-topbar">
      <div style="display:flex; align-items:center; gap:12px;">
        <button class="sidebar-toggle" id="sidebarToggle">☰</button>
        <span class="page-title"><?= e($pageTitle) ?></span>
      </div>
      <div class="admin-user-chip">
        <div class="avatar"><?= e(mb_strtoupper(mb_substr($admin['nama'] ?? $admin['username'] ?? 'A', 0, 1))) ?></div>
        <span><?= e($admin['nama'] ?? $admin['username'] ?? '') ?></span>
      </div>
    </div>
    <div class="admin-content">
      <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
