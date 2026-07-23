<?php
$pageTitle = 'Produk';
$activeMenu = 'produk';
require __DIR__ . '/includes/admin-header.php';

// ── Sinkron nama/harga/deskripsi/kategori/nama tampilan & ketersediaan per cabang dari Master Produk POS ──
// POS = satu-satunya sumber kebenaran utk data menu. Foto tetap milik Order Online sepenuhnya.
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

    // Mapping nama cabang POS -> branch_id Order (match case-insensitive; nama HARUS sama persis antar sistem)
    $branchByNama = [];
    foreach (db()->query('SELECT id, nama FROM branches')->fetchAll() as $b) {
        $branchByNama[mb_strtolower(trim($b['nama']))] = (int)$b['id'];
    }

    $masterSkus = [];
    foreach ($master as $m) {
        $sku = trim($m['sku'] ?? '');
        if ($sku === '') continue;
        $masterSkus[] = $sku;

        $kategoriNama = trim($m['kategori'] ?? '') ?: 'Umum';
        $catStmt = db()->prepare('SELECT id FROM product_categories WHERE nama = ?');
        $catStmt->execute([$kategoriNama]);
        $catRow = $catStmt->fetch();
        if ($catRow) {
            $categoryId = (int)$catRow['id'];
        } else {
            db()->prepare('INSERT INTO product_categories (nama) VALUES (?)')->execute([$kategoriNama]);
            $categoryId = (int)db()->lastInsertId();
        }

        $namaDisplay = trim($m['nama_display'] ?? '') ?: null;
        $deskripsi = trim($m['deskripsi'] ?? '');

        $existing = db()->prepare('SELECT id FROM products WHERE pos_sku = ?');
        $existing->execute([$sku]);
        $row = $existing->fetch();

        if ($row) {
            $productId = (int)$row['id'];
            db()->prepare('UPDATE products SET nama = ?, harga = ?, nama_display = ?, deskripsi = ?, category_id = ? WHERE id = ?')
                ->execute([$m['nama'], $m['harga'], $namaDisplay, $deskripsi, $categoryId, $productId]);
        } else {
            $nextUrutan = (int)db()->query('SELECT COALESCE(MAX(urutan), 0) FROM products')->fetchColumn() + 1;
            db()->prepare('INSERT INTO products (category_id, pos_sku, nama, nama_display, deskripsi, harga, foto, is_available, urutan) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)')
                ->execute([$categoryId, $sku, $m['nama'], $namaDisplay, $deskripsi, $m['harga'], '', $nextUrutan]);
            $productId = (int)db()->lastInsertId();
        }

        // Rebuild ketersediaan per cabang (opt-out: baris di sini = nonaktif di cabang itu)
        db()->prepare('DELETE FROM product_branch_unavailable WHERE product_id = ?')->execute([$productId]);
        $nonaktifCabang = $m['nonaktif_cabang'] ?? [];
        if (is_array($nonaktifCabang)) {
            foreach ($nonaktifCabang as $cabangNama) {
                $branchId = $branchByNama[mb_strtolower(trim($cabangNama))] ?? null;
                if ($branchId) {
                    db()->prepare('INSERT IGNORE INTO product_branch_unavailable (product_id, branch_id) VALUES (?, ?)')
                        ->execute([$productId, $branchId]);
                }
                // nama cabang POS tidak ketemu match di Order -> skip diam-diam (lihat peringatan di admin/cabang.php)
            }
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
        $nextUrutanCat = (int)db()->query('SELECT COALESCE(MAX(urutan), 0) FROM product_categories')->fetchColumn() + 1;
        db()->prepare('INSERT INTO product_categories (nama, urutan) VALUES (?, ?)')->execute([$nama, $nextUrutanCat]);
        flash('success', 'Kategori berhasil ditambahkan.');
    }
    redirect(BASE_URL . '/admin/produk.php');
}

// ---- Handle save product (add/edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product']) && csrf_check()) {
    $id = (int)($_POST['id'] ?? 0);
    $isAvailable = isset($_POST['is_available']) ? 1 : 0;

    // Produk yang tersinkron dari master POS (pos_sku terisi): nama/harga/deskripsi/kategori/nama tampilan
    // TIDAK bisa diedit di sini, sumbernya adalah Master Produk POS (produk.html). Cuma foto & status yg bisa diubah.
    $isSynced = false;
    if ($id > 0) {
        $chk = db()->prepare('SELECT pos_sku FROM products WHERE id = ?');
        $chk->execute([$id]);
        $chkRow = $chk->fetch();
        $isSynced = !empty($chkRow['pos_sku']);
    }
    $nama = $isSynced ? null : trim($_POST['nama'] ?? '');
    $harga = $isSynced ? null : (float)($_POST['harga'] ?? 0);
    $deskripsi = $isSynced ? null : trim($_POST['deskripsi'] ?? '');
    $categoryId = $isSynced ? null : ((int)($_POST['category_id'] ?? 0) ?: null);

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
                    db()->prepare('UPDATE products SET foto=?, is_available=? WHERE id=?')
                        ->execute([$foto, $isAvailable, $id]);
                } else {
                    db()->prepare('UPDATE products SET is_available=? WHERE id=?')
                        ->execute([$isAvailable, $id]);
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
            $nextUrutan = (int)db()->query('SELECT COALESCE(MAX(urutan), 0) FROM products')->fetchColumn() + 1;
            db()->prepare('INSERT INTO products (category_id, nama, deskripsi, harga, foto, is_available, urutan) VALUES (?,?,?,?,?,?,?)')
                ->execute([$categoryId, $nama, $deskripsi, $harga, $foto ?? '', $isAvailable, $nextUrutan]);
            flash('success', 'Produk berhasil ditambahkan.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect(BASE_URL . '/admin/produk.php');
}

// ---- Handle move (ubah urutan tampil di menu) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_product']) && csrf_check()) {
    $id = (int)$_POST['id'];
    $dir = $_POST['move_product'] === 'up' ? 'up' : 'down';
    $current = db()->prepare('SELECT id, urutan FROM products WHERE id = ?');
    $current->execute([$id]);
    $curRow = $current->fetch();
    if ($curRow) {
        $neighborStmt = $dir === 'up'
            ? db()->prepare('SELECT id, urutan FROM products WHERE urutan < ? ORDER BY urutan DESC LIMIT 1')
            : db()->prepare('SELECT id, urutan FROM products WHERE urutan > ? ORDER BY urutan ASC LIMIT 1');
        $neighborStmt->execute([$curRow['urutan']]);
        $neighbor = $neighborStmt->fetch();
        if ($neighbor) {
            db()->prepare('UPDATE products SET urutan = ? WHERE id = ?')->execute([$neighbor['urutan'], $curRow['id']]);
            db()->prepare('UPDATE products SET urutan = ? WHERE id = ?')->execute([$curRow['urutan'], $neighbor['id']]);
        }
    }
    redirect(BASE_URL . '/admin/produk.php');
}

// ---- Handle delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product']) && csrf_check()) {
    $delId = (int)$_POST['id'];
    $chkDel = db()->prepare('SELECT pos_sku FROM products WHERE id = ?');
    $chkDel->execute([$delId]);
    $delRow = $chkDel->fetch();

    $orderCountStmt = db()->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
    $orderCountStmt->execute([$delId]);
    $sudahPernahDipesan = (int)$orderCountStmt->fetchColumn() > 0;

    if (!empty($delRow['pos_sku'])) {
        flash('error', 'Produk ini tersinkron dari Master Produk POS. Hapus dari POS, atau nonaktifkan saja di sini (edit → hilangkan centang Tersedia).');
    } elseif ($sudahPernahDipesan) {
        flash('error', 'Produk ini tidak bisa dihapus karena sudah pernah dipesan (ada riwayat order). Nonaktifkan saja (edit → hilangkan centang Tersedia).');
    } else {
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$delId]);
        flash('success', 'Produk berhasil dihapus.');
    }
    redirect(BASE_URL . '/admin/produk.php');
}

$categories = db()->query('SELECT * FROM product_categories ORDER BY nama')->fetchAll();
$products = db()->query('SELECT p.*, c.nama AS category_nama FROM products p LEFT JOIN product_categories c ON c.id = p.category_id ORDER BY p.urutan ASC, p.id ASC')->fetchAll();

$editProduct = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editProduct = $stmt->fetch();
}
$editIsSynced = !empty($editProduct['pos_sku']);
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
    <p class="form-hint mb-0">Nama, harga, deskripsi, kategori, nama tampilan, dan ketersediaan per cabang produk yang tersinkron mengikuti Master Produk di POS (pos-hokidimsum.com). Foto tetap dikelola di sini.</p>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <h3><?= $editProduct ? 'Edit Produk' : 'Tambah Produk Baru' ?></h3>
    <?php if ($editProduct): ?><a href="<?= BASE_URL ?>/admin/produk.php" class="btn btn-outline btn-sm">Batal Edit</a><?php endif; ?>
  </div>
  <div class="panel-body">
    <?php if ($editIsSynced): ?>
      <div class="alert alert-error" style="background:var(--gold-100,#fff7e6); color:#8a6d1f;">🔗 Produk ini tersinkron dari Master Produk POS (SKU: <?= e($editProduct['pos_sku']) ?>). Nama, harga, deskripsi, kategori &amp; nama tampilan hanya bisa diubah dari POS (produk.html).</div>
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
          <select name="category_id" class="form-control" <?= $editIsSynced ? 'disabled' : '' ?>>
            <option value="">— Tanpa kategori —</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (($editProduct['category_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= e($c['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php if ($editIsSynced): ?>
      <div class="form-group">
        <label>Nama Tampilan (diatur dari POS)</label>
        <input type="text" class="form-control" readonly disabled value="<?= e($editProduct['nama_display'] ?? '— sama dgn nama produk —') ?>">
      </div>
      <?php endif; ?>

      <div class="form-group">
        <label>Deskripsi</label>
        <textarea name="deskripsi" class="form-control" <?= $editIsSynced ? 'readonly disabled' : '' ?>><?= e($editProduct['deskripsi'] ?? '') ?></textarea>
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
  <div class="panel-body">
    <p class="form-hint">Urutan di sini menentukan urutan tampil menu di halaman order (dari atas ke bawah). Pakai ⬆️⬇️ untuk mengatur.</p>
  </div>
  <div class="panel-body table-wrap" style="padding-top:0;">
    <table class="data-table">
      <thead><tr><th>Urutan</th><th>Foto</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Sumber</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$products): ?><tr><td colspan="8">Belum ada produk.</td></tr><?php endif; ?>
        <?php foreach ($products as $i => $p): ?>
        <tr>
          <td>
            <div class="table-actions">
              <?php if ($i > 0): ?>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button type="submit" name="move_product" value="up" class="icon-btn" title="Naikkan">⬆️</button></form>
              <?php endif; ?>
              <?php if ($i < count($products) - 1): ?>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button type="submit" name="move_product" value="down" class="icon-btn" title="Turunkan">⬇️</button></form>
              <?php endif; ?>
            </div>
          </td>
          <td><?php if ($p['foto']): ?><img src="<?= UPLOAD_URL . '/' . e($p['foto']) ?>" class="thumb-sm"><?php else: ?>🥟<?php endif; ?></td>
          <td><?= e($p['nama']) ?><?php if (!empty($p['nama_display'])): ?><br><span class="form-hint mb-0">Tampil: <?= e($p['nama_display']) ?></span><?php endif; ?></td>
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
