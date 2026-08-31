<?php
/**
 * sso.php — Titik masuk dari menu "PT Marajo Barokah" di sidebar Executive
 * dashboard utama (pos-hokidimsum.com). Menerima username + session_token
 * yang sedang aktif di sana, verifikasi ke database utama, lalu buka sesi
 * Marajo sendiri kalau valid.
 *
 *   https://marajo.pos-hokidimsum.com/sso.php?u=kurniarp&t=<sessionToken>
 */

require_once __DIR__ . '/includes/auth.php';

$u = trim($_GET['u'] ?? '');
$t = trim($_GET['t'] ?? '');

if ($u === '' || $t === '' || !is_allowed_username($u) || !verify_main_session($u, $t)) {
    header('Location: login.php?err=sso');
    exit;
}

marajo_login($u);
header('Location: dashboard.php');
exit;
