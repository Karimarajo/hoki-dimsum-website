<?php
require_once __DIR__ . '/../includes/auth.php';

if (!empty($_SESSION['admin_id'])) {
    redirect(BASE_URL . '/admin/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rate_limit_ok('admin_login', 3)) {
        $error = 'Terlalu banyak percobaan, coba lagi sebentar.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            redirect(BASE_URL . '/admin/dashboard.php');
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login Admin — <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= BASE_URL ?>/assets/2.png">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <div class="brand"><span class="logo-mark"><img src="<?= BASE_URL ?>/assets/2.png" alt="<?= e(APP_NAME) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span><span><?= e(APP_NAME) ?></span></div>
    <p class="sub">Login ke Dashboard Admin</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" class="form-control" required autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Masuk</button>
    </form>
  </div>
</div>
</body>
</html>
