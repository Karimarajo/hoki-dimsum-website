<?php
$pageTitle = 'Kategori';
$activeMenu = 'kategori';
require __DIR__ . '/includes/admin-header.php';

// ---- Handle add category ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category']) && csrf_check()) {
    $nama = trim($_POST['nama'] ?? '');
    if ($nama !== '') {
        $nextUrutan = (int)db()->query('SELECT COALESCE(MAX(urutan), 0) FROM product_categories')->fetchColumn() + 1;
        db()->prepare('INSERT INTO product_categories (nama, urutan) VALUES (?, ?)')->execute([$nama, $nextUrutan]);
        flash('success', 'Kategori berhasil ditambahkan.');
    }
    redirect(BASE_URL . '/admin/kategori.php');
}

// ---- Handle rename ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_category']) && csrf_check()) {
    $id = (int)$_POST['id'];
    $nama = trim($_POST['nama'] ?? '');
    if ($nama !== '') {
        db()->prepare('UPDATE product_categories SET nama = ? WHERE id = ?')->execute([$nama, $id]);
        flash('success', 'Kategori berhasil diperbarui.');
    }
    redirect(BASE_URL . '/admin/kategori.php');
}

// ---- Handle move (ubah urutan tampil chip filter di menu.php) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_category']) && csrf_check()) {
    $id = (int)$_POST['id'];
    $dir = $_POST['move_category'] === 'up' ? 'up' : 'down';
    $current = db()->prepare('SELECT id, urutan FROM product_categories WHERE id = ?');
    $current->execute([$id]);
    $curRow = $current->fetch();
    if ($curRow) {
        $neighborStmt = $dir === 'up'
            ? db()->prepare('SELECT id, urutan FROM product_categories WHERE urutan < ? ORDER BY urutan DESC LIMIT 1')
            : db()->prepare('SELECT id, urutan FROM product_categories WHERE urutan > ? ORDER BY urutan ASC LIMIT 1');
        $neighborStmt->execute([$curRow['urutan']]);
        $neighbor = $neighborStmt->fetch();
        if ($neighbor) {
            db()->prepare('UPDATE product_categories SET urutan = ? WHERE id = ?')->execute([$neighbor['urutan'], $curRow['id']]);
            db()->prepare('UPDATE product_categories SET urutan = ? WHERE id = ?')->execute([$curRow['urutan'], $neighbor['id']]);
        }
    }
    redirect(BASE_URL . '/admin/kategori.php');
}

// ---- Handle delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category']) && csrf_check()) {
    $id = (int)$_POST['id'];
    $productCountStmt = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
    $productCountStmt->execute([$id]);
    if ((int)$productCountStmt->fetchColumn() > 0) {
        flash('error', 'Kategori ini tidak bisa dihapus karena masih dipakai oleh produk. Pindahkan dulu produknya ke kategori lain.');
    } else {
        db()->prepare('DELETE FROM product_categories WHERE id = ?')->execute([$id]);
        flash('success', 'Kategori berhasil dihapus.');
    }
    redirect(BASE_URL . '/admin/kategori.php');
}

$categories = db()->query('SELECT * FROM product_categories ORDER BY urutan ASC, id ASC')->fetchAll();
?>

<div class="panel">
  <div class="panel-head"><h3>Tambah Kategori Baru</h3></div>
  <div class="panel-body">
    <form method="post" style="display:flex; gap:8px; flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="text" name="nama" class="form-control" placeholder="Nama kategori" required style="max-width:280px;">
      <button type="submit" name="add_category" value="1" class="btn btn-primary btn-sm">+ Tambah</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Daftar Kategori (<?= count($categories) ?>)</h3></div>
  <div class="panel-body">
    <p class="form-hint">Urutan di sini menentukan urutan tampil chip filter kategori di halaman menu (dari kiri ke kanan). Pakai ⬆️⬇️ untuk mengatur.</p>
  </div>
  <div class="panel-body table-wrap" style="padding-top:0;">
    <table class="data-table">
      <thead><tr><th>Urutan</th><th>Nama Kategori</th><th></th></tr></thead>
      <tbody>
        <?php if (!$categories): ?><tr><td colspan="3">Belum ada kategori.</td></tr><?php endif; ?>
        <?php foreach ($categories as $i => $c): ?>
        <tr>
          <td>
            <div class="table-actions">
              <?php if ($i > 0): ?>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button type="submit" name="move_category" value="up" class="icon-btn" title="Naikkan">⬆️</button></form>
              <?php endif; ?>
              <?php if ($i < count($categories) - 1): ?>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button type="submit" name="move_category" value="down" class="icon-btn" title="Turunkan">⬇️</button></form>
              <?php endif; ?>
            </div>
          </td>
          <td>
            <form method="post" style="display:flex; gap:8px; align-items:center;">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <input type="text" name="nama" class="form-control" value="<?= e($c['nama']) ?>" style="max-width:220px;">
              <button type="submit" name="rename_category" value="1" class="btn btn-outline btn-sm">Simpan</button>
            </form>
          </td>
          <td>
            <div class="table-actions">
              <form method="post" data-confirm="Hapus kategori '<?= e($c['nama']) ?>'?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button type="submit" name="delete_category" value="1" class="icon-btn danger" title="Hapus">🗑️</button>
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
