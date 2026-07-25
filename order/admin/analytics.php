<?php
$pageTitle = 'Analytics';
$activeMenu = 'analytics';
require __DIR__ . '/includes/admin-header.php';

$dateMulai = $_GET['date_mulai'] ?? date('Y-m-d', strtotime('-6 days'));
$dateSelesai = $_GET['date_selesai'] ?? date('Y-m-d');

$paramsRange = [$dateMulai . ' 00:00:00', $dateSelesai . ' 23:59:59'];

$stmtPageview = db()->prepare('SELECT COUNT(*) FROM page_visits WHERE visited_at BETWEEN ? AND ?');
$stmtPageview->execute($paramsRange);
$totalPageview = (int)$stmtPageview->fetchColumn();

$stmtVisitor = db()->prepare('SELECT COUNT(DISTINCT visitor_id) FROM page_visits WHERE visited_at BETWEEN ? AND ?');
$stmtVisitor->execute($paramsRange);
$totalVisitor = (int)$stmtVisitor->fetchColumn();

$stmtTopPages = db()->prepare('SELECT path, COUNT(*) AS jumlah FROM page_visits WHERE visited_at BETWEEN ? AND ? GROUP BY path ORDER BY jumlah DESC LIMIT 15');
$stmtTopPages->execute($paramsRange);
$topPages = $stmtTopPages->fetchAll();

$stmtDevice = db()->prepare('SELECT device_type, COUNT(*) AS jumlah FROM page_visits WHERE visited_at BETWEEN ? AND ? GROUP BY device_type ORDER BY jumlah DESC');
$stmtDevice->execute($paramsRange);
$deviceBreakdown = $stmtDevice->fetchAll();

$stmtBrowser = db()->prepare('SELECT browser, COUNT(*) AS jumlah FROM page_visits WHERE visited_at BETWEEN ? AND ? GROUP BY browser ORDER BY jumlah DESC');
$stmtBrowser->execute($paramsRange);
$browserBreakdown = $stmtBrowser->fetchAll();

$stmtHarian = db()->prepare('SELECT DATE(visited_at) AS tgl, COUNT(*) AS pageview, COUNT(DISTINCT visitor_id) AS visitor
    FROM page_visits WHERE visited_at BETWEEN ? AND ? GROUP BY DATE(visited_at) ORDER BY tgl DESC');
$stmtHarian->execute($paramsRange);
$harianBreakdown = $stmtHarian->fetchAll();
?>

<div class="panel">
  <div class="panel-body">
    <form method="get" class="filter-bar">
      <input type="date" name="date_mulai" value="<?= e($dateMulai) ?>" onchange="this.form.submit()">
      <span>s/d</span>
      <input type="date" name="date_selesai" value="<?= e($dateSelesai) ?>" onchange="this.form.submit()">
      <a href="<?= BASE_URL ?>/admin/analytics.php" class="btn btn-outline btn-sm">Reset (7 Hari Terakhir)</a>
    </form>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="ic">👁️</div>
    <div class="val"><?= number_format($totalPageview, 0, ',', '.') ?></div>
    <div class="label">Total Pageview</div>
  </div>
  <div class="stat-card">
    <div class="ic">🧑‍🤝‍🧑</div>
    <div class="val"><?= number_format($totalVisitor, 0, ',', '.') ?></div>
    <div class="label">Unique Visitor</div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Halaman Paling Sering Dikunjungi</h3></div>
  <div class="panel-body table-wrap">
    <table class="data-table">
      <thead><tr><th>Halaman</th><th>Pageview</th></tr></thead>
      <tbody>
        <?php if (!$topPages): ?><tr><td colspan="2">Belum ada data di rentang tanggal ini.</td></tr><?php endif; ?>
        <?php foreach ($topPages as $p): ?>
        <tr>
          <td><?= e($p['path']) ?></td>
          <td><?= number_format($p['jumlah'], 0, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="form-row cols-2">
  <div class="panel">
    <div class="panel-head"><h3>Device</h3></div>
    <div class="panel-body table-wrap">
      <table class="data-table">
        <thead><tr><th>Tipe</th><th>Pageview</th></tr></thead>
        <tbody>
          <?php if (!$deviceBreakdown): ?><tr><td colspan="2">Belum ada data.</td></tr><?php endif; ?>
          <?php foreach ($deviceBreakdown as $d): ?>
          <tr><td><?= e($d['device_type']) ?></td><td><?= number_format($d['jumlah'], 0, ',', '.') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="panel">
    <div class="panel-head"><h3>Browser</h3></div>
    <div class="panel-body table-wrap">
      <table class="data-table">
        <thead><tr><th>Browser</th><th>Pageview</th></tr></thead>
        <tbody>
          <?php if (!$browserBreakdown): ?><tr><td colspan="2">Belum ada data.</td></tr><?php endif; ?>
          <?php foreach ($browserBreakdown as $b): ?>
          <tr><td><?= e($b['browser']) ?></td><td><?= number_format($b['jumlah'], 0, ',', '.') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Breakdown Harian</h3></div>
  <div class="panel-body table-wrap">
    <table class="data-table">
      <thead><tr><th>Tanggal</th><th>Pageview</th><th>Unique Visitor</th></tr></thead>
      <tbody>
        <?php if (!$harianBreakdown): ?><tr><td colspan="3">Belum ada data di rentang tanggal ini.</td></tr><?php endif; ?>
        <?php foreach ($harianBreakdown as $h): ?>
        <tr>
          <td><?= date('d M Y', strtotime($h['tgl'])) ?></td>
          <td><?= number_format($h['pageview'], 0, ',', '.') ?></td>
          <td><?= number_format($h['visitor'], 0, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel-body">
    <p class="form-hint mb-0">Pilih rentang tanggal lebih lebar (mis. 1 minggu atau 1 bulan) di filter atas untuk lihat breakdown mingguan/bulanan.</p>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
