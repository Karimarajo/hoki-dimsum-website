    </div>
</div>

<script src="assets/toast.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/toast.js') ?>"></script>
<script src="assets/app.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/app.js') ?>"></script>
<?php if (!empty($extraScript)): ?>
<script><?= $extraScript ?></script>
<?php endif; ?>
</body>
</html>
