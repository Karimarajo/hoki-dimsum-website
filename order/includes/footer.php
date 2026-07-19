<?php
$waPusat = get_setting('wa_pusat', '');
?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">🥟 <?= e(APP_NAME) ?></div>
        <p><?= e(get_setting('tagline', '')) ?></p>
        <?php if ($waPusat): ?>
        <div class="footer-social">
          <a href="<?= e(wa_link($waPusat, 'Halo Hoki Dimsum, saya mau tanya-tanya.')) ?>" target="_blank" rel="noopener" aria-label="WhatsApp">💬</a>
          <?php if (get_setting('instagram')): ?>
          <a href="<?= e(get_setting('instagram')) ?>" target="_blank" rel="noopener" aria-label="Instagram">📷</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="footer-col">
        <h4>Navigasi</h4>
        <ul>
          <li><a href="<?= BASE_URL ?>/menu.php">Menu</a></li>
          <li><a href="<?= BASE_URL ?>/cabang.php">Cabang</a></li>
          <li><a href="<?= BASE_URL ?>/promo.php">Promo</a></li>
          <li><a href="<?= BASE_URL ?>/blog.php">Blog</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Layanan</h4>
        <ul>
          <li><a href="<?= BASE_URL ?>/checkout.php">Order Sekarang</a></li>
          <li><a href="<?= BASE_URL ?>/cek-status.php">Cek Status Order</a></li>
          <li><a href="<?= BASE_URL ?>/admin/login.php">Login Admin</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Kontak</h4>
        <ul>
          <?php if ($waPusat): ?><li><a href="<?= e(wa_link($waPusat, 'Halo Hoki Dimsum')) ?>">WA: <?= e($waPusat) ?></a></li><?php endif; ?>
          <li><a href="<?= BASE_URL ?>/cabang.php">Lihat semua cabang</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Sekali Coba, Ketagihan Selamanya.
    </div>
  </div>
</footer>

<?php if ($waPusat): ?>
<a class="fab-wa" href="<?= e(wa_link($waPusat, 'Halo Hoki Dimsum, saya mau order.')) ?>" target="_blank" rel="noopener" aria-label="Chat WhatsApp">💬</a>
<?php endif; ?>

<script>window.APP_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
