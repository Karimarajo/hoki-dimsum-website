<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
$path = substr(trim($data['path'] ?? $_GET['path'] ?? ''), 0, 255);

if ($path === '') {
    echo json_encode(['ok' => false]);
    exit;
}

if (empty($_COOKIE['hoki_vid'])) {
    $visitorId = bin2hex(random_bytes(16));
    setcookie('hoki_vid', $visitorId, time() + 60 * 60 * 24 * 365, '/', '', !empty($_SERVER['HTTPS']), true);
} else {
    $visitorId = $_COOKIE['hoki_vid'];
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$deviceType = parse_device_type($userAgent);
$browser = parse_browser_name($userAgent);

db()->prepare('INSERT INTO page_visits (visitor_id, path, device_type, browser) VALUES (?, ?, ?, ?)')
    ->execute([$visitorId, $path, $deviceType, $browser]);

echo json_encode(['ok' => true]);
