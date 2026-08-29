<?php
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . (marajo_current_user() ? 'dashboard.php' : 'login.php'));
exit;
