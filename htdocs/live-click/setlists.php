<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
$user = currentUser();
$canEdit = userCanEditBandContent((int)($user['band_id'] ?? 0));
$pageTitle = 'Setlists — LiveGig';
require APP_ROOT . '/includes/header.php';
?>

<div class="container-fluid px-3 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-list-ol"></i> Setlists
            <?php if ($user['band_name']): ?>
            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($user['band_name']) ?></span>
            <?php endif; ?>
        </h4>
        <?php if ($user['band_id'] && $canEdit): ?>
        <button class="btn btn-danger btn-sm" onclick="openCreateSetlist()">
            <i class="bi bi-plus-lg"></i> Nieuwe setlist
        </button>
        <?php endif; ?>
    </div>

    <div class="row g-3" id="setlists-container">
        <div class="col-12 text-muted">Laden...</div>
    </div>
</div>

<!-- Create Setlist Modal -->
<div class="modal fade" id="createSetlistModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="createSetlistTitle">Nieuwe setlist</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Naam setlist *</label>
                    <input type="text" id="sl-name" class="form-control" placeholder="Bijv. Optreden 14 juni">
                    <input type="hidden" id="sl-id">
                </div>
                <div class="row g-3">
                    <!-- Available songs -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Repertoire</span>
                                <input type="search" id="sl-song-filter" class="form-control form-control-sm w-auto" placeholder="Filteren...">
                            </div>
                            <div id="sl-available" class="list-group list-group-flush overflow-auto" style="max-height:400px">
                                <div class="list-group-item text-muted">Laden...</div>
                            </div>
                        </div>
                    </div>
                    <!-- Setlist order -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Setlist volgorde</span>
                                <span id="sl-total-time" class="badge bg-secondary">0 min</span>
                            </div>
                            <div id="sl-selected" class="list-group list-group-flush overflow-auto" style="max-height:400px">
                                <div class="list-group-item text-muted">Nog geen nummers</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button type="button" class="btn btn-danger" onclick="saveSetlist()">
                    <i class="bi bi-check-lg"></i> Opslaan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete setlist confirm -->
<div class="modal fade" id="deleteSlModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-dark">
            <div class="modal-body">
                Weet je zeker dat je <strong id="delete-sl-name"></strong> wilt verwijderen?
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Nee</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteSetlist()">Verwijderen</button>
            </div>
        </div>
    </div>
</div>

<?php
$bandId = (int)($user['band_id'] ?? 0);
$extraScripts = '<script>
var _allSongs = [];
var _slSongs = [];
var _deleteSlId = null;
var _setlistsData = [];
var _canEdit = ' . ($canEdit ? 'true' : 'false') . ';
var BAND_ID = ' . ($bandId ?: 'null') . ';

$(function() {
    loadSetlists();
});

/* E2EE: ontsleutel een lijst nummers indien versleuteld (anders no-op). */
function lgDecryptList(songs) {
    if (window.LGVault && songs && songs.some(function(s){ return s && s.enc_blob; })) {
        return LGVault.decryptSongs(BAND_ID, songs);
    }
    return Promise.resolve(songs || []);
}

function loadSetlists() {
    $.get("api/setlists.php", {band_id: BAND_ID}, function(data) {
        _setlistsData = data.setlists || [];
        var jobs = _setlistsData.map(function(sl) {
            return lgDecryptList(sl.songs || []).then(function(songs) { sl.songs = songs; });
        });
        Promise.all(jobs).then(function() { renderSetlists(_setlistsData); });
    });
}

function renderSetlists(lists) {
    var c = $("#setlists-container");
    c.empty();
    if (!lists.length) { c.html(\'<div class="col-12 text-muted">Nog geen setlists. Maak er één aan!</div>\'); return; }
    lists.forEach(function(sl, slIdx) {
        var songs = sl.songs || [];
        var dur = calcSetlistDuration(songs);
        var durTxt = songs.length ? (dur.estimated ? "~" : "") + fmtSecs(dur.totalSecs) : "";
        var estTip = dur.estimated ? " — " + dur.estimated + " nummer(s) zonder duur, geschat op gemiddeld " + fmtSecs(dur.avg) : "";
        var estIcon = dur.estimated ? \' <i class="bi bi-dash-circle text-warning ms-1" title="Geschatte duur\' + estTip + \'"></i>\' : "";
        var durBadge = durTxt ? \'<span class="text-muted" style="font-size:0.75rem;font-weight:400"><i class="bi bi-clock me-1"></i>\' + durTxt + estIcon + \'</span>\' : \'\';
        var html = \'<div class="col-md-6 col-xl-4"><div class="card setlist-card">\' +
            \'<div class="card-header d-flex justify-content-between align-items-center">\' +
            \'<div class="d-flex align-items-center gap-2">\' +
            \'<span class="fw-bold">\' + escHtml(sl.name) + \'</span>\' +
            durBadge +
            \'</div>\' +
            (_canEdit
                ? \'<div class="d-flex gap-1">\' +
                  \'<button class="btn btn-xs btn-outline-secondary" onclick="openEditSetlist(\' + sl.id + \')"><i class="bi bi-pencil"></i></button>\' +
                  \'<button class="btn btn-xs btn-outline-danger" onclick="openDeleteSetlist(\' + sl.id + \',\' + slIdx + \')"><i class="bi bi-trash"></i></button>\' +
                  \'</div>\'
                : \'\') +
            \'</div>\' +
            \'<div class="list-group list-group-flush">\';
        songs.forEach(function(s, songIdx) {
            html += \'<button class="list-group-item list-group-item-action list-group-item-dark d-flex justify-content-between align-items-center py-2" onclick="selectSongFromSetlist(\' + slIdx + \',\' + songIdx + \')">\'
                + \'<span><span class="text-muted me-2">\' + (songIdx+1) + \'.</span>\' + escHtml(s.title) + \'<span class="text-muted small ms-2">\' + escHtml(s.artist) + \'</span></span>\'
                + \'<span class="bpm-badge">\' + (s.bpm || "--") + \'</span>\'
                + \'</button>\';
        });
        html += \'</div></div></div>\';
        c.append(html);
    });
}

function selectSongFromSetlist(slIdx, songIdx) {
    var s = (_setlistsData[slIdx] || {songs: []}).songs[songIdx];
    if (s) selectSong(s);
}

function openCreateSetlist() {
    $("#createSetlistTitle").text("Nieuwe setlist");
    $("#sl-name").val("");
    $("#sl-id").val("");
    _slSongs = [];
    loadAvailableSongs();
    renderSlSelected();
    new bootstrap.Modal("#createSetlistModal").show();
}

function openEditSetlist(id) {
    $.get("api/setlists.php", {id: id}, function(data) {
        var sl = data.setlist;
        if (!sl) return;
        lgDecryptList(sl.songs || []).then(function(songs) {
            sl.songs = songs;
            $("#createSetlistTitle").text("Setlist bewerken");
            $("#sl-name").val(sl.name);
            $("#sl-id").val(sl.id);
            _slSongs = sl.songs || [];
            loadAvailableSongs();
            renderSlSelected();
            new bootstrap.Modal("#createSetlistModal").show();
        });
    });
}

function loadAvailableSongs() {
    if (_allSongs.length) { renderSlAvailable(); return; }
    $.get("api/songs.php", {band_id: BAND_ID}, function(data) {
        lgDecryptList(data.songs || []).then(function(songs) {
            _allSongs = songs;
            renderSlAvailable();
        });
    });
}

function renderSlAvailable() {
    var q = $("#sl-song-filter").val().toLowerCase();
    var c = $("#sl-available");
    c.empty();
    var found = false;
    _allSongs.forEach(function(s, idx) {
        if (q && !s.title.toLowerCase().includes(q) && !s.artist.toLowerCase().includes(q)) return;
        found = true;
        c.append(\'<button class="list-group-item list-group-item-action list-group-item-dark d-flex justify-content-between align-items-center py-1" onclick="addToSlSongs(\' + idx + \')">\'
            + \'<span>\' + escHtml(s.title) + \' <span class="text-muted small">\' + escHtml(s.artist) + \'</span></span>\'
            + \'<span class="bpm-badge">\' + (s.bpm || "--") + \'</span></button>\');
    });
    if (!found) c.append(\'<div class="list-group-item text-muted">Geen nummers</div>\');
}

$("#sl-song-filter").on("input", function() { renderSlAvailable(); });

function addToSlSongs(idx) {
    var s = _allSongs[idx];
    if (s) _slSongs.push(s);
    renderSlSelected();
}

function removeFromSlSongs(i) {
    _slSongs.splice(i, 1);
    renderSlSelected();
}

function moveSlSong(i, dir) {
    var j = i + dir;
    if (j < 0 || j >= _slSongs.length) return;
    var tmp = _slSongs[i]; _slSongs[i] = _slSongs[j]; _slSongs[j] = tmp;
    renderSlSelected();
}

function renderSlSelected() {
    var c = $("#sl-selected");
    c.empty();
    if (!_slSongs.length) { c.append(\'<div class="list-group-item text-muted">Nog geen nummers — klik op repertoire links</div>\'); updateSlTime(); return; }
    _slSongs.forEach(function(s, i) {
        c.append(\'<div class="list-group-item list-group-item-dark d-flex align-items-center gap-2 py-1">\'
            + \'<span class="text-muted me-1">\' + (i+1) + \'.</span>\'
            + \'<span class="flex-grow-1">\' + escHtml(s.title) + \' <span class="text-muted small">\' + escHtml(s.artist) + \'</span></span>\'
            + \'<span class="bpm-badge">\' + (s.bpm || "--") + \'</span>\'
            + \'<button class="btn btn-xs btn-outline-secondary" onclick="moveSlSong(\' + i + \',-1)"><i class="bi bi-chevron-up"></i></button>\'
            + \'<button class="btn btn-xs btn-outline-secondary" onclick="moveSlSong(\' + i + \',1)"><i class="bi bi-chevron-down"></i></button>\'
            + \'<button class="btn btn-xs btn-outline-danger" onclick="removeFromSlSongs(\' + i + \')"><i class="bi bi-x"></i></button>\'
            + \'</div>\');
    });
    updateSlTime();
}


function updateSlTime() {
    var r = calcSetlistDuration(_slSongs);
    var txt = _slSongs.length + " nummers";
    if (_slSongs.length) {
        txt += " · " + (r.estimated ? "~" : "") + fmtSecs(r.totalSecs);
        if (r.estimated) txt += " (" + r.estimated + " geschat)";
    }
    $("#sl-total-time").text(txt);
}

function saveSetlist() {
    var name = $("#sl-name").val().trim();
    if (!name) { alert("Geef de setlist een naam."); return; }
    if (!_slSongs.length) { alert("Voeg minstens één nummer toe."); return; }
    var data = {
        id: $("#sl-id").val(),
        name: name,
        band_id: ' . $bandId . ',
        songs: _slSongs.map(function(s){ return s.id; })
    };
    $.post("api/setlists.php", JSON.stringify(data), function(r) {
        if (r.ok) {
            bootstrap.Modal.getInstance("#createSetlistModal").hide();
            loadSetlists();
        } else { alert(r.error || "Fout bij opslaan"); }
    }, "json");
}

function openDeleteSetlist(id, idx) {
    _deleteSlId = id;
    // Naam opzoeken via index — geen songnaam inline in onclick
    var name = (_setlistsData[idx] && _setlistsData[idx].name) ? _setlistsData[idx].name : "?";
    $("#delete-sl-name").text(name);
    new bootstrap.Modal("#deleteSlModal").show();
}

function confirmDeleteSetlist() {
    $.ajax({ url: "api/setlists.php", type: "DELETE", data: JSON.stringify({id: _deleteSlId}),
        contentType: "application/json", success: function() {
            bootstrap.Modal.getInstance("#deleteSlModal").hide();
            loadSetlists();
        }
    });
}
</script>';
require APP_ROOT . '/includes/footer.php';
?>
