<?php
/**
 * api/preview.php
 * Geeft een verse Spotify preview_url terug voor een track-id.
 * De opgeslagen URL in de database verloopt snel; dit endpoint haalt er
 * altijd een verse op via de Spotify Tracks API (geen DRM — gewone MP3).
 *
 * GET ?spotify_id=<track_id>
 * Response: { ok: true, preview_url: "https://..." }
 *        of { ok: false, error: "..." }
 */
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/config.php';
requireLogin();
header('Content-Type: application/json');

$spotifyId = trim($_GET['spotify_id'] ?? '');
if (!$spotifyId || !preg_match('/^[A-Za-z0-9]+$/', $spotifyId)) {
    echo json_encode(['ok' => false, 'error' => 'Ongeldig Spotify ID']);
    exit;
}

if (!defined('SPOTIFY_CLIENT_ID') || !SPOTIFY_CLIENT_ID || !SPOTIFY_CLIENT_SECRET) {
    echo json_encode(['ok' => false, 'error' => 'Geen Spotify-credentials geconfigureerd']);
    exit;
}

$token = _pv_getToken();
if (!$token) {
    echo json_encode(['ok' => false, 'error' => 'Spotify-token ophalen mislukt']);
    exit;
}

$raw = _pv_get(
    'https://api.spotify.com/v1/tracks/' . rawurlencode($spotifyId),
    ['Authorization: Bearer ' . $token, 'Accept: application/json']
);

if ($raw === false) {
    echo json_encode(['ok' => false, 'error' => 'Spotify API niet bereikbaar']);
    exit;
}

$track      = json_decode($raw, true);
$previewUrl = $track['preview_url'] ?? null;

echo json_encode(['ok' => true, 'preview_url' => $previewUrl]);

/* ── helpers ─────────────────────────────────────────────────────────── */

function _pv_get(string $url, array $headers): string|false
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code === 200) ? $body : false;
    }
    // Fallback: file_get_contents
    $ctx = stream_context_create(['http' => [
        'timeout' => 6,
        'header'  => implode("\r\n", $headers),
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    return ($raw !== false) ? $raw : false;
}

function _pv_getToken(): string|false
{
    $cacheFile = SPOTIFY_TOKEN_CACHE_FILE;
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && $cache['expires_at'] > time() + 60) return $cache['token'];
    }
    $credentials = base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET);
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://accounts.spotify.com/api/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'timeout' => 8,
            'header'  => "Authorization: Basic $credentials\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => 'grant_type=client_credentials',
        ]]);
        $raw = @file_get_contents('https://accounts.spotify.com/api/token', false, $ctx);
    }
    if (!$raw) return false;
    $data = json_decode($raw, true);
    if (empty($data['access_token'])) return false;
    $cache = ['token' => $data['access_token'], 'expires_at' => time() + (int)($data['expires_in'] ?? 3600)];
    @file_put_contents($cacheFile, json_encode($cache));
    return $cache['token'];
}
