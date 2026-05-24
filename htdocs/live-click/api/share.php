<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = currentUser();

function canManageBand(PDO $db, int $bandId, array $user): bool {
    if ($user['role'] === 'admin') return true;
    $s = $db->prepare("SELECT 1 FROM band_members WHERE band_id=? AND user_id=? AND role='leader'");
    $s->execute([$bandId, $user['id']]);
    return (bool)$s->fetch();
}

/* GET ?band_id=N  — huidig token ophalen */
if ($method === 'GET') {
    $bandId = (int)($_GET['band_id'] ?? 0);
    if (!$bandId) { echo json_encode(['ok' => false, 'error' => 'band_id ontbreekt']); exit; }
    if (!canManageBand($db, $bandId, $user)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }
    $row = $db->prepare('SELECT share_token FROM bands WHERE id=?');
    $row->execute([$bandId]);
    $token = $row->fetchColumn();
    echo json_encode(['ok' => true, 'token' => $token ?: null]);
    exit;
}

/* POST {band_id}  — nieuw token genereren */
if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $bandId = (int)($data['band_id'] ?? 0);
    if (!$bandId) { echo json_encode(['ok' => false, 'error' => 'band_id ontbreekt']); exit; }
    if (!canManageBand($db, $bandId, $user)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }
    $token = bin2hex(random_bytes(16));
    $db->prepare('UPDATE bands SET share_token=? WHERE id=?')->execute([$token, $bandId]);
    echo json_encode(['ok' => true, 'token' => $token]);
    exit;
}

/* DELETE {band_id}  — token intrekken */
if ($method === 'DELETE') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $bandId = (int)($data['band_id'] ?? 0);
    if (!$bandId) { echo json_encode(['ok' => false, 'error' => 'band_id ontbreekt']); exit; }
    if (!canManageBand($db, $bandId, $user)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }
    $db->prepare('UPDATE bands SET share_token=NULL WHERE id=?')->execute([$bandId]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
