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
