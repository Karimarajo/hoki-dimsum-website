<?php
/**
 * auth.php — Session guard aplikasi Marajo (terpisah dari session Hoki Dimsum,
 * karena beda subdomain / origin). Login masuk lewat sso.php (SSO dari
 * dashboard utama) atau login.php (fallback manual).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_main.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('marajo_session');
    session_start();
}

function marajo_login(string $username): void
{
    session_regenerate_id(true);
    $_SESSION['marajo_user']     = $username;
    $_SESSION['marajo_login_at'] = time();
}

function marajo_current_user(): ?string
{
    $u = $_SESSION['marajo_user'] ?? null;
    return ($u && is_allowed_username($u)) ? $u : null;
}

/** Panggil di awal setiap halaman yang butuh login. Redirect ke login.php kalau belum. */
function marajo_require_login(): string
{
    $user = marajo_current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

/** Versi untuk api.php — kirim JSON 401 alih-alih redirect. */
function marajo_require_login_api(): string
{
    $user = marajo_current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Sesi habis, silakan login ulang.']);
        exit;
    }
    return $user;
}

function marajo_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
