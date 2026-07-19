<?php
$pageTitle = 'Setting';
$activeMenu = 'setting';
require __DIR__ . '/includes/admin-header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings']) && csrf_check()) {
    $fields = ['wa_pusat', 'tagline', 'tentang', 'instagram', 'meta_description'];
    foreach ($fields as $f) {
        set_setting($f, trim($_POST[$f] ?? ''));
    }
    flash('success', 'Pengaturan berhasil disimpan.');
    redirect(BASE_URL . '/admin/setting.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_slide']) && csrf_check()) {
    try {
        if (empty($_FILES['gambar']['name'])) {
            flash('error', 'Pilih gambar dulu.');
        } else {
            $gambar = upload_image($_FILES['gambar'], 'hero');
            $maxUrutan = (int)db()->query('SELECT COALESCE(MAX(urutan), 0) FROM hero_slides')->fetchColumn();
            db()->prepare('INSERT INTO hero_slides (gambar, urutan, is_active) VALUES (?, ?, 1)')
                ->execute([$gambar, $maxUrutan + 1]);
            flash('success', 'Gambar slider berhasil ditambahkan.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect(BASE_URL . '/admin/setting.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_slide']) && csrf_check()) {
    db()->prepare('UPDATE hero_slides SET is_active = NOT is_active WHERE id = ?')->execute([(int)$_POST['id']]);
    redirect(BASE_URL . '/admin/setting.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_slide']) && csrf_check()) {
    db()->prepare('DELETE FROM hero_slides WHERE id = ?')->execute([(int)$_POST['id']]);
    flash('success', 'Gambar slider berhasil dihapus.');
    redirect(BASE_URL . '/admin/setting.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_slide']) && csrf_check()) {
    $id = (int)$_POST['id'];
    $dir = $_POST['move_slide'] === 'up' ? 'up' : 'down';
    $current = db()->prepare('SELECT id, urutan FROM hero_slides WHERE id = ?');
    $current->execute([$id]);
    $curRow = $current->fetch();
    if ($curRow) {
        $neighborStmt = $dir === 'up'
            ? db()->prepare('SELECT id, urutan FROM hero_slides WHERE urutan < ? ORDER BY urutan DESC LIMIT 1')
            : db()->prepare('SELECT id, urutan FROM hero_slides WHERE urutan > ? ORDER BY urutan ASC LIMIT 1');
        $neighborStmt->execute([$curRow['urutan']]);
        $neighbor = $neighborStmt->fetch();
        if ($neighbor) {
            db()->prepare('UPDATE hero_slides SET urutan = ? WHERE id = ?')->execute([$neighbor['urutan'], $curRow['id']]);
            db()->prepare('UPDATE hero_slides SET urutan = ? WHERE id = ?')->execute([$curRow['urutan'], $neighbor['id']]);
        }
    }
    redirect(BASE_URL . '/admin/setting.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && csrf_check()) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $admin = current_admin();
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = ?');
    $stmt->execute([$admin['id']]);
    $row = $stmt->fetch();

    if (!password_verify($current, $row['password_hash'])) {
        flash('error', 'Password saat ini salah.');
    } elseif (strlen($new) < 6) {
        flash('error', 'Password baru minimal 6 karakter.');
    } elseif ($new !== $confirm) {
        flash('error', 'Konfirmasi password tidak cocok.');
    } else {
        db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
        flash('success', 'Password berhasil diganti.');
    }
    redirect(BASE_URL . '/admin/setting.php');
}
?>

<div class="panel">
  <div class="panel-head"><h3>Informasi Pembayaran &amp; Kontak</h3></div>
  <div class="panel-body">
    <form method="post" class="admin-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <div class="form-row cols-2">
        <div class="form-group">
          <label>Nomor WA Pusat</label>
          <input type="text" name="wa_pusat" class="form-control" placeholder="6281234567890" value="<?= e(get_setting('wa_pusat')) ?>">
        </div>
        <div class="form-group">
          <label>Instagram</label>
          <input type="url" name="instagram" class="form-control" value="<?= e(get_setting('instagram')) ?>">
        </div>
      </div>

      <div class="form-hint" style="margin-bottom:14px;">💡 QRIS pembayaran sekarang diatur per cabang, lihat menu <a href="<?= BASE_URL ?>/admin/cabang.php">Cabang</a>.</div>

      <div class="form-group">
        <label>Tagline Website</label>
        <input type="text" name="tagline" class="form-control" value="<?= e(get_setting('tagline')) ?>">
      </div>
      <div class="form-group">
        <label>Tentang Kami</label>
        <textarea name="tentang" class="form-control" rows="4"><?= e(get_setting('tentang')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Meta Description (SEO)</label>
        <textarea name="meta_description" class="form-control"><?= e(get_setting('meta_description')) ?></textarea>
      </div>

      <button type="submit" name="save_settings" value="1" class="btn btn-primary">Simpan Pengaturan</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>🖼️ Slider Hero (Halaman Utama)</h3></div>
  <div class="panel-body">
    <p class="form-hint" style="margin-bottom:14px;">Upload beberapa gambar untuk slider di bagian hero halaman utama. Kalau cuma 1 gambar aktif, slider tidak akan geser otomatis. Kalau belum ada gambar, tampilan default (ikon dimsum) yang dipakai.</p>

    <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; margin-bottom:18px; flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="file" name="gambar" accept="image/png,image/jpeg,image/webp" required>
      <button type="submit" name="add_slide" value="1" class="btn btn-primary btn-sm">+ Tambah Gambar</button>
      <span class="form-hint mb-0">Max 2MB. JPG/PNG/WEBP. Rasio 16:11 paling pas.</span>
    </form>

    <?php
    $slides = db()->query('SELECT * FROM hero_slides ORDER BY urutan ASC')->fetchAll();
    ?>
    <?php if (!$slides): ?>
      <p class="form-hint mb-0">Belum ada gambar slider.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Gambar</th><th>Urutan</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($slides as $i => $s): ?>
          <tr>
            <td><img src="<?= UPLOAD_URL . '/' . e($s['gambar']) ?>" class="thumb-sm"></td>
            <td><?= (int)$s['urutan'] ?></td>
            <td><span class="status-pill <?= $s['is_active'] ? 'status-ready' : 'status-cancelled' ?>"><?= $s['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
            <td>
              <div class="table-actions">
                <?php if ($i > 0): ?>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button type="submit" name="move_slide" value="up" class="icon-btn" title="Naikkan">⬆️</button></form>
                <?php endif; ?>
                <?php if ($i < count($slides) - 1): ?>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button type="submit" name="move_slide" value="down" class="icon-btn" title="Turunkan">⬇️</button></form>
                <?php endif; ?>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button type="submit" name="toggle_slide" value="1" class="icon-btn" title="<?= $s['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>"><?= $s['is_active'] ? '🙈' : '👁️' ?></button></form>
                <form method="post" data-confirm="Hapus gambar slider ini?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button type="submit" name="delete_slide" value="1" class="icon-btn danger" title="Hapus">🗑️</button></form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Ganti Password Admin</h3></div>
  <div class="panel-body">
    <form method="post" class="admin-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="form-group">
        <label>Password Saat Ini</label>
        <input type="password" name="current_password" class="form-control" required>
      </div>
      <div class="form-row cols-2">
        <div class="form-group">
          <label>Password Baru</label>
          <input type="password" name="new_password" class="form-control" required minlength="6">
        </div>
        <div class="form-group">
          <label>Konfirmasi Password Baru</label>
          <input type="password" name="confirm_password" class="form-control" required minlength="6">
        </div>
      </div>
      <button type="submit" name="change_password" value="1" class="btn btn-outline">Ganti Password</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
