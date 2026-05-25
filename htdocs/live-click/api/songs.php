<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $bandId = $_GET['band_id'] ?? null;
    // drum_svg is included so the dashboard can work offline (cached in localStorage)
    $cols = 'id,title,artist,bpm,song_key,duration,starts,description,preview_url,spotify_id,drum_notation,drum_svg,band_id,created_by,created_at';
    if (!$bandId) {
        // No band filter → return nothing (prevents leaking songs from other bands)
        echo json_encode(['ok' => true, 'songs' => []]);
        exit;
    }
    $stmt = $db->prepare("SELECT $cols FROM songs WHERE band_id = ? ORDER BY title COLLATE NOCASE");
    $stmt->execute([(int)$bandId]);
    echo json_encode(['ok' => true, 'songs' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id     = (int)($data['id'] ?? 0);
    $title  = trim($data['title'] ?? '');
    $artist = trim($data['artist'] ?? '');
    if (!$title || !$artist) { echo json_encode(['ok'=>false,'error'=>'Titel en artiest verplicht']); exit; }

    $bpm        = isset($data['bpm']) && $data['bpm'] !== '' ? (int)$data['bpm'] : null;
    $key        = trim($data['song_key']    ?? '') ?: null;
    $dur        = trim($data['duration']    ?? '') ?: null;
    $starts     = trim($data['starts']      ?? '') ?: null;
    $desc       = trim($data['description'] ?? '') ?: null;
    $previewUrl = trim($data['preview_url'] ?? '') ?: null;
    $spotifyId  = trim($data['spotify_id']  ?? '') ?: null;
    $drumNotation = trim($data['drum_notation'] ?? '') ?: null;
    // Regenerate SVG whenever notation is saved
    $drumSvg = null;
    $drumSvgUpdatedAt = null;
    if ($drumNotation !== null) {
        require_once APP_ROOT . '/includes/DrumSvg.php';
        $drumSvg = DrumSvg::render($drumNotation) ?: null;
        $drumSvgUpdatedAt = date('Y-m-d H:i:s');
    }
    // band_id: treat empty string / "null" / 0 all as NULL
    $rawBand = $data['band_id'] ?? null;
    $bandId  = ($rawBand !== null && $rawBand !== '' && $rawBand !== 'null' && (int)$rawBand > 0)
               ? (int)$rawBand : null;

    try {
        if ($id) {
            $db->prepare('UPDATE songs SET title=?,artist=?,bpm=?,song_key=?,duration=?,starts=?,description=?,preview_url=?,spotify_id=?,drum_notation=?,drum_svg=?,drum_svg_updated_at=? WHERE id=?')
               ->execute([$title, $artist, $bpm, $key, $dur, $starts, $desc, $previewUrl, $spotifyId, $drumNotation, $drumSvg, $drumSvgUpdatedAt, $id]);
        } else {
            $db->prepare('INSERT INTO songs (title,artist,bpm,song_key,duration,starts,description,preview_url,spotify_id,drum_notation,drum_svg,drum_svg_updated_at,band_id,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
               ->execute([$title, $artist, $bpm, $key, $dur, $starts, $desc, $previewUrl, $spotifyId, $drumNotation, $drumSvg, $drumSvgUpdatedAt, $bandId, currentUser()['id']]);
            $id = $db->lastInsertId();
        }
        echo json_encode(['ok' => true, 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'Geen id']); exit; }
    $db->prepare('DELETE FROM songs WHERE id=?')->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
