<?php
$pageTitle = 'Cabang';
$activeMenu = 'cabang';
require __DIR__ . '/includes/admin-header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_branch']) && csrf_check()) {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $jam = trim($_POST['jam_operasional'] ?? '');
    $wa = trim($_POST['wa_number'] ?? '');
    $gofood = trim($_POST['gofood_link'] ?? '');
    $grabfood = trim($_POST['grabfood_link'] ?? '');
    $shopeefood = trim($_POST['shopeefood_link'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($nama === '' || $alamat === '') {
        flash('error', 'Nama dan alamat cabang wajib diisi.');
    } else {
        try {
            $qris = null;
            if (!empty($_FILES['qris_image']['name'])) {
                $qris = upload_image($_FILES['qris_image'], 'qris');
            }

            if ($id > 0) {
                if ($qris) {
                    db()->prepare('UPDATE branches SET nama=?, alamat=?, jam_operasional=?, wa_number=?, gofood_link=?, grabfood_link=?, shopeefood_link=?, qris_image=?, is_active=? WHERE id=?')
                        ->execute([$nama, $alamat, $jam, $wa ?: null, $gofood ?: null, $grabfood ?: null, $shopeefood ?: null, $qris, $isActive, $id]);
                } else {
                    db()->prepare('UPDATE branches SET nama=?, alamat=?, jam_operasional=?, wa_number=?, gofood_link=?, grabfood_link=?, shopeefood_link=?, is_active=? WHERE id=?')
                        ->execute([$nama, $alamat, $jam, $wa ?: null, $gofood ?: null, $grabfood ?: null, $shopeefood ?: null, $isActive, $id]);
                }
                flash('success', 'Cabang berhasil diperbarui.');
            } else {
                db()->prepare('INSERT INTO branches (nama, alamat, jam_operasional, wa_number, gofood_link, grabfood_link, shopeefood_link, qris_image, is_active) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$nama, $alamat, $jam, $wa ?: null, $gofood ?: null, $grabfood ?: null, $shopeefood ?: null, $qris, $isActive]);
                flash('success', 'Cabang berhasil ditambahkan.');
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect(BASE_URL . '/admin/cabang.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_branch']) && csrf_check()) {
    $id = (int)$_POST['id'];
    $stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE branch_id = ?');
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Cabang tidak bisa dihapus karena sudah memiliki riwayat pesanan. Nonaktifkan saja.');
    } else {
        db()->prepare('DELETE FROM branches WHERE id = ?')->execute([$id]);
        flash('success', 'Cabang berhasil dihapus.');
    }
    redirect(BASE_URL . '/admin/cabang.php');
}

$branches = db()->query('SELECT * FROM branches ORDER BY id DESC')->fetchAll();
$editBranch = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM branches WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editBranch = $stmt->fetch();
}
?>

<div class="panel">
  <div class="panel-head">
    <h3><?= $editBranch ? 'Edit Cabang' : 'Tambah Cabang Baru' ?></h3>
    <?php if ($editBranch): ?><a href="<?= BASE_URL ?>/admin/cabang.php" class="btn btn-outline btn-sm">Batal Edit</a><?php endif; ?>
  </div>
  <div class="panel-body">
    <form method="post" enctype="multipart/form-data" class="admin-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <?php if ($editBranch): ?><input type="hidden" name="id" value="<?= $editBranch['id'] ?>"><?php endif; ?>

      <div class="form-row cols-2">
        <div class="form-group">
          <label>Nama Cabang</label>
          <input type="text" name="nama" class="form-control" required value="<?= e($editBranch['nama'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Jam Operasional</label>
          <input type="text" name="jam_operasional" class="form-control" placeholder="09.00 - 21.00 WIB" value="<?= e($editBranch['jam_operasional'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Alamat</label>
        <textarea name="alamat" class="form-control" required><?= e($editBranch['alamat'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label>Nomor WA Khusus Cabang (opsional, kosongkan untuk pakai WA pusat)</label>
        <input type="text" name="wa_number" class="form-control" placeholder="6281234567890" value="<?= e($editBranch['wa_number'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>QRIS Khusus Cabang (opsional, kosongkan untuk pakai QRIS pusat)</label>
        <input type="file" name="qris_image" accept="image/png,image/jpeg,image/webp" class="form-control" data-image-input="qrisBranchPreview">
      </div>
      <div class="image-preview" id="qrisBranchPreview">
        <?php if (!empty($editBranch['qris_image'])): ?><img src="<?= UPLOAD_URL . '/' . e($editBranch['qris_image']) ?>" alt=""><?php else: ?>Belum ada QRIS khusus<?php endif; ?>
      </div>

      <div class="form-row cols-2">
        <div class="form-group">
          <label>Link GoFood</label>
          <input type="url" name="gofood_link" class="form-control" value="<?= e($editBranch['gofood_link'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Link GrabFood</label>
          <input type="url" name="grabfood_link" class="form-control" value="<?= e($editBranch['grabfood_link'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Link ShopeeFood</label>
        <input type="url" name="shopeefood_link" class="form-control" value="<?= e($editBranch['shopeefood_link'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label><input type="checkbox" name="is_active" <?= ($editBranch['is_active'] ?? 1) ? 'checked' : '' ?>> Cabang aktif (tampil di website)</label>
      </div>

      <button type="submit" name="save_branch" value="1" class="btn btn-primary"><?= $editBranch ? 'Simpan Perubahan' : 'Tambah Cabang' ?></button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Daftar Cabang (<?= count($branches) ?>)</h3></div>
  <div class="panel-body table-wrap">
    <table class="data-table">
      <thead><tr><th>Nama</th><th>Alamat</th><th>QRIS</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$branches): ?><tr><td colspan="5">Belum ada cabang.</td></tr><?php endif; ?>
        <?php foreach ($branches as $b): ?>
        <tr>
          <td><?= e($b['nama']) ?></td>
          <td><?= e($b['alamat']) ?></td>
          <td><span class="status-pill <?= !empty($b['qris_image']) ? 'status-ready' : 'status-pending_payment' ?>"><?= !empty($b['qris_image']) ? 'Ada' : 'Pakai Pusat' ?></span></td>
          <td><span class="status-pill <?= $b['is_active'] ? 'status-ready' : 'status-cancelled' ?>"><?= $b['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
          <td>
            <div class="table-actions">
              <a href="?edit=<?= $b['id'] ?>" class="icon-btn edit" title="Edit">✏️</a>
              <form method="post" data-confirm="Hapus cabang '<?= e($b['nama']) ?>'?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                <button type="submit" name="delete_branch" value="1" class="icon-btn danger" title="Hapus">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
