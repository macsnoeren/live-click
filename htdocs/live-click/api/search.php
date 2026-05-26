<?php
require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/config.php';
requireLogin();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (!$q) { echo json_encode(['ok' => false, 'results' => [], 'source' => 'none']); exit; }

// Local library lookup — aggregate per title+artist to avoid duplicates.
// Beperk tot bands waar de huidige gebruiker lid van is (admin ziet alles).
$db   = getDB();
$like = '%' . $q . '%';
$user = currentUser();

if ($user['role'] === 'admin') {
    $stmt = $db->prepare(
        'SELECT title, artist, MAX(bpm) AS bpm, MAX(song_key) AS song_key, MAX(duration) AS duration
           FROM songs
          WHERE LOWER(title)  LIKE LOWER(?)
             OR LOWER(artist) LIKE LOWER(?)
          GROUP BY title, artist
          ORDER BY title COLLATE NOCASE
          LIMIT 8'
    );
    $stmt->execute([$like, $like]);
} else {
    $myBands = userBands((int)$user['id']);
    if (!$myBands) {
        $dbHits = [];
    } else {
        $ids = array_map(fn($b) => (int)$b['id'], $myBands);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT title, artist, MAX(bpm) AS bpm, MAX(song_key) AS song_key, MAX(duration) AS duration
               FROM songs
              WHERE band_id IN ($ph)
                AND (LOWER(title)  LIKE LOWER(?)
                  OR LOWER(artist) LIKE LOWER(?))
              GROUP BY title, artist
              ORDER BY title COLLATE NOCASE
              LIMIT 8"
        );
        $stmt->execute(array_merge($ids, [$like, $like]));
    }
}
if (!isset($dbHits)) $dbHits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Priority: 1. Tunebat  2. GetSongBPM  3. Spotify  4. MusicBrainz
$results = searchTunebat($q);
$source  = 'tunebat';

if (!$results && GETSONGBPM_API_KEY) {
    $results = searchGetSongBpm($q);
    $source  = 'getsongbpm';
}

$spotifyNoBpm = false;
if (!$results && SPOTIFY_CLIENT_ID && SPOTIFY_CLIENT_SECRET) {
    $results = searchSpotify($q, $spotifyNoBpm);
    $source  = 'spotify';
}

if (!$results) {
    $results = searchMusicBrainz($q);
    $source  = 'musicbrainz';
}

echo json_encode(['ok' => true, 'results' => $results, 'source' => $source, 'spotify_no_bpm' => $spotifyNoBpm, 'db_hits' => $dbHits]);

/* =========================================
   Helpers
   ========================================= */

/**
 * Camelot Wheel: maps (key 0-11, mode 1=maj/0=min) to notation like "8B", "5A".
 * Useful for harmonic mixing — adjacent numbers are compatible keys.
 */
function computeCamelot(int $keyIdx, int $mode): string {
    static $maj = ['8B','3B','10B','5B','12B','7B','2B','9B','4B','11B','6B','1B'];
    static $min = ['5A','12A','7A','2A','9A','4A','11A','6A','1A','8A','3A','10A'];
    if ($keyIdx < 0 || $keyIdx > 11) return '';
    return $mode === 1 ? $maj[$keyIdx] : $min[$keyIdx];
}

function buildKeyFields(int $keyIdx, int $mode): array {
    static $names = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];
    if ($keyIdx < 0 || $keyIdx > 11) return ['key' => null, 'camelot' => null];
    return [
        'key'     => $names[$keyIdx] . ($mode === 1 ? ' maj' : ' min'),
        'camelot' => computeCamelot($keyIdx, $mode),
    ];
}

function msDuration(int $ms): string {
    return sprintf('%d:%02d', floor($ms / 60000), floor(($ms % 60000) / 1000));
}

/* =========================================
   HTTP helpers
   ========================================= */

/** cURL GET — returns body string or false. */
function curlGet(string $url, array $headers, int $timeout = 8): string|false {
    if (!function_exists('curl_init')) return false;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',   // auto decode gzip/deflate/br
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code !== 200) return false;
    // Guard against Cloudflare HTML challenge pages
    $first = ltrim($body)[0] ?? '';
    if ($first !== '{' && $first !== '[') return false;
    return $body;
}

/** file_get_contents fallback for environments without cURL. */
function httpGet(string $url, array $headerLines, int $timeout = 8): string|false {
    $ctx = stream_context_create(['http' => [
        'timeout'       => $timeout,
        'ignore_errors' => true,
        'header'        => implode("\r\n", $headerLines),
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return false;
    $first = ltrim($raw)[0] ?? '';
    if ($first !== '{' && $first !== '[') return false;
    return $raw;
}

/* =========================================
   Tunebat  (Spotify data, no credentials needed)
   Field map (compact single-letter keys):
     a  = title
     b  = artist(s) — string or array
     f  = duration_ms
     j  = tempo / BPM
     k  = key index 0-11 (C,C#,D,D#,E,F,F#,G,G#,A,A#,B)
     l  = mode: 1=major, 0=minor
     m  = energy 0-1
     n  = danceability 0-1
     o  = valence 0-1 (happiness/positivity)
     p  = popularity 0-100 (integer)
   ========================================= */
function searchTunebat(string $q): array {
    $url = 'https://api.tunebat.com/api/tracks/data?q=' . urlencode($q) . '&p=1';

    $browserHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: nl-NL,nl;q=0.9,en-US;q=0.8,en;q=0.7',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Origin: https://tunebat.com',
        'Referer: https://tunebat.com/Search/' . rawurlencode($q),
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-site',
        'sec-ch-ua: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'Connection: keep-alive',
    ];

    $raw = curlGet($url, $browserHeaders)
        ?? httpGet($url, $browserHeaders);

    if (!$raw) return [];

    $data  = json_decode($raw, true);
    $items = $data['data']['items'] ?? [];
    if (!$items) return [];

    $results = [];
    foreach ($items as $t) {
        $title  = isset($t['a']) && is_string($t['a']) ? $t['a'] : '';
        $artist = '';
        if (isset($t['b'])) {
            $artist = is_array($t['b']) ? implode(', ', array_filter($t['b'])) : (string)$t['b'];
        }

        $duration = !empty($t['f']) ? msDuration((int)$t['f']) : '';
        $bpm      = isset($t['j']) && is_numeric($t['j']) ? (int)round((float)$t['j']) : null;

        $keyFields = ['key' => null, 'camelot' => null];
        if (isset($t['k']) && is_numeric($t['k']) && (int)$t['k'] >= 0 && (int)$t['k'] <= 11) {
            $keyFields = buildKeyFields((int)$t['k'], isset($t['l']) ? (int)$t['l'] : 1);
        }

        $pct = function($v) { return isset($v) && is_numeric($v) ? (int)round((float)$v * 100) : null; };

        // 'p' is popularity (0-100 integer in Tunebat), not a 0-1 float
        $popularity = isset($t['p']) && is_numeric($t['p']) ? (int)$t['p'] : null;

        $results[] = [
            'title'        => $title,
            'artist'       => $artist,
            'duration'     => $duration,
            'bpm'          => $bpm,
            'key'          => $keyFields['key'],
            'camelot'      => $keyFields['camelot'],
            'energy'       => $pct($t['m'] ?? null),
            'danceability' => $pct($t['n'] ?? null),
            'valence'      => $pct($t['o'] ?? null),
            'popularity'   => $popularity,
            'preview_url'  => null,
        ];
    }
    return $results;
}

/* =========================================
   GetSongBPM  (free API key via getsongbpm.com/api)
   Flow: 1 search request → up to 5 IDs → parallel detail fetches via curl_multi
   Fields: tempo (BPM string), key_of (e.g. "Em", "C#"), open_key (Camelot-style "2m"/"8B"),
           danceability (0-100), artist.name
   ========================================= */
function searchGetSongBpm(string $q): array {
    if (!GETSONGBPM_API_KEY || !function_exists('curl_init')) return [];

    $base = 'https://api.getsongbpm.com/';

    // Step 1 — search for matching songs, returns list of {id, name/title, ...}
    $searchUrl = $base . 'search/?api_key=' . urlencode(GETSONGBPM_API_KEY)
               . '&type=song&lookup=' . urlencode($q);
    $raw = curlGet($searchUrl, ['Accept: application/json']);
    if (!$raw) return [];

    $ids = [];
    foreach (json_decode($raw, true)['search'] ?? [] as $item) {
        if (!empty($item['id'])) $ids[] = $item['id'];
        if (count($ids) >= 5) break;
    }
    if (!$ids) return [];

    // Step 2 — fetch all song details in parallel
    $mh      = curl_multi_init();
    $handles = [];
    foreach ($ids as $id) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $base . 'song/?api_key=' . urlencode(GETSONGBPM_API_KEY) . '&id=' . urlencode($id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 0.5);
    } while ($running > 0);

    $results = [];
    foreach ($handles as $ch) {
        $body = curl_multi_getcontent($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if (!$body || $code !== 200) continue;
        $first = ltrim($body)[0] ?? '';
        if ($first !== '{' && $first !== '[') continue;

        $song = json_decode($body, true)['song'] ?? null;
        if (!$song || empty($song['title'])) continue;

        $bpm = isset($song['tempo']) && $song['tempo'] !== '' ? (int)$song['tempo'] : null;

        // key_of examples: "Em", "C#", "G", "Bbm", "F#m"
        $keyFields = ['key' => null, 'camelot' => null];
        if (!empty($song['key_of'])) {
            $parsed = parseGetSongBpmKey($song['key_of']);
            if ($parsed) $keyFields = buildKeyFields($parsed['keyIdx'], $parsed['mode']);
        }

        $results[] = [
            'title'        => $song['title'],
            'artist'       => $song['artist']['name'] ?? '',
            'duration'     => null,
            'bpm'          => $bpm,
            'key'          => $keyFields['key'],
            'camelot'      => $keyFields['camelot'],
            'energy'       => null,
            'danceability' => isset($song['danceability']) && $song['danceability'] !== '' ? (int)$song['danceability'] : null,
            'valence'      => null,
            'popularity'   => null,
            'preview_url'  => null,
        ];
    }
    curl_multi_close($mh);
    return $results;
}

/**
 * Converts GetSongBPM key notation ("Em", "C#", "Bbm", "F#") to
 * [keyIdx (0-11), mode (1=maj / 0=min)] for use with buildKeyFields().
 */
function parseGetSongBpmKey(string $keyOf): ?array {
    $isMinor = str_ends_with($keyOf, 'm');
    $note    = $isMinor ? substr($keyOf, 0, -1) : $keyOf;
    $mode    = $isMinor ? 0 : 1;

    // Normalise flat spellings to sharps
    static $flats = ['Db'=>'C#','Eb'=>'D#','Gb'=>'F#','Ab'=>'G#','Bb'=>'A#'];
    $note = $flats[$note] ?? $note;

    static $notes = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];
    $idx = array_search($note, $notes, true);
    if ($idx === false) return null;
    return ['keyIdx' => (int)$idx, 'mode' => $mode];
}

/* =========================================
   Spotify (requires credentials in config.php)
   ========================================= */
function searchSpotify(string $q, bool &$noBpm = false): array {
    $token = getSpotifyToken();
    if (!$token) return [];

    $raw = spotifyGet('https://api.spotify.com/v1/search?q=' . urlencode($q) . '&type=track&limit=10', $token);
    if (!$raw) return [];

    $data   = json_decode($raw, true);
    $tracks = $data['tracks']['items'] ?? [];
    if (!$tracks) return [];

    // Fetch audio features for all tracks in one call (deprecated for apps after Nov 2024)
    $ids     = implode(',', array_column($tracks, 'id'));
    $featRaw = spotifyGet('https://api.spotify.com/v1/audio-features?ids=' . $ids, $token);
    $featById = [];
    if ($featRaw) {
        foreach (json_decode($featRaw, true)['audio_features'] ?? [] as $f) {
            if ($f && isset($f['id'])) $featById[$f['id']] = $f;
        }
    }
    // If no features came back (403 = deprecated endpoint), flag it
    if (!$featById) $noBpm = true;

    $results = [];
    foreach ($tracks as $t) {
        $artists  = implode(', ', array_map(fn($a) => $a['name'], $t['artists'] ?? []));
        $duration = !empty($t['duration_ms']) ? msDuration((int)$t['duration_ms']) : '';
        $feat     = $featById[$t['id']] ?? null;

        $keyFields = ['key' => null, 'camelot' => null];
        if ($feat && isset($feat['key']) && $feat['key'] >= 0 && $feat['key'] <= 11) {
            $keyFields = buildKeyFields($feat['key'], $feat['mode'] ?? 1);
        }

        $results[] = [
            'title'        => $t['name'] ?? '',
            'artist'       => $artists,
            'duration'     => $duration,
            'bpm'          => $feat ? (int)round((float)$feat['tempo']) : null,
            'key'          => $keyFields['key'],
            'camelot'      => $keyFields['camelot'],
            'energy'       => $feat ? (int)round((float)$feat['energy'] * 100) : null,
            'danceability' => $feat ? (int)round((float)$feat['danceability'] * 100) : null,
            'valence'      => $feat ? (int)round((float)$feat['valence'] * 100) : null,
            'popularity'   => $t['popularity'] ?? null,
            'preview_url'  => $t['preview_url'] ?? null,
            'spotify_id'   => $t['id'] ?? null,
        ];
    }
    return $results;
}

function spotifyGet(string $url, string $token): string|false {
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ];
    return curlGet($url, $headers)
        ?? httpGet($url, ['Authorization: Bearer ' . $token, 'Accept: application/json']);
}

function getSpotifyToken(): string|false {
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

/* =========================================
   MusicBrainz fallback (no BPM / key)
   ========================================= */
function searchMusicBrainz(string $q): array {
    $url = 'https://musicbrainz.org/ws/2/recording/?query=' . urlencode($q) . '&fmt=json&limit=10';
    $raw = httpGet($url, ['User-Agent: LiveGig/1.0 (macsnoeren@gmail.com)']);
    if (!$raw) return [];

    $results = [];
    foreach (json_decode($raw, true)['recordings'] ?? [] as $r) {
        $artist   = $r['artist-credit'][0]['artist']['name'] ?? '';
        $duration = !empty($r['length']) ? msDuration((int)$r['length']) : '';
        $results[] = [
            'title'        => $r['title'] ?? '',
            'artist'       => $artist,
            'duration'     => $duration,
            'bpm'          => null,
            'key'          => null,
            'camelot'      => null,
            'energy'       => null,
            'danceability' => null,
            'valence'      => null,
            'popularity'   => null,
            'preview_url'  => null,
        ];
    }
    return $results;
}
