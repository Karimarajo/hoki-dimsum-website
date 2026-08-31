<?php
require_once __DIR__ . '/includes/auth.php';
marajo_logout();
header('Location: login.php');
exit;
