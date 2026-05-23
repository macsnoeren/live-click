<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Dashboard — LiveGig';
require __DIR__ . '/includes/header.php';
?>
<script>document.body.classList.add('page-dashboard');</script>

<?php if (!$user['band_id']): ?>
<div class="alert alert-warning alert-dismissible page-alert fade show mb-0" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Je bent nog niet aan een band gekoppeld.</strong>
    <?php if ($user['role'] === 'admin'): ?>
        Ga naar <a href="admin.php" class="alert-link">Admin → Bands</a> om jezelf toe te voegen.
    <?php else: ?>
        Een admin moet jou koppelen aan een band.
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="db-wrap">

    <!-- Left: setlist -->
    <div class="db-col-left">
        <div class="card db-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-list-ol"></i> Actieve setlist
                    <span id="sl-dash-dur" class="badge bg-secondary ms-2" style="display:none"></span>
                </span>
                <select id="setlist-select" class="form-select form-select-sm w-auto"
                        onchange="loadSetlist(this.value)">
                    <option value="">— kies setlist —</option>
                </select>
            </div>
            <div id="setlist-songs" class="list-group list-group-flush db-list">
                <div class="list-group-item text-muted">Selecteer een setlist</div>
            </div>
        </div>
    </div>

    <!-- Right: song detail + all songs -->
    <div class="db-col-right">

        <div class="card" id="song-detail-card" style="display:none!important">
            <div class="card-body py-2 px-3">

                <!-- Top row: title / artist / meta on left, BPM on right -->
                <div class="d-flex align-items-start gap-3">
                    <div class="flex-grow-1 min-w-0">
                        <h4 id="detail-title" class="mb-0 fw-bold text-white text-truncate"></h4>
                        <div class="text-muted small mt-1" id="detail-artist"></div>
                        <div class="small mt-1" id="detail-starts-wrap" style="display:none">
                            <span class="text-muted">Start:</span>
                            <strong id="detail-starts"></strong>
                            <span id="detail-duration" class="text-muted ms-2"></span>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <div class="bpm-big" id="detail-bpm">--</div>
                        <div class="text-muted" style="font-size:0.7rem">BPM</div>
                    </div>
                </div>

                <!-- Notes / description -->
                <div id="detail-desc" class="mt-2" style="display:none;font-size:0.82rem;color:#ccc;white-space:pre-wrap;border-left:2px solid #333;padding-left:8px"></div>

                <!-- Drum structure SVG -->
                <div id="detail-drum" class="mt-2" style="display:none;overflow-x:auto;background:#0b0b0b;border:1px solid #222;border-radius:5px;padding:8px 6px"></div>

            </div>
        </div>

        <div class="card db-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-music-note-beamed"></i> Alle nummers</span>
                <input type="search" id="song-search" class="form-control form-control-sm"
                       style="width:180px" placeholder="Zoek nummer...">
            </div>
            <div id="all-songs" class="list-group list-group-flush db-list">
                <div class="list-group-item text-muted">Laden...</div>
            </div>
        </div>

    </div>
</div>

<?php
$extraScripts = '<script>
var BAND_ID = ' . ($user['band_id'] ? (int)$user['band_id'] : 'null') . ';
$(function() {
    loadAllSongs();
    loadSetlistDropdown();
    $("#song-search").on("input", function() { filterSongs($(this).val()); });
});
</script>';
require __DIR__ . '/includes/footer.php';
?>
