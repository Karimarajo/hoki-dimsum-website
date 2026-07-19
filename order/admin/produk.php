<?php
$pageTitle = 'Produk';
$activeMenu = 'produk';
require __DIR__ . '/includes/admin-header.php';

// ── Sinkron nama & harga dari Master Produk POS (pos-hokidimsum.com) ──
// POS = sumber kebenaran nama & harga. foto/kategori/deskripsi tetap milik Order Online.
$posApiBase = $isDev
    ? 'http://127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? 80) . '/api.php'
    : 'https://pos-hokidimsum.com/api.php';

function sync_produk_dari_master(string $posApiBase): ?string {
    $ch = curl_init($posApiBase . '?action=get_produk');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6]);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) return 'Gagal menghubungi server POS untuk sinkron produk.';

    $master = json_decode($res, true);
    if (!is_array($master)) return 'Data master produk tidak valid.';

    $masterSkus = [];
    foreach ($master as $m) {
        $sku = trim($m['sku'] ?? '');
        if ($sku === '') continue;
        $masterSkus[] = $sku;

        $existing = db()->prepare('SELECT id FROM products WHERE pos_sku = ?');
        $existing->execute([$sku]);
        $row = $existing->fetch();

        if ($row) {
            db()->prepare('UPDATE products SET nama = ?, harga = ? WHERE id = ?')
                ->execute([$m['nama'], $m['harga'], $row['id']]);
        } else {
            db()->prepare('INSERT INTO products (category_id, pos_sku, nama, deskripsi, harga, foto, is_available) VALUES (NULL, ?, ?, ?, ?, ?, 1)')
                ->execute([$sku, $m['nama'], '', $m['harga'], '']);
        }
    }

    // Produk yang sebelumnya tersinkron tapi sudah tidak ada lagi di master POS -> nonaktifkan
    // (bukan hard-delete, supaya tidak melanggar riwayat order_items lama).
    if ($masterSkus) {
        $placeholders = implode(',', array_fill(0, count($masterSkus), '?'));
        db()->prepare("UPDATE products SET is_available = 0 WHERE pos_sku IS NOT NULL AND pos_sku NOT IN ($placeholders)")
            ->execute($masterSkus);
    } else {
        db()->query("UPDATE products SET is_available = 0 WHERE pos_sku IS NOT NULL");
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_produk']) && csrf_check()) {
    $syncErr = sync_produk_dari_master($posApiBase);
    if ($syncErr) {
        flash('error', $syncErr);
    } else {
        flash('success', 'Produk berhasil disinkron dari Master Produk POS.');
    }
    redirect(BASE_URL . '/admin/produk.php');
}

// ---- Handle add category ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category']) && csrf_check()) {
    $nama = trim($_POST['category_nama'] ?? '');
    if ($nama !== '') {
        db()->prepare('INSERT INTO product_categories (nama) VALUES (?)')->execute([$nama]);
        flash('success', 'Kategori berhasil ditambahkan.');
    }
    redirect(BASE_URL . '/admin/produk.php');
}

// ---- Handle save product (add/edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product']) && csrf_check()) {
    $id = (int)($_POST['id'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
    $isAvailable = isset($_POST['is_available']) ? 1 : 0;

    // Produk yang tersinkron dari master POS (pos_sku terisi): nama & harga TIDAK bisa diedit di sini,
    // sumbernya adalah Master Produk POS. Cuma foto/kategori/deskripsi/status yang bisa diubah.
    $isSynced = false;
    if ($id > 0) {
        $chk = db()->prepare('SELECT pos_sku FROM products WHERE id = ?');
        $chk->execute([$id]);
        $chkRow = $chk->fetch();
        $isSynced = !empty($chkRow['pos_sku']);
    }
    $nama = $isSynced ? null : trim($_POST['nama'] ?? '');
    $harga = $isSynced ? null : (float)($_POST['harga'] ?? 0);

    try {
        $foto = null;
        if (!empty($_FILES['foto']['name'])) {
            $foto = upload_image($_FILES['foto'], 'products');
        }

        if (!$isSynced && ($nama === '' || $harga <= 0)) {
            flash('error', 'Nama dan harga produk wajib diisi dengan benar.');
        } elseif ($id > 0) {
            if ($isSynced) {
                if ($foto) {
                    db()->prepare('UPDATE products SET category_id=?, deskripsi=?, foto=?, is_available=? WHERE id=?')
                        ->execute([$categoryId, $deskripsi, $foto, $isAvailable, $id]);
                } else {
                    db()->prepare('UPDATE products SET category_id=?, deskripsi=?, is_available=? WHERE id=?')
                        ->execute([$categoryId, $deskripsi, $isAvailable, $id]);
                }
            } elseif ($foto) {
                db()->prepare('UPDATE products SET category_id=?, nama=?, deskripsi=?, harga=?, foto=?, is_available=? WHERE id=?')
                    ->execute([$categoryId, $nama, $deskripsi, $harga, $foto, $isAvailable, $id]);
            } else {
                db()->prepare('UPDATE products SET category_id=?, nama=?, deskripsi=?, harga=?, is_available=? WHERE id=?')
                    ->execute([$categoryId, $nama, $deskripsi, $harga, $isAvailable, $id]);
            }
            flash('success', 'Produk berhasil diperbarui.');
        } else {
            db()->prepare('INSERT INTO products (category_id, nama, deskripsi, harga, foto, is_available) VALUES (?,?,?,?,?,?)')
                ->execute([$categoryId, $nama, $deskripsi, $harga, $foto ?? '', $isAvailable]);
            flash('success', 'Produk berhasil ditambahkan.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect(BASE_URL . '/admin/produk.php');
}

// ---- Handle delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product']) && csrf_check()) {
    $delId = (int)$_POST['id'];
    $chkDel = db()->prepare('SELECT pos_sku FROM products WHERE id = ?');
    $chkDel->execute([$delId]);
    $delRow = $chkDel->fetch();
    if (!empty($delRow['pos_sku'])) {
        flash('error', 'Produk ini tersinkron dari Master Produk POS. Hapus dari POS, atau nonaktifkan saja di sini (edit → hilangkan centang Tersedia).');
    } else {
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$delId]);
        flash('success', 'Produk berhasil dihapus.');
    }
    redirect(BASE_URL . '/admin/produk.php');
}

$categories = db()->query('SELECT * FROM product_categories ORDER BY nama')->fetchAll();
$products = db()->query('SELECT p.*, c.nama AS category_nama FROM products p LEFT JOIN product_categories c ON c.id = p.category_id ORDER BY p.id DESC')->fetchAll();

$editProduct = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editProduct = $stmt->fetch();
}
?>

<div class="panel">
  <div class="panel-head">
    <h3>🔗 Master Produk POS</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <button type="submit" name="sync_produk" value="1" class="btn btn-outline btn-sm">🔄 Sync dari Master Produk</button>
    </form>
  </div>
  <div class="panel-body">
    <p class="form-hint mb-0">Nama &amp; harga produk yang tersinkron mengikuti Master Produk di POS (pos-hokidimsum.com). Foto, kategori, dan deskripsi tetap dikelola di sini.</p>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <h3><?= $editProduct ? 'Edit Produk' : 'Tambah Produk Baru' ?></h3>
    <?php if ($editProduct): ?><a href="<?= BASE_URL ?>/admin/produk.php" class="btn btn-outline btn-sm">Batal Edit</a><?php endif; ?>
  </div>
  <div class="panel-body">
    <?php $editIsSynced = !empty($editProduct['pos_sku']); ?>
    <?php if ($editIsSynced): ?>
      <div class="alert alert-error" style="background:var(--gold-100,#fff7e6); color:#8a6d1f;">🔗 Produk ini tersinkron dari Master Produk POS (SKU: <?= e($editProduct['pos_sku']) ?>). Nama &amp; harga hanya bisa diubah dari POS.</div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="admin-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <?php if ($editProduct): ?><input type="hidden" name="id" value="<?= $editProduct['id'] ?>"><?php endif; ?>

      <div class="form-row cols-2">
        <div class="form-group">
          <label>Nama Produk</label>
          <input type="text" name="nama" class="form-control" required value="<?= e($editProduct['nama'] ?? '') ?>" <?= $editIsSynced ? 'readonly disabled' : '' ?>>
        </div>
        <div class="form-group">
          <label>Kategori</label>
          <select name="category_id" class="form-control">
            <option value="">— Tanpa kategori —</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (($editProduct['category_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= e($c['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Deskripsi</label>
        <textarea name="deskripsi" class="form-control"><?= e($editProduct['deskripsi'] ?? '') ?></textarea>
      </div>

      <div class="form-row cols-2">
        <div class="form-group">
          <label>Harga (Rp)</label>
          <input type="number" name="harga" min="0" step="500" class="form-control" required value="<?= e((string)($editProduct['harga'] ?? '')) ?>" <?= $editIsSynced ? 'readonly disabled' : '' ?>>
        </div>
        <div class="form-group">
          <label>Foto Produk</label>
          <input type="file" name="foto" accept="image/png,image/jpeg,image/webp" class="form-control" data-image-input="fotoPreview">
          <div class="form-hint">Max 2MB. JPG/PNG/WEBP.</div>
        </div>
      </div>
      <div class="image-preview" id="fotoPreview">
        <?php if (!empty($editProduct['foto'])): ?><img src="<?= UPLOAD_URL . '/' . e($editProduct['foto']) ?>" alt=""><?php else: ?>Tidak ada foto<?php endif; ?>
      </div>

      <div class="form-group">
        <label><input type="checkbox" name="is_available" <?= ($editProduct['is_available'] ?? 1) ? 'checked' : '' ?>> Tersedia untuk dijual</label>
      </div>

      <button type="submit" name="save_product" value="1" class="btn btn-primary"><?= $editProduct ? 'Simpan Perubahan' : 'Tambah Produk' ?></button>
    </form>

    <details style="margin-top:18px;">
      <summary style="cursor:pointer; font-weight:600; font-size:13.5px; color:var(--red-600);">+ Tambah kategori baru</summary>
      <form method="post" style="display:flex; gap:8px; margin-top:12px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="text" name="category_nama" class="form-control" placeholder="Nama kategori" required>
        <button type="submit" name="add_category" value="1" class="btn btn-outline btn-sm">Tambah</button>
      </form>
    </details>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Daftar Produk (<?= count($products) ?>)</h3></div>
  <div class="panel-body table-wrap">
    <table class="data-table">
      <thead><tr><th>Foto</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Sumber</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$products): ?><tr><td colspan="7">Belum ada produk.</td></tr><?php endif; ?>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><?php if ($p['foto']): ?><img src="<?= UPLOAD_URL . '/' . e($p['foto']) ?>" class="thumb-sm"><?php else: ?>🥟<?php endif; ?></td>
          <td><?= e($p['nama']) ?></td>
          <td><?= e($p['category_nama'] ?? '-') ?></td>
          <td><?= rupiah($p['harga']) ?></td>
          <td><?php if (!empty($p['pos_sku'])): ?><span class="status-pill status-ready">🔗 POS</span><?php else: ?><span class="status-pill status-pending_payment">Manual</span><?php endif; ?></td>
          <td><span class="status-pill <?= $p['is_available'] ? 'status-ready' : 'status-cancelled' ?>"><?= $p['is_available'] ? 'Tersedia' : 'Habis' ?></span></td>
          <td>
            <div class="table-actions">
              <a href="?edit=<?= $p['id'] ?>" class="icon-btn edit" title="Edit">✏️</a>
              <form method="post" data-confirm="Hapus produk '<?= e($p['nama']) ?>'?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" name="delete_product" value="1" class="icon-btn danger" title="Hapus">🗑️</button>
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
