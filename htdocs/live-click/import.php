<?php
/**
 * Import songs from data.js into the database.
 * Admin only. Run via browser: /import.php
 * Can also be run via CLI: php import.php [band_id]
 */

$cli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/bootstrap.php';

if (!$cli) {
    require_once APP_ROOT . '/includes/auth.php';
    requireAdmin();
    $user = currentUser();
} else {
    require_once APP_ROOT . '/includes/db.php';
    $user = ['id' => 1];
}

$db = getDB();

/* =========================================
   Parse data.js
   ========================================= */

function parseSongsFromDataJs(string $path): array|string {
    $content = @file_get_contents($path);
    if ($content === false) return 'Kan data.js niet lezen.';
    if (!preg_match('/var\s+_songs\s*=\s*(\[.*?\]);/s', $content, $m)) {
        return 'Kan _songs array niet vinden in data.js.';
    }
    $songs = json_decode($m[1], true);
    if (!is_array($songs)) return 'JSON-fout: ' . json_last_error_msg();
    return $songs;
}

function extractDuration(string $desc): ?string {
    // Match (MM:SS) or (H:MM:SS), skip 00:00
    if (preg_match('/\((\d{1,2}:\d{2})\)/', $desc, $m)) {
        return $m[1] !== '00:00' ? $m[1] : null;
    }
    return null;
}

function cleanBpm(string|int|null $val): ?int {
    $v = trim((string)($val ?? ''));
    if ($v === '' || !is_numeric($v)) return null;
    $i = (int)$v;
    return ($i > 0 && $i <= 400) ? $i : null;
}

function normaliseArtist(string $a): string {
    return trim($a);
}

/* =========================================
   Build normalised preview list
   ========================================= */

$rawSongs = parseSongsFromDataJs(__DIR__ . '/data.js');
$parseError = is_string($rawSongs) ? $rawSongs : null;
if ($parseError) $rawSongs = [];

$songs = [];
foreach ($rawSongs as $s) {
    $title  = trim($s['title']  ?? '');
    $artist = normaliseArtist($s['artist'] ?? '');
    if (!$title || !$artist) continue;

    // Some entries in data.js have bpm and starts swapped (e.g. ids 34-35)
    $bpmRaw    = trim((string)($s['bpm']    ?? ''));
    $startsRaw = trim((string)($s['starts'] ?? ''));
    $bpm       = cleanBpm($bpmRaw);
    $starts    = $startsRaw;

    // Detect swap: bpm field contains text, starts field contains a number
    if ($bpm === null && $bpmRaw !== '' && is_numeric($startsRaw)) {
        $bpm    = cleanBpm($startsRaw);
        $starts = $bpmRaw;
    }

    $desc = trim($s['description'] ?? '');
    $dur  = extractDuration($desc);

    $songs[] = compact('title', 'artist', 'bpm', 'dur', 'starts', 'desc');
}

/* =========================================
   CLI mode
   ========================================= */

if ($cli) {
    $bandId = isset($argv[1]) ? (int)$argv[1] : null;

    // Create / find Blast! band
    if (!$bandId) {
        $row = $db->prepare('SELECT id FROM bands WHERE name=?');
        $row->execute(['Blast!']);
        $b = $row->fetch();
        if (!$b) {
            $db->prepare('INSERT INTO bands (name) VALUES (?)')->execute(['Blast!']);
            $bandId = (int)$db->lastInsertId();
            echo "Band 'Blast!' aangemaakt (id $bandId)\n";
        } else {
            $bandId = (int)$b['id'];
            echo "Band 'Blast!' gevonden (id $bandId)\n";
        }
    }

    $check = $db->prepare('SELECT id FROM songs WHERE LOWER(TRIM(title))=LOWER(TRIM(?)) AND band_id' . ($bandId ? '=?' : ' IS NULL'));
    $ins   = $db->prepare('INSERT OR IGNORE INTO songs (title,artist,bpm,duration,starts,description,band_id,created_by) VALUES (?,?,?,?,?,?,?,?)');
    $imp = $skip = 0;
    foreach ($songs as $s) {
        $params = [$s['title']];
        if ($bandId) $params[] = $bandId;
        $check->execute($params);
        if ($check->fetch()) { $skip++; continue; }
        $ins->execute([$s['title'], $s['artist'], $s['bpm'], $s['dur'], $s['starts'] ?: null, $s['desc'] ?: null, $bandId, $user['id']]);
        $imp++;
    }
    // Ensure admin is member of the band
    $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id,role) VALUES (?,?,?)')->execute([$user['id'], $bandId, 'leader']);
    echo "Klaar! $imp geïmporteerd, $skip overgeslagen.\n";
    exit;
}

/* =========================================
   Web: handle POST
   ========================================= */

$imported = null;
$skipped  = null;
$postErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$parseError) {
    $bandId  = (int)($_POST['band_id'] ?? 0) ?: null;
    $replace = !empty($_POST['replace']);
    $createBand = trim($_POST['new_band'] ?? '');

    // Optionally create a new band
    if ($createBand) {
        $db->prepare('INSERT INTO bands (name) VALUES (?)')->execute([$createBand]);
        $bandId = (int)$db->lastInsertId();
        // Add current admin as leader
        $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id,role) VALUES (?,?,?)')
           ->execute([$user['id'], $bandId, 'leader']);
    }

    $bandCond = $bandId ? 'AND band_id = ?' : 'AND band_id IS NULL';
    $checkStmt  = $db->prepare("SELECT id FROM songs WHERE LOWER(TRIM(title))=LOWER(TRIM(?)) AND LOWER(TRIM(artist))=LOWER(TRIM(?)) $bandCond");
    $insertStmt = $db->prepare('INSERT INTO songs (title,artist,bpm,duration,starts,description,band_id,created_by) VALUES (?,?,?,?,?,?,?,?)');
    $updateStmt = $db->prepare('UPDATE songs SET bpm=?,duration=?,starts=?,description=? WHERE id=?');

    $imported = 0;
    $skipped  = 0;

    foreach ($songs as $s) {
        $params = [$s['title'], $s['artist']];
        if ($bandId) $params[] = $bandId;
        $checkStmt->execute($params);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            if ($replace) {
                $updateStmt->execute([$s['bpm'], $s['dur'], $s['starts'] ?: null, $s['desc'] ?: null, $existingId]);
                $imported++;
            } else {
                $skipped++;
            }
        } else {
            $insertStmt->execute([$s['title'], $s['artist'], $s['bpm'], $s['dur'], $s['starts'] ?: null, $s['desc'] ?: null, $bandId, $user['id']]);
            $imported++;
        }
    }
}

/* =========================================
   Web: render page
   ========================================= */

$bands = $db->query('SELECT id, name FROM bands ORDER BY name')->fetchAll();

$pageTitle = 'Import data.js — LiveGig';
require APP_ROOT . '/includes/header.php';
?>

<div class="container py-4" style="max-width:800px">
    <h4 class="mb-1"><i class="bi bi-upload"></i> Nummers importeren uit data.js</h4>
    <p class="text-muted small mb-4">
        <?= count($songs) ?> nummers gevonden in <code>data.js</code>.
    </p>

    <?php if ($parseError): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($parseError) ?></div>
    <?php endif; ?>

    <?php if ($imported !== null): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-1"></i>
        <strong><?= $imported ?></strong> nummer(s) geïmporteerd<?php if ($skipped): ?>,
        <strong><?= $skipped ?></strong> overgeslagen (bestonden al)<?php endif; ?>.
        <a href="songs.php" class="ms-3 btn btn-sm btn-outline-success">→ Repertoire</a>
    </div>
    <?php endif; ?>

    <?php if (!$parseError): ?>
    <form method="post" class="card p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Koppel aan bestaande band</label>
                <select name="band_id" class="form-select">
                    <option value="">— Geen band —</option>
                    <?php foreach ($bands as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Of maak een nieuwe band aan</label>
                <input type="text" name="new_band" class="form-control" placeholder="Naam nieuwe band">
                <div class="form-text">Laat leeg als je hierboven een band kiest.</div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="replace" id="chk-replace" value="1">
                    <label class="form-check-label small" for="chk-replace">Bestaande nummers bijwerken</label>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-upload me-1"></i> Importeer <?= count($songs) ?> nummers
            </button>
        </div>
    </form>

    <div class="card">
        <div class="card-header">Voorbeeld — alle <?= count($songs) ?> nummers</div>
        <div class="table-responsive" style="max-height:420px;overflow-y:auto">
            <table class="table table-dark table-sm mb-0">
                <thead style="position:sticky;top:0;z-index:1">
                    <tr>
                        <th>#</th>
                        <th>Titel</th>
                        <th>Artiest</th>
                        <th class="text-center">BPM</th>
                        <th>Duur</th>
                        <th>Wie start</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($songs as $i => $s): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($s['title']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($s['artist']) ?></td>
                        <td class="text-center">
                            <?php if ($s['bpm']): ?>
                            <span class="bpm-badge"><?= $s['bpm'] ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($s['dur'] ?? '—') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($s['starts']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>
