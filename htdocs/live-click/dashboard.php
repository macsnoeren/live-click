<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Dashboard — LiveGig';
require APP_ROOT . '/includes/header.php';
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

<!-- Offline-melding (verborgen zolang de server bereikbaar is) -->
<div id="lg-offline-badge" style="display:none;background:#1a0000;border-bottom:1px solid #330000;
     color:#cc6666;font-size:0.8rem;padding:5px 20px;text-align:center">
    <i class="bi bi-wifi-off me-1"></i> Offline — werkt op lokale cache
    <span id="lg-cache-ts" class="ms-2" style="color:#884444"></span>
</div>

<div class="db-wrap">

    <!-- Left: setlist (incl. virtuele "Alle Nummers") + zoeken -->
    <div class="db-col-left">
        <div class="card db-card">
            <div class="card-header db-setlist-header">
                <div class="d-flex align-items-center gap-2">
                    <select id="setlist-select" class="form-select form-select-sm flex-grow-1"
                            onchange="loadSetlist(this.value)">
                        <option value="all">Alle Nummers</option>
                    </select>
                    <span id="sl-dash-dur" class="badge bg-secondary" style="display:none"></span>
                </div>
                <input type="search" id="dash-search" class="form-control form-control-sm mt-2"
                       placeholder="Zoek nummer...">
            </div>
            <div id="setlist-songs" class="list-group list-group-flush db-list">
                <div class="list-group-item text-muted">Laden...</div>
            </div>
        </div>
    </div>

    <!-- Right: notities / drumstructuur in tabs (titel/BPM/artiest staan in de header) -->
    <div class="db-col-right">
        <div class="card db-card db-detail-card" id="song-detail-card">
            <div class="card-header db-detail-header">
                <ul class="nav nav-tabs db-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-notes-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-notes" type="button" role="tab">
                            <i class="bi bi-card-text"></i> Notities
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-drum-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-drum" type="button" role="tab">
                            <i class="bi bi-music-note-list"></i> Drumstructuur
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content db-tab-content">
                <div class="tab-pane fade show active db-pane" id="tab-notes" role="tabpanel">
                    <div id="detail-desc" class="db-notes" style="display:none"></div>
                    <div id="detail-notes-empty" class="db-pane-empty">
                        <i class="bi bi-card-text"></i><span>Selecteer een nummer</span>
                    </div>
                </div>
                <div class="tab-pane fade db-pane" id="tab-drum" role="tabpanel">
                    <div id="detail-drum" class="db-drum" style="display:none"></div>
                    <div id="detail-drum-empty" class="db-pane-empty">
                        <i class="bi bi-music-note-list"></i><span>Geen drumstructuur</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
var BAND_ID = ' . ($user['band_id'] ? (int)$user['band_id'] : 'null') . ';
$(function() {
    loadAllSongs();        // vult de cache voor "Alle Nummers" + zoeken
    loadSetlistDropdown();
    loadSetlist("all");    // standaard: toon alle nummers
    $("#dash-search").on("input", function() { filterPanel($(this).val()); });
    // Drum-SVG passend maken zodra het tabblad zichtbaar wordt / bij resize
    $("#tab-drum-btn").on("shown.bs.tab", fitDrumSvg);
    $(window).on("resize", function() {
        if (document.getElementById("tab-drum-btn").classList.contains("active")) fitDrumSvg();
    });
});
</script>';
require APP_ROOT . '/includes/footer.php';
?>
