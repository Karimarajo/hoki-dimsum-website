<?php
/** Expects $p = product row (optionally with category_nama, from a JOIN) */
$catId = $p['category_id'] ?? 0;
$cartQty = cart_qty((int)$p['id']);
?>
<div class="card product-card" data-category="<?= (int)$catId ?>" data-product-wrap>
  <div class="product-thumb">
    <?php if (!empty($p['foto'])): ?>
      <img src="<?= UPLOAD_URL . '/' . e($p['foto']) ?>" alt="<?= e($p['nama']) ?>" loading="lazy">
    <?php else: ?>
      🥟
    <?php endif; ?>
    <?php if (!empty($p['category_nama'])): ?><span class="product-cat-tag"><?= e($p['category_nama']) ?></span><?php endif; ?>
    <?php if (!$p['is_available']): ?><div class="badge-unavailable">Habis</div><?php endif; ?>
  </div>
  <div class="product-body">
    <h3><?= e($p['nama_display'] ?: $p['nama']) ?></h3>
    <p class="desc"><?= e($p['deskripsi']) ?></p>
    <div class="line-total" data-line-total<?= $cartQty > 0 ? '' : ' style="display:none;"' ?>>Subtotal: <?= rupiah($p['harga'] * $cartQty) ?></div>
    <div class="product-foot">
      <span class="price"><?= rupiah($p['harga']) ?></span>
      <?php if ($p['is_available']): ?>
      <div class="qty-control<?= $cartQty > 0 ? ' active' : '' ?>" data-product-id="<?= (int)$p['id'] ?>" data-qty="<?= $cartQty ?>" data-price="<?= (int)$p['harga'] ?>">
        <button type="button" class="add-cart-btn" data-cart-init aria-label="Masukkan keranjang">+</button>
        <div class="qty-stepper">
          <button type="button" data-cart-minus aria-label="Kurangi">−</button>
          <span data-qty-value><?= $cartQty > 0 ? $cartQty : 1 ?></span>
          <button type="button" data-cart-plus aria-label="Tambah">+</button>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
