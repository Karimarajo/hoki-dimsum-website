<?php
$pageTitle = 'Cabang';
$activeMenu = 'cabang';
require __DIR__ . '/includes/admin-header.php';

function save_branch_hours(int $branchId, array $post): void {
    db()->prepare('DELETE FROM branch_hours WHERE branch_id = ?')->execute([$branchId]);
    $stmt = db()->prepare('INSERT INTO branch_hours (branch_id, hari, buka, tutup, is_closed) VALUES (?,?,?,?,?)');
    for ($hari = 0; $hari <= 6; $hari++) {
        $isClosed = isset($post["closed_$hari"]) ? 1 : 0;
        $buka = trim($post["buka_$hari"] ?? '');
        $tutup = trim($post["tutup_$hari"] ?? '');
        if ($isClosed) {
            $stmt->execute([$branchId, $hari, null, null, 1]);
        } elseif ($buka !== '' && $tutup !== '') {
            $stmt->execute([$branchId, $hari, $buka, $tutup, 0]);
        }
        // kalau tidak ditutup & jam kosong -> tidak insert baris (artinya tidak dibatasi di hari itu)
    }
}

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
                $savedBranchId = $id;
                flash('success', 'Cabang berhasil diperbarui.');
            } else {
                db()->prepare('INSERT INTO branches (nama, alamat, jam_operasional, wa_number, gofood_link, grabfood_link, shopeefood_link, qris_image, is_active) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$nama, $alamat, $jam, $wa ?: null, $gofood ?: null, $grabfood ?: null, $shopeefood ?: null, $qris, $isActive]);
                $savedBranchId = (int)db()->lastInsertId();
                flash('success', 'Cabang berhasil ditambahkan.');
            }
            save_branch_hours($savedBranchId, $_POST);
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
$editHours = [];
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM branches WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editBranch = $stmt->fetch();
    if ($editBranch) {
        $editHours = get_branch_hours((int)$editBranch['id']);
    }
}
$dayOrder = [1, 2, 3, 4, 5, 6, 0]; // Senin ... Minggu
$dayNames = day_names();

// Ambil daftar nama cabang di POS, utk peringatan kalau nama cabang di sini tidak match
// (nama HARUS sama persis dgn hoki_cabang.nama_cabang supaya sync menu & ketersediaan per-cabang jalan benar).
$posCabangNames = [];
$posApiBaseCek = $isDev
    ? 'http://127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? 80) . '/api.php'
    : 'https://pos-hokidimsum.com/api.php';
$chPos = curl_init($posApiBaseCek . '?action=get_cabang');
curl_setopt_array($chPos, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4]);
$resPos = curl_exec($chPos);
curl_close($chPos);
$posCabangData = $resPos !== false ? json_decode($resPos, true) : null;
if (is_array($posCabangData)) {
    foreach ($posCabangData as $pc) {
        if (!empty($pc['nama_cabang'])) {
            $posCabangNames[] = mb_strtolower(trim($pc['nama_cabang']));
        }
    }
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
          <label>Jam Operasional (teks tampilan, mis. "09.00 - 21.00 WIB")</label>
          <input type="text" name="jam_operasional" class="form-control" placeholder="09.00 - 21.00 WIB" value="<?= e($editBranch['jam_operasional'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Alamat</label>
        <textarea name="alamat" class="form-control" required><?= e($editBranch['alamat'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label>Jam &amp; Hari Buka per Hari (dipakai untuk otomatis menutup pilihan cabang saat checkout)</label>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Hari</th><th>Tutup Penuh</th><th>Jam Buka</th><th>Jam Tutup</th></tr></thead>
            <tbody>
              <?php foreach ($dayOrder as $hari): $h = $editHours[$hari] ?? null; ?>
              <tr>
                <td><?= e($dayNames[$hari]) ?></td>
                <td><input type="checkbox" name="closed_<?= $hari ?>" <?= ($h && $h['is_closed']) ? 'checked' : '' ?>></td>
                <td><input type="time" name="buka_<?= $hari ?>" class="form-control" value="<?= $h && $h['buka'] ? e(substr($h['buka'], 0, 5)) : '' ?>"></td>
                <td><input type="time" name="tutup_<?= $hari ?>" class="form-control" value="<?= $h && $h['tutup'] ? e(substr($h['tutup'], 0, 5)) : '' ?>"></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="form-hint">Kosongkan jam buka &amp; tutup (dan jangan centang Tutup Penuh) kalau hari itu tidak mau dibatasi &mdash; cabang akan selalu bisa dipilih saat checkout.</div>
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
      <thead><tr><th>Nama</th><th>Alamat</th><th>QRIS</th><th>Status</th><th>Buka Sekarang?</th><th></th></tr></thead>
      <tbody>
        <?php if (!$branches): ?><tr><td colspan="6">Belum ada cabang.</td></tr><?php endif; ?>
        <?php foreach ($branches as $b): $openNow = branch_is_open_now(get_branch_hours((int)$b['id'])); $namaMatchPos = !$posCabangNames || in_array(mb_strtolower(trim($b['nama'])), $posCabangNames, true); ?>
        <tr>
          <td><?= e($b['nama']) ?><?php if (!$namaMatchPos): ?><br><span class="badge-nonaktif-cabang" style="display:inline-block; margin-top:3px; font-size:10.5px; font-weight:700; color:#8a6d1f; background:var(--gold-100,#fff7e6); padding:1px 7px; border-radius:99px;" title="Nama cabang ini tidak ketemu match di daftar cabang POS. Sync menu POS→Order & ketersediaan per-cabang utk cabang ini tidak akan jalan sampai namanya disamakan.">⚠️ Tidak match ke POS</span><?php endif; ?></td>
          <td><?= e($b['alamat']) ?></td>
          <td><span class="status-pill <?= !empty($b['qris_image']) ? 'status-ready' : 'status-pending_payment' ?>"><?= !empty($b['qris_image']) ? 'Ada' : 'Pakai Pusat' ?></span></td>
          <td><span class="status-pill <?= $b['is_active'] ? 'status-ready' : 'status-cancelled' ?>"><?= $b['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
          <td><span class="status-pill <?= $openNow ? 'status-ready' : 'status-cancelled' ?>"><?= $openNow ? 'Buka' : 'Tutup' ?></span></td>
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
