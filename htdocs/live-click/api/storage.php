<?php
/**
 * Fair-use opslagverbruik van de huidige gebruiker (som van geleide bands).
 */
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/storage.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

echo json_encode(['ok' => true, 'usage' => userStorageUsage((int)currentUser()['id'])]);
