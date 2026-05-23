<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/DrumSvg.php';

$data     = json_decode(file_get_contents('php://input'), true) ?: [];
$notation = trim($data['notation'] ?? '');

if ($notation === '') {
    echo json_encode(['ok' => true, 'svg' => '']);
    exit;
}

echo json_encode(['ok' => true, 'svg' => DrumSvg::render($notation)]);
