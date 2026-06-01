<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = currentUser();

function canManageBand(PDO $db, int $bandId, array $user): bool {
    // Alleen de bandleider; de globale admin heeft hier bewust GEEN rechten
    // (deellinks bevatten band-inhoud — zie PRIVACY.md).
    $s = $db->prepare("SELECT 1 FROM band_members WHERE band_id=? AND user_id=? AND role='leader'");
    $s->execute([$bandId, $user['id']]);
    return (bool)$s->fetch();
}

/* GET ?band_id=N  — bestaat er een token? (plaintext wordt nooit teruggestuurd)
   Geeft ook of de band versleuteld is, zodat de client weet of er een
   versleutelde projectie (share_blob) meegestuurd moet worden. */
if ($method === 'GET') {
    $bandId = (int)($_GET['band_id'] ?? 0);
    if (!$bandId) { echo json_encode(['ok' => false, 'error' => 'band_id ontbreekt']); exit; }
    if (!canManageBand($db, $bandId, $user)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }
    $row = $db->prepare('SELECT share_token, is_encrypted FROM bands WHERE id=?');
    $row->execute([$bandId]);
    $b = $row->fetch() ?: [];
    echo json_encode([
        'ok'           => true,
        'has_token'    => !empty($b['share_token']),
        'is_encrypted' => (int)($b['is_encrypted'] ?? 0) === 1,
    ]);
    exit;
}

/* POST {band_id [, share_blob] [, update_only]}
   - niet-versleutelde band: server rendert public.php uit plaintext → geen blob.
   - versleutelde band: client levert share_blob (versleutelde projectie). De
     deelsleutel zit NOOIT in deze request; die leeft alleen in de URL-fragment.
   - update_only=true: alleen de blob verversen, bestaand token behouden. */
if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $bandId = (int)($data['band_id'] ?? 0);
    if (!$bandId) { echo json_encode(['ok' => false, 'error' => 'band_id ontbreekt']); exit; }
    if (!canManageBand($db, $bandId, $user)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }

    $encrypted = bandIsEncrypted($bandId);
    $shareBlob = isset($data['share_blob']) && trim($data['share_blob']) !== '' ? trim($data['share_blob']) : null;
    $updateOnly = !empty($data['update_only']);

    if ($encrypted) {
        if ($shareBlob === null) { echo json_encode(['ok'=>false,'error'=>'Versleutelde band vereist een projectie.']); exit; }
        if (strlen($shareBlob) > 2000000) { echo json_encode(['ok'=>false,'error'=>'Projectie te groot.']); exit; }
    } else {
        $shareBlob = null; // niet-versleutelde band gebruikt geen blob
    }

    if ($updateOnly) {
        // Alleen de projectie verversen; token moet al bestaan.
        $cur = $db->prepare('SELECT share_token FROM bands WHERE id=?');
        $cur->execute([$bandId]);
        if (empty($cur->fetchColumn())) { echo json_encode(['ok'=>false,'error'=>'Geen actieve deellink.']); exit; }
        $db->prepare('UPDATE bands SET share_blob=? WHERE id=?')->execute([$shareBlob, $bandId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    $token = bin2hex(random_bytes(16));
    $hash  = hash('sha256', $token);
    $db->prepare('UPDATE bands SET share_token=?, share_blob=? WHERE id=?')->execute([$hash, $shareBlob, $bandId]);
    echo json_encode(['ok' => true, 'token' => $token]);
    exit;
}

/* DELETE {band_id}  — token + projectie intrekken */
if ($method === 'DELETE') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $bandId = (int)($data['band_id'] ?? 0);
    if (!$bandId) { echo json_encode(['ok' => false, 'error' => 'band_id ontbreekt']); exit; }
    if (!canManageBand($db, $bandId, $user)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }
    $db->prepare('UPDATE bands SET share_token=NULL, share_blob=NULL WHERE id=?')->execute([$bandId]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
