<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/storage.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $bandId = (int)($_GET['band_id'] ?? 0);
    if (!$bandId) {
        // No band filter → return nothing (prevents leaking songs from other bands)
        echo json_encode(['ok' => true, 'songs' => []]);
        exit;
    }
    requireBandAccess($bandId);
    // drum_svg is included so the dashboard can work offline (cached in localStorage)
    $cols = 'id,title,artist,bpm,song_key,duration,starts,description,preview_url,spotify_id,drum_notation,drum_svg,lyrics,chords,pdf_path,enc_blob,band_id,created_by,created_at';
    $stmt = $db->prepare("SELECT $cols FROM songs WHERE band_id = ? ORDER BY title COLLATE NOCASE");
    $stmt->execute([$bandId]);
    echo json_encode(['ok' => true, 'songs' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id     = (int)($data['id'] ?? 0);
    $title  = trim($data['title'] ?? '');
    $artist = trim($data['artist'] ?? '');
    // Titel/artiest zijn verplicht behalve bij een versleutelde band (dan zitten
    // ze in enc_blob). De definitieve check gebeurt na de band-/encryptiebepaling.
    $hasEncBlobInput = isset($data['enc_blob']) && trim($data['enc_blob']) !== '';
    if (!$hasEncBlobInput && (!$title || !$artist)) {
        echo json_encode(['ok'=>false,'error'=>'Titel en artiest verplicht']); exit;
    }

    // E2EE: voor versleutelde bands stuurt de client één versleutelde blob i.p.v.
    // losse plaintext-velden. We bepalen verderop (na de band-bepaling) of de
    // band versleuteld is en valideren dienovereenkomstig.
    $encBlob = isset($data['enc_blob']) && trim($data['enc_blob']) !== '' ? trim($data['enc_blob']) : null;

    $bpm        = isset($data['bpm']) && $data['bpm'] !== '' ? (int)$data['bpm'] : null;
    $key        = trim($data['song_key']    ?? '') ?: null;
    $dur        = trim($data['duration']    ?? '') ?: null;
    $starts     = trim($data['starts']      ?? '') ?: null;
    $desc       = trim($data['description'] ?? '') ?: null;
    $previewUrl = trim($data['preview_url'] ?? '') ?: null;
    $spotifyId  = trim($data['spotify_id']  ?? '') ?: null;
    $drumNotation = trim($data['drum_notation'] ?? '') ?: null;
    $lyrics       = trim($data['lyrics'] ?? '') ?: null;
    $chords       = trim($data['chords'] ?? '') ?: null;

    // Bescherming tegen DoS via gigantische velden (M4)
    if (strlen($desc ?? '') > 4000 || strlen($drumNotation ?? '') > 5000
        || strlen($lyrics ?? '') > 20000 || strlen($chords ?? '') > 20000
        || strlen($encBlob ?? '') > 400000) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'error' => 'Een van de velden is te lang.']);
        exit;
    }

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

    // Autorisatie:
    //   - Nieuwe song: gebruiker moet lid zijn van de doel-band (of admin).
    //   - Bestaande song: gebruiker moet lid zijn van de HUIDIGE band én van de doel-band.
    if ($id) {
        $currentBandId = getSongBandId($id);
        if ($currentBandId === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Nummer niet gevonden.']);
            exit;
        }
        if (!userCanEditBandContent((int)$currentBandId)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Je hebt alleen leesrechten voor dit nummer.']);
            exit;
        }
    }
    if ($bandId !== null && !userCanEditBandContent($bandId)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Je hebt alleen leesrechten voor de doel-band.']);
        exit;
    }

    // Is de doelband versleuteld? (update: huidige band; insert: doelband)
    $effectiveBandId = $id ? (int)($currentBandId ?? 0) : (int)($bandId ?? 0);
    $encrypted = bandIsEncrypted($effectiveBandId);

    if ($encrypted && $encBlob === null) {
        echo json_encode(['ok'=>false,'error'=>'Deze band is versleuteld; er is geen versleutelde inhoud meegegeven.']);
        exit;
    }

    // Fair-use: blokkeer opslaan als de NIEUWE inhoud het opslagquotum van de
    // bandeigenaar (de leider) overschrijdt. We kijken naar het verschil met de
    // bestaande versie: inkorten of gelijk blijven mag altijd, ook al zit je al
    // op de limiet — zo kun je altijd ruimte vrijmaken.
    if ($effectiveBandId) {
        $ownerId = bandStorageOwnerId($effectiveBandId);
        if ($ownerId !== null) {
            $newBytes = $encrypted
                ? strlen((string)$encBlob)
                : strlen((string)$title) + strlen((string)$artist) + strlen((string)$desc)
                  + strlen((string)$drumNotation) + strlen((string)$drumSvg)
                  + strlen((string)$lyrics) + strlen((string)$chords);
            $oldBytes = $id ? songStorageBytes((int)$id) : 0;
            $delta    = $newBytes - $oldBytes;
            if ($delta > 0) {
                $usage = userStorageUsage($ownerId);
                if ($usage['used_bytes'] + $delta > $usage['quota_bytes']) {
                    http_response_code(413);
                    echo json_encode([
                        'ok'         => false,
                        'over_quota' => true,
                        'error'      => 'Opslaglimiet (fair-use) bereikt. Kort de inhoud in of verwijder iets om ruimte vrij te maken.',
                    ]);
                    exit;
                }
            }
        }
    }

    try {
        if ($encrypted) {
            // Versleutelde band: alleen de blob bewaren. Plaintext-kolommen leeg
            // (title/artist zijn NOT NULL → lege string als placeholder).
            if ($id) {
                $db->prepare("UPDATE songs SET title='',artist='',bpm=NULL,song_key=NULL,duration=NULL,starts=NULL,description=NULL,preview_url=NULL,spotify_id=NULL,drum_notation=NULL,drum_svg=NULL,drum_svg_updated_at=NULL,lyrics=NULL,chords=NULL,enc_blob=? WHERE id=?")
                   ->execute([$encBlob, $id]);
            } else {
                $db->prepare("INSERT INTO songs (title,artist,band_id,created_by,enc_blob) VALUES ('','',?,?,?)")
                   ->execute([$bandId, currentUser()['id'], $encBlob]);
                $id = $db->lastInsertId();
            }
        } elseif ($id) {
            $db->prepare('UPDATE songs SET title=?,artist=?,bpm=?,song_key=?,duration=?,starts=?,description=?,preview_url=?,spotify_id=?,drum_notation=?,drum_svg=?,drum_svg_updated_at=?,lyrics=?,chords=? WHERE id=?')
               ->execute([$title, $artist, $bpm, $key, $dur, $starts, $desc, $previewUrl, $spotifyId, $drumNotation, $drumSvg, $drumSvgUpdatedAt, $lyrics, $chords, $id]);
        } else {
            $db->prepare('INSERT INTO songs (title,artist,bpm,song_key,duration,starts,description,preview_url,spotify_id,drum_notation,drum_svg,drum_svg_updated_at,lyrics,chords,band_id,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
               ->execute([$title, $artist, $bpm, $key, $dur, $starts, $desc, $previewUrl, $spotifyId, $drumNotation, $drumSvg, $drumSvgUpdatedAt, $lyrics, $chords, $bandId, currentUser()['id']]);
            $id = $db->lastInsertId();
        }
        echo json_encode(['ok' => true, 'id' => $id]);
    } catch (PDOException $e) {
        error_log('songs.php PDO: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database fout bij opslaan.']);
    }
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'Geen id']); exit; }

    $bandId = getSongBandId($id);
    if ($bandId === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Nummer niet gevonden.']);
        exit;
    }
    if (!userCanEditBandContent((int)$bandId)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Je hebt alleen leesrechten voor dit nummer.']);
        exit;
    }

    $db->prepare('DELETE FROM songs WHERE id=?')->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
