<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

$db     = getDB();
$user   = currentUser();
$method = $_SERVER['REQUEST_METHOD'];

function canManageInvite(PDO $db, int $bandId, int $userId, bool $isAdmin): bool {
    // Alleen de bandleider; de globale admin beheert geen uitnodigingslinks
    // (uitnodigen = leden toevoegen aan een band — dat is leiderschap, geen
    // accountbeheer). $isAdmin blijft als parameter staan voor de GET-zichtbaarheid.
    $s = $db->prepare("SELECT 1 FROM band_members WHERE band_id=? AND user_id=? AND role='leader'");
    $s->execute([$bandId, $userId]);
    return (bool)$s->fetch();
}

$isAdmin = $user['role'] === 'admin';

if ($method === 'GET') {
    $bandId = (int)($_GET['band_id'] ?? 0);
    // Lidmaatschap is voldoende om te zien dat er een link bestaat;
    // de plaintext wordt nooit teruggestuurd (alleen bij creatie).
    $isMember = false;
    if ($bandId) {
        $s = $db->prepare('SELECT 1 FROM band_members WHERE band_id=? AND user_id=?');
        $s->execute([$bandId, $user['id']]);
        $isMember = (bool)$s->fetch();
    }
    if (!$bandId || (!$isMember && !$isAdmin)) {
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }
    $stmt = $db->prepare('SELECT 1 FROM band_invites WHERE band_id=?');
    $stmt->execute([$bandId]);
    $hasToken = (bool)$stmt->fetchColumn();
    echo json_encode(['ok' => true, 'has_token' => $hasToken]);
    exit;
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $bandId = (int)($data['band_id'] ?? 0);
    if (!$bandId || !canManageInvite($db, $bandId, $user['id'], $isAdmin)) {
        echo json_encode(['ok' => false, 'error' => 'Alleen de bandleider mag uitnodigingslinks beheren']); exit;
    }
    $token = bin2hex(random_bytes(16));
    $hash  = hash('sha256', $token);
    $db->prepare('INSERT INTO band_invites (band_id, token, created_by) VALUES (?,?,?)
                  ON CONFLICT(band_id) DO UPDATE SET token=excluded.token, created_by=excluded.created_by, created_at=CURRENT_TIMESTAMP')
       ->execute([$bandId, $hash, $user['id']]);
    echo json_encode(['ok' => true, 'token' => $token]);
    exit;
}

if ($method === 'DELETE') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $bandId = (int)($data['band_id'] ?? 0);
    if (!$bandId || !canManageInvite($db, $bandId, $user['id'], $isAdmin)) {
        echo json_encode(['ok' => false, 'error' => 'Alleen de bandleider mag uitnodigingslinks beheren']); exit;
    }
    $db->prepare('DELETE FROM band_invites WHERE band_id=?')->execute([$bandId]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
