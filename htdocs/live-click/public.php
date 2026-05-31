<?php
/**
 * Publieke setlijstpagina — geen login vereist.
 * URL: public.php?t=SHARE_TOKEN
 */
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/db.php';

$token = trim($_GET['t'] ?? '');
if (!$token) { http_response_code(404); die('Ongeldige link.'); }

$db   = getDB();
// Token in DB is een SHA-256-hash van de plaintext-token in de URL.
$stmt = $db->prepare('SELECT id, name FROM bands WHERE share_token=?');
$stmt->execute([hash('sha256', $token)]);
$band = $stmt->fetch();
if (!$band) { http_response_code(404); die('Link niet gevonden of verlopen.'); }

// Setlists met nummers ophalen
$stmt = $db->prepare('SELECT id, name FROM setlists WHERE band_id=? ORDER BY name COLLATE NOCASE ASC');
$stmt->execute([$band['id']]);
$setlists = $stmt->fetchAll();

foreach ($setlists as &$sl) {
    $s = $db->prepare(
        'SELECT s.title, s.artist, s.bpm, s.duration, s.starts
           FROM setlist_songs ss
           JOIN songs s ON s.id = ss.song_id
          WHERE ss.setlist_id = ?
          ORDER BY ss.position'
    );
    $s->execute([$sl['id']]);
    $sl['songs'] = $s->fetchAll();
}
unset($sl);

$bandName = htmlspecialchars($band['name']);
$hasLists = !empty($setlists);
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $bandName ?> — Setlijsten</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* ── Donker thema (scherm) ─────────────────────────────────────── */
        body { background:#0b0b0b; color:#e0e0e0; font-family:system-ui,Arial,sans-serif; }

        .pub-header {
            background:#111;
            border-bottom:1px solid #222;
            padding:18px 24px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            flex-wrap:wrap;
            gap:12px;
        }
        .pub-brand { font-size:1.4rem; font-weight:800; letter-spacing:4px;
                     color:#cc0000; text-transform:uppercase; }
        .pub-band  { font-size:1rem; color:#888; }

        .pub-controls {
            background:#111;
            border-bottom:1px solid #1e1e1e;
            padding:10px 24px;
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }

        .pub-setlist  { background:#0f0f0f; border:1px solid #222; border-radius:8px;
                        margin:16px 24px; padding:0 0 8px; }
        .pub-sl-title { font-size:1rem; font-weight:700; color:#fff;
                        padding:14px 18px 10px; border-bottom:1px solid #1e1e1e; }
        .pub-sl-meta  { font-size:0.75rem; color:#555; margin-left:10px; font-weight:400; }

        table.pub-table { width:100%; border-collapse:collapse; }
        table.pub-table thead th {
            background:#181818; color:#777; font-size:0.7rem;
            text-transform:uppercase; letter-spacing:.5px;
            padding:6px 14px; border-bottom:1px solid #222;
        }
        table.pub-table tbody tr { border-bottom:1px solid #1a1a1a; }
        table.pub-table tbody tr:last-child { border-bottom:none; }
        table.pub-table td { padding:8px 14px; font-size:0.88rem; vertical-align:middle; }
        table.pub-table td.num { color:#444; width:36px; text-align:right; font-variant-numeric:tabular-nums; }
        table.pub-table td.title { font-weight:600; color:#e8e8e8; }
        table.pub-table td.artist { color:#888; }
        table.pub-table td.bpm    { color:#cc0000; font-weight:700; font-variant-numeric:tabular-nums;
                                    white-space:nowrap; font-size:0.8rem; }
        table.pub-table td.dur    { color:#555; font-size:0.78rem; white-space:nowrap; }
        table.pub-table td.starts { color:#888; font-size:0.78rem; }

        .pub-empty { color:#555; padding:20px 18px; font-size:0.85rem; }

        .select-dark { background:#181818; border:1px solid #2e2e2e; color:#e0e0e0;
                       border-radius:6px; padding:6px 12px; font-size:0.9rem; }
        .select-dark:focus { outline:none; border-color:#cc0000;
                             box-shadow:0 0 0 2px rgba(204,0,0,.2); }

        .btn-print { background:#cc0000; color:#fff; border:none; border-radius:6px;
                     padding:7px 18px; font-size:0.88rem; font-weight:600; cursor:pointer;
                     display:inline-flex; align-items:center; gap:6px; }
        .btn-print:hover { background:#e60000; }

        .no-setlists { text-align:center; padding:60px 24px; color:#555; }

        /* ── Print / PDF ───────────────────────────────────────────────── */
        @media print {
            * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }

            body { background:#fff !important; color:#000 !important; font-size:11pt; }

            .pub-header  { background:#fff !important; border-bottom:1px solid #ccc !important;
                           padding:12pt 16pt; }
            .pub-brand   { color:#cc0000 !important; font-size:16pt; }
            .pub-band    { color:#444 !important; }
            .no-print    { display:none !important; }

            .pub-controls { display:none !important; }

            /* Toon ALLE setlists bij printen */
            .pub-setlist { display:block !important; background:#fff !important;
                           border:1px solid #ccc !important; margin:12pt 16pt;
                           page-break-inside:avoid; border-radius:0 !important; }
            .pub-sl-title { background:#f5f5f5 !important; color:#000 !important;
                            border-bottom:1px solid #ccc !important; padding:8pt 12pt; }

            table.pub-table { width:100%; }
            table.pub-table thead th {
                background:#eee !important; color:#333 !important;
                padding:5pt 10pt; border-bottom:1px solid #ccc !important;
            }
            table.pub-table tbody tr { border-bottom:1px solid #eee !important; }
            table.pub-table td { padding:5pt 10pt; color:#000 !important; }
            table.pub-table td.num    { color:#999 !important; }
            table.pub-table td.title  { color:#000 !important; }
            table.pub-table td.artist { color:#444 !important; }
            table.pub-table td.bpm    { color:#cc0000 !important; }
            table.pub-table td.dur    { color:#666 !important; }
            table.pub-table td.starts { color:#666 !important; }

            .pub-print-footer { display:block !important; text-align:center;
                                color:#bbb; font-size:8pt; margin-top:20pt; }
        }

        .pub-print-footer { display:none; }
    </style>
</head>
<body>

<!-- Header -->
<div class="pub-header">
    <div>
        <div class="pub-brand">LiveGig</div>
        <div class="pub-band"><?= $bandName ?></div>
    </div>
    <?php if ($hasLists): ?>
    <button class="btn-print no-print" onclick="window.print()">
        <i class="bi bi-printer"></i> Afdrukken / PDF
    </button>
    <?php endif; ?>
</div>

<?php if (!$hasLists): ?>
<div class="no-setlists">
    <i class="bi bi-list-ol" style="font-size:2rem;display:block;margin-bottom:12px"></i>
    Geen setlijsten beschikbaar voor deze band.
</div>

<?php else: ?>

<!-- Setlist selector -->
<div class="pub-controls no-print">
    <label style="font-size:.85rem;color:#888" for="sl-select">Setlijst:</label>
    <select id="sl-select" class="select-dark" onchange="showSetlist(this.value)">
        <?php foreach ($setlists as $i => $sl): ?>
        <option value="<?= $sl['id'] ?>"><?= htmlspecialchars($sl['name']) ?>
            (<?= count($sl['songs']) ?> nummers)</option>
        <?php endforeach; ?>
    </select>
    <span style="font-size:.75rem;color:#444;margin-left:4px">
        Bij afdrukken worden alle setlijsten geprint.
    </span>
</div>

<!-- Setlists (op scherm: één zichtbaar; bij print: allemaal) -->
<?php foreach ($setlists as $i => $sl): ?>
<div class="pub-setlist" id="sl-<?= $sl['id'] ?>"
     style="<?= $i > 0 ? 'display:none' : '' ?>">
    <div class="pub-sl-title">
        <?= htmlspecialchars($sl['name']) ?>
        <span class="pub-sl-meta"><?= count($sl['songs']) ?> nummers</span>
    </div>

    <?php if (empty($sl['songs'])): ?>
    <div class="pub-empty">Lege setlijst.</div>
    <?php else: ?>
    <table class="pub-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Titel</th>
                <th>Artiest</th>
                <th>BPM</th>
                <th>Duur</th>
                <th>Start</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sl['songs'] as $j => $song): ?>
            <tr>
                <td class="num"><?= $j + 1 ?></td>
                <td class="title"><?= htmlspecialchars($song['title']) ?></td>
                <td class="artist"><?= htmlspecialchars($song['artist']) ?></td>
                <td class="bpm"><?= $song['bpm'] ? htmlspecialchars($song['bpm']) : '<span style="color:#333">—</span>' ?></td>
                <td class="dur"><?= $song['duration'] ? htmlspecialchars($song['duration']) : '' ?></td>
                <td class="starts"><?= htmlspecialchars($song['starts'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="pub-print-footer">
    Gegenereerd door LiveGig · <?= $bandName ?>
</div>

<script>
var _setlists = <?= json_encode(array_column($setlists, 'id')) ?>;

function showSetlist(id) {
    _setlists.forEach(function(slId) {
        var el = document.getElementById('sl-' + slId);
        if (el) el.style.display = (slId == id) ? '' : 'none';
    });
}
</script>

<?php endif; ?>
</body>
</html>
