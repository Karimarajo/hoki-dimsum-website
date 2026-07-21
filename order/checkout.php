<?php
require_once __DIR__ . '/includes/cart.php';
$currentPage = 'checkout.php';
$pageTitle = 'Checkout — ' . APP_NAME;

// Kupon dikelola & divalidasi di database POS (pos-hokidimsum.com), bukan di database order.
// Dua URL berbeda dibutuhkan:
// - $posApiBase       : dipanggil server-to-server (PHP/cURL) saat submit checkout.
//   Lokal, panggil balik ke diri sendiri lewat 127.0.0.1:SERVER_PORT (bukan HTTP_HOST),
//   supaya tidak terganggu port-forwarding Docker/proxy apa pun.
// - $posApiBaseClient  : dipanggil dari browser customer (JS fetch) untuk live preview.
//   Harus pakai host yang benar-benar bisa diakses browser (HTTP_HOST apa adanya).
// Production: keduanya sama-sama domain pos-hokidimsum.com (order.pos-hokidimsum.com terpisah domain).
$posApiBase = $isDev
    ? 'http://127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? 80) . '/api.php'
    : 'https://pos-hokidimsum.com/api.php';
$posApiBaseClient = $isDev
    ? 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080') . '/api.php'
    : 'https://pos-hokidimsum.com/api.php';

function validasi_kupon_pos(string $posApiBase, string $kode, string $cabangNama, array $items, int $subtotal, bool $pakai = false, string $referensi = ''): array {
    $params = [
        'action'   => 'cek_kupon',
        'kode'     => $kode,
        'cabang'   => $cabangNama,
        'subtotal' => $subtotal,
        'items'    => json_encode($items),
    ];
    if ($pakai) {
        $params['pakai']     = 1;
        $params['sumber']    = 'order_online';
        $params['referensi'] = $referensi;
    }
    $url = $posApiBase . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return ['valid' => false, 'message' => 'Gagal menghubungi server untuk validasi kupon, coba lagi.'];
    }
    $data = json_decode($response, true);
    return is_array($data) ? $data : ['valid' => false, 'message' => 'Respon validasi kupon tidak valid.'];
}

$errors = [];
$branches = db()->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY nama")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!csrf_check()) {
        $errors[] = 'Sesi tidak valid, silakan coba lagi.';
    } elseif (!rate_limit_ok('checkout', 8)) {
        $errors[] = 'Mohon tunggu beberapa detik sebelum submit order lagi.';
    } else {
        $nama = trim($_POST['nama_customer'] ?? '');
        $noWa = trim($_POST['no_wa'] ?? '');
        $branchId = (int)($_POST['branch_id'] ?? 0);
        $pickupMethod = $_POST['pickup_method'] ?? '';
        $pickupDate = $_POST['pickup_date'] ?? '';
        $pickupTime = $_POST['pickup_time'] ?? '';
        $catatan = trim($_POST['catatan'] ?? '');

        $items = cart_items();
        $kuponKodeInput = trim($_POST['kupon_kode'] ?? '');

        if ($nama === '') $errors[] = 'Nama wajib diisi.';
        if (!preg_match('/^[0-9+]{9,15}$/', $noWa)) $errors[] = 'Nomor WhatsApp tidak valid.';
        if (!in_array($pickupMethod, ['sendiri', 'ojol'], true)) $errors[] = 'Pilih metode pengambilan.';
        if (!$pickupDate || strtotime($pickupDate) < strtotime(date('Y-m-d'))) $errors[] = 'Tanggal pengambilan tidak valid.';
        if (!$pickupTime) $errors[] = 'Jam pengambilan wajib diisi.';
        $branchValid = false;
        $branchNama = '';
        foreach ($branches as $b) { if ($b['id'] == $branchId) { $branchValid = true; $branchNama = $b['nama']; } }
        if (!$branchValid) {
            $errors[] = 'Pilih cabang yang valid.';
        } elseif (!branch_is_open_now(get_branch_hours($branchId))) {
            $errors[] = 'Cabang ini sedang tutup saat ini. Silakan pilih cabang lain atau coba lagi pada jam operasionalnya.';
        } elseif ($items) {
            $nonaktifNama = products_unavailable_at_branch(array_map(fn($it) => $it['product']['id'], $items), $branchId);
            if ($nonaktifNama) {
                $errors[] = 'Menu berikut sedang tidak tersedia di cabang ini: ' . implode(', ', $nonaktifNama) . '. Silakan hapus dari keranjang atau pilih cabang lain.';
            }
        }
        if (!$items) $errors[] = 'Keranjang kosong.';

        $subtotal = 0;
        foreach ($items as $it) $subtotal += $it['subtotal'];

        $diskonKupon = 0;
        $kuponKodeFinal = null;
        $kuponItems = array_map(fn($it) => ['sku' => $it['product']['pos_sku'] ?? ''], $items);
        if (!$errors && $kuponKodeInput !== '') {
            $cekKupon = validasi_kupon_pos($posApiBase, $kuponKodeInput, $branchNama, $kuponItems, (int)$subtotal);
            if ($cekKupon['valid']) {
                $diskonKupon = (int)$cekKupon['diskon'];
                $kuponKodeFinal = $cekKupon['kode'];
            } else {
                $errors[] = $cekKupon['message'] ?? 'Kupon tidak valid.';
            }
        }

        if (!$errors) {
            $kodeUnik = generate_kode_unik($pickupDate);
            $totalBayar = max(0, $subtotal + $kodeUnik - $diskonKupon);
            $orderCode = generate_order_code();

            try {
                db()->beginTransaction();
                $stmt = db()->prepare("INSERT INTO orders (order_code, nama_customer, no_wa, branch_id, pickup_method, pickup_date, pickup_time, catatan, subtotal, kode_unik, kupon_kode, diskon, total_bayar, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment')");
                $stmt->execute([$orderCode, $nama, $noWa, $branchId, $pickupMethod, $pickupDate, $pickupTime, $catatan, $subtotal, $kodeUnik, $kuponKodeFinal, $diskonKupon, $totalBayar]);
                $orderId = db()->lastInsertId();

                $itemStmt = db()->prepare("INSERT INTO order_items (order_id, product_id, nama_produk_snapshot, harga_snapshot, qty) VALUES (?, ?, ?, ?, ?)");
                foreach ($items as $it) {
                    $itemStmt->execute([$orderId, $it['product']['id'], $it['product']['nama'], $it['product']['harga'], $it['qty']]);
                }
                db()->commit();

                if ($kuponKodeFinal) {
                    validasi_kupon_pos($posApiBase, $kuponKodeFinal, $branchNama, $kuponItems, (int)$subtotal, true, $orderCode);
                }

                cart_clear();
                redirect(BASE_URL . '/pembayaran.php?order=' . urlencode($orderCode));
            } catch (Exception $e) {
                db()->rollBack();
                $errors[] = 'Gagal menyimpan order. Silakan coba lagi.';
            }
        }
    }
}

$items = cart_items();
$total = 0;
foreach ($items as $it) $total += $it['subtotal'];

require __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <span class="eyebrow">Checkout</span>
    <h1>Selesaikan Pesananmu</h1>
  </div>
</div>

<div class="section" style="padding-top:0;">
  <div class="container" style="max-width:640px;">

    <?php if ($errors): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="empty-state">
        <div class="ic">🛒</div>
        <p>Keranjangmu masih kosong.</p>
        <a href="<?= BASE_URL ?>/menu.php" class="btn btn-primary">Lihat Menu</a>
      </div>
    <?php else: ?>

    <div class="panel-like" style="margin-bottom:24px;">
      <h3 style="font-size:15px; margin-bottom:12px;">🛒 Keranjang Kamu</h3>
      <div class="cart-drawer-list">
        <?php foreach ($items as $it): $p = $it['product']; ?>
        <div class="cart-line">
          <div class="thumb"><?php if ($p['foto']): ?><img src="<?= UPLOAD_URL . '/' . e($p['foto']) ?>" alt=""><?php else: ?>🥟<?php endif; ?></div>
          <div>
            <div class="name"><?= e($p['nama']) ?></div>
            <div class="unit-price"><?= rupiah($p['harga']) ?> x <?= $it['qty'] ?></div>
          </div>
          <div class="line-actions">
            <input type="number" min="1" value="<?= $it['qty'] ?>" data-cart-qty="<?= $p['id'] ?>" style="width:56px; padding:6px; border-radius:8px; border:1px solid var(--line); text-align:center;">
            <a href="#" data-cart-remove="<?= $p['id'] ?>" class="remove-link">Hapus</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <form method="post" class="admin-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <div class="form-group">
        <label>Nama Lengkap</label>
        <input type="text" name="nama_customer" class="form-control" required value="<?= e($_POST['nama_customer'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Nomor WhatsApp</label>
        <input type="tel" name="no_wa" class="form-control" placeholder="08xxxxxxxxxx" required value="<?= e($_POST['no_wa'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Pilih Cabang</label>
        <select name="branch_id" class="form-control" required>
          <option value="">— Pilih cabang —</option>
          <?php foreach ($branches as $b): $openNow = branch_is_open_now(get_branch_hours((int)$b['id'])); ?>
          <option value="<?= $b['id'] ?>" <?= !$openNow ? 'disabled' : '' ?> <?= (isset($_POST['branch_id']) && $_POST['branch_id'] == $b['id']) ? 'selected' : '' ?>><?= e($b['nama']) ?><?= !$openNow ? ' (Tutup)' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Cabang yang sedang tutup di luar jam operasional tidak bisa dipilih.</div>
      </div>

      <div class="form-group">
        <label>Metode Pengambilan</label>
        <div class="radio-group">
          <label class="radio-card">
            <input type="radio" name="pickup_method" value="sendiri" required> Ambil Sendiri
          </label>
          <label class="radio-card">
            <input type="radio" name="pickup_method" value="ojol"> Diambil Ojol
          </label>
        </div>
      </div>

      <div class="form-row cols-2">
        <div class="form-group">
          <label>Tanggal Ambil</label>
          <input type="date" name="pickup_date" class="form-control" min="<?= date('Y-m-d') ?>" required value="<?= e($_POST['pickup_date'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-group">
          <label>Jam Ambil</label>
          <input type="time" name="pickup_time" class="form-control" required value="<?= e($_POST['pickup_time'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Catatan (opsional)</label>
        <textarea name="catatan" class="form-control" placeholder="Contoh: tanpa saus pedas"><?= e($_POST['catatan'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label>Kode Kupon (opsional)</label>
        <div style="display:flex; gap:8px;">
          <input type="text" name="kupon_kode" id="kuponKodeInput" class="form-control" style="text-transform:uppercase;" placeholder="Contoh: HOKI50" value="<?= e($_POST['kupon_kode'] ?? '') ?>">
          <button type="button" class="btn btn-outline" onclick="cekKuponPreview()">Cek</button>
        </div>
        <div id="kuponPreviewMsg" style="font-size:12px; margin-top:6px;"></div>
      </div>

      <div class="summary-box" style="margin-bottom:20px;">
        <div class="summary-row"><span>Subtotal</span><span><?= rupiah($total) ?></span></div>
        <div class="summary-row total"><span>Total</span><span><?= rupiah($total) ?></span></div>
        <div class="form-hint">*Kode unik 3 digit &amp; diskon kupon (bila ada) akan ditambahkan/dihitung otomatis di halaman pembayaran.</div>
      </div>

      <button type="submit" name="place_order" value="1" class="btn btn-primary btn-block">Buat Pesanan →</button>
    </form>

    <script>
    async function cekKuponPreview() {
        const kode = document.getElementById('kuponKodeInput').value.trim().toUpperCase();
        const msgEl = document.getElementById('kuponPreviewMsg');
        const branchSelect = document.querySelector('select[name=branch_id]');
        const cabangNama = branchSelect.options[branchSelect.selectedIndex] ? branchSelect.options[branchSelect.selectedIndex].text : '';

        if (!kode) { msgEl.textContent = ''; return; }
        if (!branchSelect.value) {
            msgEl.textContent = 'Pilih cabang dulu sebelum cek kupon.';
            msgEl.style.color = '#c8372d';
            return;
        }

        msgEl.textContent = 'Mengecek kupon...';
        msgEl.style.color = '#888';

        const items = <?= json_encode(array_map(fn($it) => ['sku' => $it['product']['pos_sku'] ?? ''], $items)) ?>;
        const subtotal = <?= (int)$total ?>;
        const posApiBase = <?= json_encode($posApiBaseClient) ?>;

        try {
            const params = new URLSearchParams({ action: 'cek_kupon', kode, cabang: cabangNama, subtotal, items: JSON.stringify(items) });
            const res  = await fetch(`${posApiBase}?${params.toString()}`);
            const json = await res.json();
            if (json.valid) {
                msgEl.textContent = `✅ Kupon valid! Diskon Rp${Number(json.diskon).toLocaleString('id-ID')}`;
                msgEl.style.color = '#2e7d32';
            } else {
                msgEl.textContent = `❌ ${json.message}`;
                msgEl.style.color = '#c8372d';
            }
        } catch (e) {
            msgEl.textContent = 'Gagal menghubungi server untuk cek kupon.';
            msgEl.style.color = '#c8372d';
        }
    }
    </script>

    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
