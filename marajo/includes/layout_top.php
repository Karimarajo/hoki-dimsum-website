<?php
/**
 * layout_top.php — Kepala halaman + sidebar, dipakai semua halaman Marajo.
 * Set $pageTitle, $pageIcon, $activePage SEBELUM require file ini.
 */

require_once __DIR__ . '/auth.php';
$marajoUser = marajo_require_login();

$navItems = [
    ['dashboard.php',         '📊', 'Dashboard'],
    ['transaksi.php',         '💵', 'Transaksi'],
    ['data_barang.php',       '📦', 'Data Barang'],
    ['stock_barang.php',      '🏬', 'Stock Barang'],
    ['history_penjualan.php', '🧾', 'History Penjualan'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Marajo · <?= htmlspecialchars($pageTitle ?? '') ?></title>
<link rel="icon" type="image/png" href="assets/favicon-32.png?v=<?= (int)@filemtime(__DIR__ . '/../assets/favicon-32.png') ?>">
<link rel="apple-touch-icon" href="assets/logo-128.png?v=<?= (int)@filemtime(__DIR__ . '/../assets/logo-128.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/style.css') ?>">
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand" onclick="toggleSidebar()">
        <div class="sidebar-logo">
            <img src="assets/logo-128.png?v=<?= (int)@filemtime(__DIR__ . '/../assets/logo-128.png') ?>" alt="PT Marajo Barokah">
        </div>
        <div class="sidebar-brand-text">
            <div class="sidebar-brand-name">PT Marajo Barokah</div>
            <div class="sidebar-brand-sub">Inventory &amp; Keuangan</div>
        </div>
    </div>
    <button class="sidebar-toggle" onclick="toggleSidebar()">‹</button>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as [$link, $icon, $label]): ?>
        <a class="nav-item <?= ($activePage ?? '') === $link ? 'active' : '' ?>" href="<?= $link ?>">
            <span class="nav-icon"><?= $icon ?></span><span class="nav-label"><?= htmlspecialchars($label) ?></span>
        </a>
        <?php endforeach; ?>
        <div class="nav-divider"></div>
        <a class="nav-item" href="<?= htmlspecialchars(MAIN_SITE_URL) ?>/dashboard.html">
            <span class="nav-icon">↩️</span><span class="nav-label">Kembali ke Hoki POS</span>
        </a>
    </nav>
    <div class="sidebar-user">
        <div class="user-card">
            <div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($marajoUser, 0, 1))) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($marajoUser) ?></div>
                <div class="user-role">Executive</div>
            </div>
            <a class="btn-logout-icon" href="logout.php" title="Logout">🚪</a>
        </div>
    </div>
</aside>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="header-left">
            <div class="header-page-title">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>
                <?= $pageIcon ?? '' ?> <?= htmlspecialchars($pageTitle ?? '') ?>
            </div>
        </div>
    </header>
    <div class="page-body">
