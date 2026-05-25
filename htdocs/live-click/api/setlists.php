<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
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
        $sl = getSetlistWithSongs($db, (int)$_GET['id']);
        echo json_encode(['ok'=>true,'setlist'=>$sl]);
        exit;
    }
    $bandId = (int)($_GET['band_id'] ?? 0);
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

    if (!$name || !$bandId) { echo json_encode(['ok'=>false,'error'=>'Naam en band verplicht']); exit; }

    if ($id) {
        $db->prepare('UPDATE setlists SET name=? WHERE id=?')->execute([$name,$id]);
        $db->prepare('DELETE FROM setlist_songs WHERE setlist_id=?')->execute([$id]);
    } else {
        $db->prepare('INSERT INTO setlists (name,band_id,created_by) VALUES (?,?,?)')->execute([$name,$bandId,currentUser()['id']]);
        $id = $db->lastInsertId();
    }

    $ins = $db->prepare('INSERT INTO setlist_songs (setlist_id,song_id,position) VALUES (?,?,?)');
    foreach ($songs as $pos => $songId) {
        $ins->execute([$id,(int)$songId,$pos]);
    }
    echo json_encode(['ok'=>true,'id'=>$id]);
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    $db->prepare('DELETE FROM setlists WHERE id=?')->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
