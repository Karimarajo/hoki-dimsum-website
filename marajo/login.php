<?php
require_once __DIR__ . '/includes/auth.php';

if (marajo_current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if (isset($_GET['err']) && $_GET['err'] === 'sso') {
    $error = 'Link akses dari dashboard utama sudah tidak valid / kadaluarsa. Silakan login manual, atau buka lagi lewat menu Executive di Hoki POS.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    if ($u === '' || $p === '') {
        $error = 'Username dan password wajib diisi.';
    } elseif (!is_allowed_username($u)) {
        $error = 'Akun ini tidak memiliki akses ke Marajo.';
    } else {
        $row = verify_main_credentials($u, $p);
        if ($row) {
            marajo_login($row['username']);
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Login · Marajo</title>
<link rel="icon" type="image/png" href="assets/favicon-32.png?v=<?= (int)@filemtime(__DIR__ . '/assets/favicon-32.png') ?>">
<link rel="apple-touch-icon" href="assets/logo-128.png?v=<?= (int)@filemtime(__DIR__ . '/assets/logo-128.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= (int)@filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <img class="login-logo-img" src="assets/logo-128.png?v=<?= (int)@filemtime(__DIR__ . '/assets/logo-128.png') ?>" alt="PT Marajo Barokah">
        <div class="login-title">PT Marajo Barokah</div>
        <div class="login-sub">Login pakai akun Hoki POS kamu (kurniarp / hanazaf)</div>

        <?php if ($error): ?>
        <div class="login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary">Masuk</button>
        </form>

        <div class="login-note">Akses dibatasi khusus untuk akun tertentu di sistem Hoki Dimsum POS.</div>
    </div>
</div>
</body>
</html>
