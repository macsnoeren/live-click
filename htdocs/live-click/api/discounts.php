<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireAdmin();
csrfRequire();
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $db->query(
        'SELECT id, code, type, value, description, duration, max_redemptions,
                times_redeemed, valid_until, active, created_at
           FROM discount_codes
          ORDER BY active DESC, created_at DESC'
    )->fetchAll();
    echo json_encode(['ok' => true, 'codes' => $rows]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    $id          = (int)($data['id'] ?? 0);
    // Code normaliseren: hoofdletters, geen spaties — gebruiksvriendelijk invoeren.
    $code        = strtoupper(preg_replace('/\s+/', '', (string)($data['code'] ?? '')));
    $type        = in_array($data['type'] ?? '', ['percent', 'fixed'], true) ? $data['type'] : 'percent';
    $value       = (float)($data['value'] ?? 0);
    $description = trim((string)($data['description'] ?? ''));
    $duration    = in_array($data['duration'] ?? '', ['once', 'forever'], true) ? $data['duration'] : 'once';
    $maxRed      = isset($data['max_redemptions']) && $data['max_redemptions'] !== ''
                   ? max(0, (int)$data['max_redemptions']) : null;
    $validUntil  = trim((string)($data['valid_until'] ?? '')) ?: null;
    $active      = isset($data['active']) ? (int)(bool)$data['active'] : 1;

    if ($code === '') {
        echo json_encode(['ok' => false, 'error' => 'Code is verplicht.']); exit;
    }
    if ($value <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Waarde moet groter dan 0 zijn.']); exit;
    }
    if ($type === 'percent' && $value > 100) {
        echo json_encode(['ok' => false, 'error' => 'Een percentage kan niet hoger dan 100 zijn.']); exit;
    }

    try {
        if ($id) {
            $db->prepare(
                'UPDATE discount_codes
                    SET code=?, type=?, value=?, description=?, duration=?,
                        max_redemptions=?, valid_until=?, active=?
                  WHERE id=?'
            )->execute([$code, $type, $value, $description, $duration, $maxRed, $validUntil, $active, $id]);
            auditLog('discount.update', 'discount_code', $id, ['code' => $code]);
        } else {
            $db->prepare(
                'INSERT INTO discount_codes
                    (code, type, value, description, duration, max_redemptions, valid_until, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$code, $type, $value, $description, $duration, $maxRed, $validUntil, $active]);
            $id = (int)$db->lastInsertId();
            auditLog('discount.create', 'discount_code', $id, ['code' => $code]);
        }
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'Deze code bestaat al.']); exit;
    }

    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $id   = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'Geen id']); exit; }

    $row = $db->prepare('SELECT code FROM discount_codes WHERE id=?');
    $row->execute([$id]);
    $target = $row->fetch();
    if (!$target) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Code niet gevonden.']); exit; }

    // Abonnementen die naar deze code verwijzen behouden hun korting
    // (discount_code_id wordt NULL via ON DELETE SET NULL).
    $db->prepare('DELETE FROM discount_codes WHERE id=?')->execute([$id]);
    auditLog('discount.delete', 'discount_code', $id, ['code' => $target['code']]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
