<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

function getSetlistWithSongs(PDO $db, int $id): ?array {
    $sl = $db->prepare('SELECT * FROM setlists WHERE id=?');
    $sl->execute([$id]);
    $setlist = $sl->fetch();
    if (!$setlist) return null;
    $songs = $db->prepare(
        'SELECT s.*, ss.position FROM setlist_songs ss JOIN songs s ON s.id=ss.song_id WHERE ss.setlist_id=? ORDER BY ss.position'
    );
    $songs->execute([$id]);
    $setlist['songs'] = $songs->fetchAll();
    return $setlist;
}

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $bandId = getSetlistBandId($id);
        if ($bandId === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Setlist niet gevonden.']);
            exit;
        }
        requireBandAccess($bandId);
        echo json_encode(['ok'=>true, 'setlist'=>getSetlistWithSongs($db, $id)]);
        exit;
    }
    $bandId = (int)($_GET['band_id'] ?? 0);
    if (!$bandId) { echo json_encode(['ok'=>true,'setlists'=>[]]); exit; }
    requireBandAccess($bandId);

    $stmt = $db->prepare('SELECT * FROM setlists WHERE band_id=? ORDER BY name COLLATE NOCASE ASC');
    $stmt->execute([$bandId]);
    $lists = $stmt->fetchAll();
    foreach ($lists as &$sl) {
        $songs = $db->prepare(
            'SELECT s.*, ss.position FROM setlist_songs ss JOIN songs s ON s.id=ss.song_id WHERE ss.setlist_id=? ORDER BY ss.position'
        );
        $songs->execute([$sl['id']]);
        $sl['songs'] = $songs->fetchAll();
    }
    echo json_encode(['ok'=>true,'setlists'=>$lists]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id     = (int)($data['id'] ?? 0);
    $name   = trim($data['name'] ?? '');
    $bandId = (int)($data['band_id'] ?? 0);
    $songs  = $data['songs'] ?? [];
    // E2EE: bij een versleutelde band zit de naam in enc_blob i.p.v. name.
    $encBlob = isset($data['enc_blob']) && trim($data['enc_blob']) !== '' ? trim($data['enc_blob']) : null;

    if (!$bandId) { echo json_encode(['ok'=>false,'error'=>'Band verplicht']); exit; }
    requireBandContentAccess($bandId);

    $encrypted = bandIsEncrypted($bandId);
    if ($encrypted) {
        if ($encBlob === null) { echo json_encode(['ok'=>false,'error'=>'Deze band is versleuteld; geen versleutelde naam meegegeven.']); exit; }
        if (strlen($encBlob) > 20000) { echo json_encode(['ok'=>false,'error'=>'Naam te lang.']); exit; }
        $name = ''; // placeholder; echte naam zit in enc_blob
    } elseif (!$name) {
        echo json_encode(['ok'=>false,'error'=>'Naam en band verplicht']); exit;
    }

    if ($id) {
        // Bij update: controleer huidige eigenaarband (mag niet via "id" naar andere band gesleept worden zonder toegang).
        $currentBandId = getSetlistBandId($id);
        if ($currentBandId === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Setlist niet gevonden.']);
            exit;
        }
        if (!userCanEditBandContent((int)$currentBandId)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Je hebt alleen leesrechten voor deze setlist.']);
            exit;
        }
        $db->prepare('UPDATE setlists SET name=?, enc_blob=? WHERE id=?')->execute([$name, $encBlob, $id]);
        $db->prepare('DELETE FROM setlist_songs WHERE setlist_id=?')->execute([$id]);
    } else {
        $db->prepare('INSERT INTO setlists (name,band_id,created_by,enc_blob) VALUES (?,?,?,?)')->execute([$name,$bandId,currentUser()['id'],$encBlob]);
        $id = $db->lastInsertId();
    }

    // Alleen songs accepteren die tot de doelband behoren
    if ($songs) {
        $placeholders = implode(',', array_fill(0, count($songs), '?'));
        $check = $db->prepare("SELECT id FROM songs WHERE band_id=? AND id IN ($placeholders)");
        $check->execute(array_merge([$bandId], array_map('intval', $songs)));
        $allowed = array_flip(array_map('intval', $check->fetchAll(PDO::FETCH_COLUMN)));

        $ins = $db->prepare('INSERT INTO setlist_songs (setlist_id,song_id,position) VALUES (?,?,?)');
        $pos = 0;
        foreach ($songs as $songId) {
            $sid = (int)$songId;
            if (!isset($allowed[$sid])) continue;
            $ins->execute([$id, $sid, $pos++]);
        }
    }
    echo json_encode(['ok'=>true,'id'=>$id]);
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'Geen id']); exit; }

    $bandId = getSetlistBandId($id);
    if ($bandId === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Setlist niet gevonden.']);
        exit;
    }
    if (!userCanEditBandContent((int)$bandId)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Je hebt alleen leesrechten voor deze setlist.']);
        exit;
    }
    $db->prepare('DELETE FROM setlists WHERE id=?')->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
