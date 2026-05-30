<?php
/**
 * Songtekst ophalen via lyrics.ovh (gratis, geen API-sleutel nodig) en
 * opslaan bij het nummer. Daarna staat de tekst in de DB + offline-cache.
 *
 * Bron is bewust eenvoudig te vervangen: pas alleen fetchLyrics() hieronder
 * aan om een andere bron (Genius/Musixmatch met sleutel) te gebruiken.
 *
 * Let op: songteksten zijn auteursrechtelijk beschermd. Dit endpoint is
 * bedoeld voor intern bandgebruik.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
csrfRequire();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id   = (int)($data['song_id'] ?? 0);
if (!$id) { echo json_encode(['ok' => false, 'error' => 'Geen nummer-id']); exit; }

$db   = getDB();
$stmt = $db->prepare('SELECT id, title, artist, band_id FROM songs WHERE id = ?');
$stmt->execute([$id]);
$song = $stmt->fetch();

if (!$song) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Nummer niet gevonden.']);
    exit;
}
// Songtekst ophalen schrijft naar het nummer → vereist bewerkrechten (geen viewer).
if (!userCanEditBandContent($song['band_id'] !== null ? (int)$song['band_id'] : 0)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Je hebt alleen leesrechten voor dit nummer.']);
    exit;
}

$title  = trim($song['title']);
$artist = trim($song['artist']);
if ($title === '' || $artist === '') {
    echo json_encode(['ok' => false, 'error' => 'Titel en artiest zijn nodig om te zoeken.']);
    exit;
}

$lyrics = fetchLyrics($artist, $title);
if ($lyrics === null) {
    echo json_encode(['ok' => false, 'error' => 'Geen songtekst gevonden voor "' . $title . '".']);
    exit;
}

// Normaliseer regeleindes en begrens de lengte (zelfde grens als songs.php)
$lyrics = str_replace(["\r\n", "\r"], "\n", $lyrics);
if (strlen($lyrics) > 20000) $lyrics = substr($lyrics, 0, 20000);

try {
    $db->prepare('UPDATE songs SET lyrics = ? WHERE id = ?')->execute([$lyrics, $id]);
} catch (PDOException $e) {
    error_log('lyrics.php PDO: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database fout bij opslaan.']);
    exit;
}

echo json_encode(['ok' => true, 'lyrics' => $lyrics]);

/* =========================================
   Bron: lyrics.ovh
   ========================================= */
function fetchLyrics(string $artist, string $title): ?string {
    $url = 'https://api.lyrics.ovh/v1/' . rawurlencode($artist) . '/' . rawurlencode($title);
    $raw = lyricsHttpGet($url);
    if (!$raw) return null;

    $j = json_decode($raw, true);
    if (is_array($j) && isset($j['lyrics']) && trim($j['lyrics']) !== '') {
        return trim($j['lyrics']);
    }
    return null;
}

/** Simpele HTTP GET (cURL met file_get_contents-fallback). Body bij HTTP 200, anders null. */
function lyricsHttpGet(string $url, int $timeout = 8): ?string {
    $headers = ['Accept: application/json', 'User-Agent: LiveGig/1.0 (band tool)'];

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code === 200) ? $body : null;
    }

    $ctx = stream_context_create(['http' => [
        'timeout'       => $timeout,
        'ignore_errors' => true,
        'header'        => implode("\r\n", $headers),
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw !== false ? $raw : null;
}
