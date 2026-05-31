<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/includes/auth.php';
requireLogin();
$user = currentUser();
$canEdit = userCanEditBandContent((int)($user['band_id'] ?? 0));
$pageTitle = 'Nummers — LiveGig';
require APP_ROOT . '/includes/header.php';
?>

<div class="container-fluid px-3 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-music-note-beamed"></i> Repertoire
            <?php if ($user['band_name']): ?>
            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($user['band_name']) ?></span>
            <?php endif; ?>
        </h4>
        <div class="d-flex gap-2">
            <input type="search" id="song-filter" class="form-control form-control-sm" placeholder="Zoeken..." style="width:200px">
            <?php if ($canEdit): ?>
            <button class="btn btn-danger btn-sm" onclick="openAddSong()">
                <i class="bi bi-plus-lg"></i> Nummer toevoegen
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="table-spotify-embed" class="mb-2" style="display:none"></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-dark table-hover table-sm mb-0" id="songs-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>#</th>
                        <th>Titel</th>
                        <th>Artiest</th>
                        <th class="text-center">BPM</th>
                        <th>Duur</th>
                        <th>Wie start</th>
                        <th>Beschrijving</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="songs-tbody">
                    <tr><td colspan="9" class="text-muted">Laden...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Song Modal -->
<div class="modal fade" id="songModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="songModalTitle">Nummer toevoegen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Music search -->
                <div class="mb-3 p-3 bg-black rounded">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small text-muted mb-0">Zoek nummer online</label>
                        <?php
                        require_once APP_ROOT . '/includes/config.php';
                        if (SPOTIFY_CLIENT_ID): ?>
                        <span class="badge bg-success" title="Spotify API — BPM beschikbaar">
                            <i class="bi bi-spotify"></i> Spotify + BPM
                        </span>
                        <?php elseif (GETSONGBPM_API_KEY): ?>
                        <span class="badge bg-success" title="GetSongBPM — BPM beschikbaar">
                            <i class="bi bi-music-note-beamed"></i> GetSongBPM + BPM
                        </span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark" title="Geen BPM-bron geconfigureerd — Tunebat wordt geprobeerd maar is soms geblokkeerd. Voeg een API-sleutel toe voor betrouwbare BPM.">
                            <i class="bi bi-exclamation-triangle"></i> Geen BPM-bron
                            <a href="#" class="text-dark ms-1 fw-bold" title="Klik voor instructies" data-bs-toggle="modal" data-bs-target="#spotifyHelpModal">?</a>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="input-group">
                        <input type="text" id="search-query" class="form-control" placeholder="Bijv. Highway to Hell ACDC"
                               onkeydown="if(event.key==='Enter') searchMusic()">
                        <button class="btn btn-outline-secondary" onclick="searchMusic()">
                            <i class="bi bi-search"></i> Zoek
                        </button>
                    </div>
                    <div id="search-results" class="mt-2"></div>
                </div>

                <form id="song-form">
                    <input type="hidden" id="song-id">
                    <input type="hidden" id="song-preview-url">
                    <input type="hidden" id="song-spotify-id">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">Titel *</label>
                            <input type="text" id="song-title" class="form-control" required>
                            <div id="song-duplicate-warning" class="small text-warning mt-1" style="display:none"></div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">BPM</label>
                            <input type="number" id="song-bpm" class="form-control" min="1" max="400">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Toonsoort</label>
                            <input type="text" id="song-key" class="form-control" placeholder="bijv. A min">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Artiest *</label>
                            <input type="text" id="song-artist" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duur</label>
                            <input type="text" id="song-duration" class="form-control" placeholder="mm:ss">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Wie start</label>
                            <input type="text" id="song-starts" class="form-control" placeholder="Bijv. Drums, Gitaar">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Beschrijving / notities</label>
                            <textarea id="song-description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-flex justify-content-between align-items-baseline">
                                <span><i class="bi bi-mic"></i> Songtekst</span>
                                <span class="text-muted" style="font-size:0.72rem;font-weight:400">
                                    Op het dashboard ook automatisch op te halen
                                </span>
                            </label>
                            <textarea id="song-lyrics" class="form-control" rows="4"
                                      placeholder="Songtekst..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-music-note"></i> Akkoorden</label>
                            <textarea id="song-chords" class="form-control font-monospace" rows="4"
                                      placeholder="Bijv.&#10;Intro: Am  F  C  G&#10;Couplet: Am  F  C  G"
                                      autocomplete="off" spellcheck="false"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-flex justify-content-between align-items-baseline">
                                <span><i class="bi bi-music-note-list"></i> Drumstructuur</span>
                                <span class="text-muted" style="font-size:0.72rem;font-weight:400">
                                    <code class="text-muted">|</code>&nbsp;maat &nbsp;
                                    <code class="text-muted">-</code>&nbsp;rust &nbsp;
                                    <code class="text-muted">^</code>&nbsp;cymbaal &nbsp;
                                    <code class="text-muted">*</code>&nbsp;brake
                                </span>
                            </label>
                            <textarea id="song-drum-notation" class="form-control font-monospace" rows="5"
                                      placeholder="Intro: | | | |   | | | |&#10;Couplet: | | | |   | | | |    | | | |   | | | |&#10;Refrein: | | | |   | | | |    | | | |   | | | |&#10;Brug: | | | |   | | | |&#10;Outro: | | | |   | | | |"
                                      autocomplete="off" spellcheck="false"></textarea>
                            <div id="drum-preview" class="mt-2" style="display:none;overflow-x:auto;background:#0d0d0d;border:1px solid #1e1e1e;border-radius:6px;padding:6px"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-file-earmark-pdf"></i> Bladmuziek (PDF)
                                <span class="text-muted" style="font-size:0.72rem;font-weight:400">max 10 MB</span>
                            </label>
                            <div id="song-pdf-current" class="small mb-1" style="display:none">
                                <i class="bi bi-file-earmark-pdf text-danger"></i>
                                <a id="song-pdf-link" href="#" target="_blank" rel="noopener">Huidige PDF bekijken</a>
                                <button type="button" class="btn btn-xs btn-outline-danger ms-2" onclick="removePdf()">
                                    <i class="bi bi-trash"></i> Verwijderen
                                </button>
                            </div>
                            <input type="file" id="song-pdf-file" class="form-control" accept="application/pdf,.pdf">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button type="button" class="btn btn-danger" onclick="saveSong()">
                    <i class="bi bi-check-lg"></i> Opslaan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Spotify help -->
<div class="modal fade" id="spotifyHelpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-music-note-beamed"></i> BPM-bron instellen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <p>LiveGig probeert BPM op te halen via Tunebat, maar die site is soms geblokkeerd. Voeg een gratis API-sleutel toe voor betrouwbare BPM-data.</p>

                <h6 class="text-success mt-3"><i class="bi bi-star-fill"></i> Optie 1 — GetSongBPM (aanbevolen, gratis)</h6>
                <ol class="ps-3 mb-2">
                    <li>Ga naar <strong>getsongbpm.com/our-api/</strong> en maak een gratis account</li>
                    <li>Kopieer je API-sleutel</li>
                    <li>Zet hem in <code>includes/config.php</code>:
                        <pre class="bg-black p-2 rounded mt-1">define('GETSONGBPM_API_KEY', 'jouw_sleutel');</pre>
                    </li>
                </ol>

                <h6 class="text-muted mt-3"><i class="bi bi-spotify"></i> Optie 2 — Spotify</h6>
                <ol class="ps-3 mb-2">
                    <li>Ga naar <strong>developer.spotify.com/dashboard</strong> en log in</li>
                    <li>Klik <strong>Create app</strong> → vul naam in (bijv. "LiveGig")</li>
                    <li>Kopieer <strong>Client ID</strong> en <strong>Client Secret</strong></li>
                    <li>Zet ze in <code>includes/config.php</code>:
                        <pre class="bg-black p-2 rounded mt-1">define('SPOTIFY_CLIENT_ID',     'jouw_client_id');
define('SPOTIFY_CLIENT_SECRET', 'jouw_secret');</pre>
                    </li>
                </ol>
                <div class="alert alert-warning py-2 mb-0">
                    <strong>Let op:</strong> Spotify heeft het BPM-endpoint gedepreceerd voor apps aangemaakt na 27 november 2024. GetSongBPM is daarom de aanbevolen optie.
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Sluiten</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete confirm -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Nummer verwijderen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Weet je zeker dat je <strong id="delete-name"></strong> wilt verwijderen?
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Nee</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">Verwijderen</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
var _deleteSongId = null;
var _songsList = [];
var _canEdit = ' . ($canEdit ? 'true' : 'false') . ';
$(function() { loadSongsTable(); });

function loadSongsTable() {
    var bandId = ' . ($user['band_id'] ?? 'null') . ';
    $.get("api/songs.php", {band_id: bandId}, function(data) {
        _songsList = data.songs || [];
        renderSongsTable(_songsList);
    });
}

function renderSongsTable(songs) {
    var tbody = $("#songs-tbody");
    tbody.empty();
    if (!songs.length) { tbody.append(\'<tr><td colspan="9" class="text-muted">Geen nummers gevonden</td></tr>\'); return; }
    songs.forEach(function(s, i) {
        var playBtn = (s.spotify_id || s.preview_url)
            ? \'<td><button class="btn btn-xs btn-outline-success" title="Afspelen via Spotify" onclick="toggleTablePreview(\' + i + \')"><i class="bi bi-play-fill" id="play-icon-\' + i + \'"></i></button></td>\'
            : \'<td></td>\';
        var drumIcon = s.drum_notation ? \' <i class="bi bi-music-note-list text-warning ms-1" title="Drumstructuur beschikbaar" style="font-size:0.7rem"></i>\' : \'\';
        tbody.append(
            \'<tr data-title="\' + escHtml(s.title) + \'" data-artist="\' + escHtml(s.artist) + \'">\' +
            playBtn +
            \'<td class="text-muted">\' + (i+1) + \'</td>\' +
            \'<td class="fw-semibold">\' + escHtml(s.title) + drumIcon + \'</td>\' +
            \'<td class="text-muted">\' + escHtml(s.artist) + \'</td>\' +
            \'<td class="text-center"><span class="bpm-badge">\' + (s.bpm || "--") + \'</span></td>\' +
            \'<td class="text-muted">\' + escHtml(s.duration || "") + \'</td>\' +
            \'<td>\' + escHtml(s.starts || "") + \'</td>\' +
            \'<td class="text-muted small">\' + escHtml(s.description || "").substring(0,60) + \'</td>\' +
            (_canEdit
                ? \'<td><button class="btn btn-xs btn-outline-secondary me-1" onclick="openEditSong(\' + i + \')"><i class="bi bi-pencil"></i></button>\' +
                  \'<button class="btn btn-xs btn-outline-danger" onclick="openDeleteSong(\' + s.id + \',\' + i + \')"><i class="bi bi-trash"></i></button></td>\'
                : \'<td></td>\') +
            \'</tr>\'
        );
    });
}

$("#song-filter").on("input", function() {
    var q = $(this).val().toLowerCase();
    $("#songs-tbody tr").each(function() {
        var t = ($(this).data("title") || "").toLowerCase();
        var a = ($(this).data("artist") || "").toLowerCase();
        $(this).toggle(!q || t.includes(q) || a.includes(q));
    });
});

function openAddSong() {
    $("#songModalTitle").text("Nummer toevoegen");
    $("#song-form")[0].reset();
    $("#song-id").val("");
    $("#song-preview-url").val("");
    $("#song-spotify-id").val("");
    $("#song-drum-notation").val("");
    $("#song-lyrics").val("");
    $("#song-chords").val("");
    $("#song-pdf-file").val("");
    $("#song-pdf-current").hide();
    $("#drum-preview").hide().empty();
    $("#song-duplicate-warning").hide();
    $("#search-results").empty();
    new bootstrap.Modal("#songModal").show();
}

function openEditSong(i) {
    var s = _songsList[i];
    if (!s) return;
    $("#songModalTitle").text("Nummer bewerken");
    $("#song-id").val(s.id);
    $("#song-title").val(s.title);
    $("#song-artist").val(s.artist);
    $("#song-bpm").val(s.bpm);
    $("#song-key").val(s.song_key || "");
    $("#song-duration").val(s.duration);
    $("#song-starts").val(s.starts);
    $("#song-description").val(s.description);
    $("#song-preview-url").val(s.preview_url || "");
    $("#song-spotify-id").val(s.spotify_id || "");
    $("#song-drum-notation").val(s.drum_notation || "");
    $("#song-lyrics").val(s.lyrics || "");
    $("#song-chords").val(s.chords || "");
    $("#song-pdf-file").val("");
    if (s.pdf_path) {
        $("#song-pdf-link").attr("href", "api/pdf.php?song_id=" + s.id);
        $("#song-pdf-current").show();
    } else {
        $("#song-pdf-current").hide();
    }
    $("#song-duplicate-warning").hide();
    $("#search-results").empty();
    refreshDrumPreview(s.drum_notation || "");
    new bootstrap.Modal("#songModal").show();
}

function checkDuplicate() {
    var title = $("#song-title").val().trim().toLowerCase();
    var artist = $("#song-artist").val().trim().toLowerCase();
    var editingId = $("#song-id").val();
    $("#song-duplicate-warning").hide();
    if (!title) return;
    var matches = _songsList.filter(function(s) {
        if (editingId && s.id == editingId) return false;
        var sameTitle = s.title.trim().toLowerCase() === title;
        var sameArtist = !artist || s.artist.trim().toLowerCase() === artist;
        return sameTitle && sameArtist;
    });
    if (matches.length) {
        var names = matches.map(function(s) {
            return \'"\' + escHtml(s.title) + \'" — \' + escHtml(s.artist);
        }).join(\', \');
        $("#song-duplicate-warning").html(\'<i class="bi bi-exclamation-triangle me-1"></i>Al in repertoire: \' + names).show();
    }
}

$("#song-title, #song-artist").on("input", checkDuplicate);

function saveSong() {
    var data = {
        id: $("#song-id").val(),
        title: $("#song-title").val().trim(),
        artist: $("#song-artist").val().trim(),
        bpm: $("#song-bpm").val(),
        song_key: $("#song-key").val().trim(),
        duration: $("#song-duration").val().trim(),
        starts: $("#song-starts").val().trim(),
        description: $("#song-description").val().trim(),
        preview_url: $("#song-preview-url").val(),
        spotify_id:  $("#song-spotify-id").val(),
        drum_notation: $("#song-drum-notation").val().trim(),
        lyrics: $("#song-lyrics").val().trim(),
        chords: $("#song-chords").val().trim(),
        band_id: ' . ($user['band_id'] ?? 'null') . '
    };
    if (!data.title || !data.artist) { alert("Titel en artiest zijn verplicht."); return; }
    $.post("api/songs.php", data, function(r) {
        if (!r.ok) { alert(r.error || "Fout bij opslaan"); return; }
        var file = document.getElementById("song-pdf-file").files[0];
        if (file) {
            uploadPdf(r.id, file, finishSave, function(err) {
                alert("Nummer opgeslagen, maar PDF-upload mislukt: " + err);
                finishSave();
            });
        } else {
            finishSave();
        }
    }, "json").fail(function(xhr) {
        alert("Opslaan mislukt (HTTP " + xhr.status + "): " + (xhr.responseText || "onbekende fout"));
    });
}

function finishSave() {
    bootstrap.Modal.getInstance("#songModal").hide();
    loadSongsTable();
}

/* PDF uploaden via multipart (CSRF-header wordt door ajaxSend toegevoegd). */
function uploadPdf(songId, file, onOk, onErr) {
    var fd = new FormData();
    fd.append("song_id", songId);
    fd.append("pdf", file);
    $.ajax({
        url: "api/pdf.php", type: "POST", data: fd,
        processData: false, contentType: false, dataType: "json"
    }).done(function(r) {
        if (r.ok) { if (onOk) onOk(); }
        else      { if (onErr) onErr(r.error || "onbekende fout"); }
    }).fail(function(xhr) {
        if (onErr) onErr("HTTP " + xhr.status);
    });
}

/* PDF verwijderen bij het nummer dat in het formulier open staat. */
function removePdf() {
    var id = $("#song-id").val();
    if (!id) { $("#song-pdf-file").val(""); $("#song-pdf-current").hide(); return; }
    if (!confirm("PDF verwijderen?")) return;
    $.ajax({
        url: "api/pdf.php", type: "DELETE",
        data: JSON.stringify({ song_id: id }),
        contentType: "application/json", dataType: "json"
    }).done(function(r) {
        if (r.ok) { $("#song-pdf-current").hide(); $("#song-pdf-file").val(""); loadSongsTable(); }
        else      { alert(r.error || "Verwijderen mislukt"); }
    }).fail(function(xhr) {
        alert("Verwijderen mislukt (HTTP " + xhr.status + ")");
    });
}

function openDeleteSong(id, idx) {
    _deleteSongId = id;
    // Naam opzoeken via index — geen songnaam inline in onclick nodig
    var name = (_songsList[idx] && _songsList[idx].title) ? _songsList[idx].title : "?";
    $("#delete-name").text(name);
    new bootstrap.Modal("#deleteModal").show();
}

function confirmDelete() {
    $.ajax({ url: "api/songs.php", type: "DELETE", data: JSON.stringify({id: _deleteSongId}),
        contentType: "application/json", success: function(r) {
            bootstrap.Modal.getInstance("#deleteModal").hide();
            loadSongsTable();
        }
    });
}

// Search results stored here — index used in onclick to avoid quote-escaping issues
var _searchResults = [];
var _dbHits = [];

function searchMusic() {
    var q = $("#search-query").val().trim();
    if (!q) return;
    $("#search-results").html(\'<div class="search-loading"><i class="bi bi-hourglass-split"></i> Zoeken...</div>\');
    $.get("api/search.php", {q: q}, function(data) {
        _dbHits = data.db_hits || [];
        _searchResults = data.results || [];
        renderSearchResults(_searchResults, data.source || "", data.spotify_no_bpm || false);
    }).fail(function() {
        $("#search-results").html(\'<div class="search-loading text-danger">Zoeken mislukt.</div>\');
    });
}

function renderSearchResults(results, source, spotifyNoBpm) {
    var html = \'\';

    // ── Library hits ────────────────────────────────────────────────────────
    if (_dbHits.length) {
        html += \'<div class="search-source" style="color:#f0c040"><i class="bi bi-database-fill me-1"></i>Uit bibliotheek</div>\';
        html += \'<div class="search-result-list">\';
        _dbHits.forEach(function(r, i) {
            var badges = \'\';
            if (r.bpm)      badges += \'<span class="bpm-badge">\' + r.bpm + \'</span> \';
            if (r.song_key) badges += \'<span class="search-badge">\' + escHtml(r.song_key) + \'</span>\';
            html += \'<div class="search-result-item d-flex align-items-center gap-1">\'
                 + \'<button type="button" class="flex-grow-1 text-start border-0 bg-transparent p-0" onclick="pickDbResult(\' + i + \')">\'
                 + \'<div class="d-flex justify-content-between align-items-center gap-2">\'
                 + \'<div class="min-w-0"><div class="search-result-title text-truncate">\' + escHtml(r.title) + \'</div>\'
                 + \'<div class="search-result-artist text-truncate">\' + escHtml(r.artist)
                 + (r.duration ? \' <span class="search-result-dur">\' + r.duration + \'</span>\' : \'\') + \'</div></div>\'
                 + \'<div class="search-result-badges flex-shrink-0">\' + badges + \'</div>\'
                 + \'</div></button></div>\';
        });
        html += \'</div>\';
    }

    if (!results.length) {
        if (!_dbHits.length) html += \'<div class="search-loading">Geen resultaten gevonden.</div>\';
        $("#search-results").html(html);
        return;
    }

    var sourceNames = {tunebat: "Tunebat", getsongbpm: "GetSongBPM", spotify: "Spotify", musicbrainz: "MusicBrainz"};
    var hasBpm = results.some(function(r) { return r.bpm; });
    var src = sourceNames[source] || source;
    var bpmLbl = hasBpm ? \' <span class="text-success">· BPM ✓</span>\'
               : (source === \'spotify\' && spotifyNoBpm)
                   ? \' <span class="text-warning">· geen BPM <small>(audio-features gedepreceerd — gebruik GetSongBPM)</small></span>\'
                   : \' <span class="text-muted">· geen BPM</span>\';
    html += \'<div class="search-source">\' + src + bpmLbl + \'</div><div class="search-result-list">\';
    results.forEach(function(r, i) {
        var badges = \'\';
        if (r.bpm)                badges += \'<span class="bpm-badge">\' + r.bpm + \'</span> \';
        if (r.key)                badges += \'<span class="search-badge">\' + escHtml(r.key) + \'</span> \';
        if (r.camelot)            badges += \'<span class="search-badge search-badge-camelot" title="Camelot">\' + escHtml(r.camelot) + \'</span> \';
        if (r.energy != null)     badges += \'<span class="search-badge" title="Energie">⚡\' + r.energy + \'%</span> \';
        if (r.danceability != null) badges += \'<span class="search-badge" title="Dansbaar">💃\' + r.danceability + \'%</span> \';
        if (r.valence != null)    badges += \'<span class="search-badge" title="Sfeer / vrolijkheid">\' + valenceEmoji(r.valence) + r.valence + \'%</span> \';
        if (r.popularity != null) badges += \'<span class="search-badge search-badge-pop" title="Populariteit">★\' + r.popularity + \'</span>\';
        var playBtn = (r.preview_url || r.spotify_id)
            ? \'<button type="button" class="btn btn-xs btn-outline-success me-2 flex-shrink-0" title="Preview afspelen" onclick="event.stopPropagation(); toggleSearchPreview(\' + i + \')"><i class="bi bi-play-fill" id="sr-play-\' + i + \'"></i></button>\'
            : \'\';
        html += \'<div class="search-result-item d-flex align-items-center gap-1">\'
             + playBtn
             + \'<button type="button" class="flex-grow-1 text-start border-0 bg-transparent p-0" onclick="pickSearchResult(\' + i + \')">\'
             + \'<div class="d-flex justify-content-between align-items-center gap-2">\'
             + \'<div class="min-w-0"><div class="search-result-title text-truncate">\' + escHtml(r.title) + \'</div>\'
             + \'<div class="search-result-artist text-truncate">\' + escHtml(r.artist)
             + (r.duration ? \' <span class="search-result-dur">\' + r.duration + \'</span>\' : \'\') + \'</div></div>\'
             + \'<div class="search-result-badges flex-shrink-0">\' + badges + \'</div>\'
             + \'</div></button></div>\';
    });
    html += \'</div><div id="spotify-embed-container" class="mt-2" style="display:none"></div>\';
    $("#search-results").html(html);
}

function valenceEmoji(v) {
    if (v >= 70) return \'😄\';
    if (v >= 40) return \'😐\';
    return \'😔\';
}

function pickSearchResult(i) {
    var r = _searchResults[i];
    if (!r) return;
    stopPreview();
    $("#song-title").val(r.title);
    $("#song-artist").val(r.artist);
    if (r.duration)     $("#song-duration").val(r.duration);
    if (r.bpm)          $("#song-bpm").val(r.bpm);
    if (r.preview_url)  $("#song-preview-url").val(r.preview_url);
    if (r.spotify_id)   $("#song-spotify-id").val(r.spotify_id);
    if (r.key) {
        var keyVal = r.key;
        if (r.camelot) keyVal += \' (\' + r.camelot + \')\';
        $("#song-key").val(keyVal);
    }
    $("#search-results").empty();
    _searchResults = [];
    _dbHits = [];
    checkDuplicate();
}

function pickDbResult(i) {
    var r = _dbHits[i];
    if (!r) return;
    $("#song-title").val(r.title);
    $("#song-artist").val(r.artist);
    if (r.bpm)      $("#song-bpm").val(r.bpm);
    if (r.song_key) $("#song-key").val(r.song_key);
    if (r.duration) $("#song-duration").val(r.duration);
    $("#search-results").empty();
    _dbHits = [];
    _searchResults = [];
    checkDuplicate();
}

/* ---- Drum notation live preview ---- */
var _drumTimer = null;
$("#song-drum-notation").on("input", function() {
    clearTimeout(_drumTimer);
    var val = $(this).val();
    _drumTimer = setTimeout(function() { refreshDrumPreview(val); }, 280);
});

function refreshDrumPreview(notation) {
    notation = (notation || "").trim();
    if (!notation) { $("#drum-preview").hide().empty(); return; }
    $.ajax({
        url: "api/drum_preview.php",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({notation: notation}),
        dataType: "json",
        success: function(r) {
            if (r.ok && r.svg) { $("#drum-preview").html(r.svg).show(); }
            else               { $("#drum-preview").hide().empty(); }
        }
    });
}

/* ---- Preview audio / Spotify ---- */
var _previewAudio = null;
var _playingType  = null; // "search" or "table"
var _playingIdx   = -1;

function stopPreview() {
    if (_previewAudio) { _previewAudio.pause(); _previewAudio = null; }
    if (_playingType === "search" && _playingIdx >= 0) {
        $("#sr-play-" + _playingIdx).removeClass("bi-stop-fill").addClass("bi-play-fill");
        $("#spotify-embed-container").hide().empty();
    }
    if (_playingType === "table" && _playingIdx >= 0) {
        $("#play-icon-" + _playingIdx).removeClass("bi-stop-fill").addClass("bi-play-fill");
        $("#table-spotify-embed").hide().empty();
    }
    _playingType = null;
    _playingIdx  = -1;
}

/*
 * Probeer een MP3-preview URL af te spelen.
 * Bij mislukken: haal een verse URL op via de server (Spotify Tracks API).
 * Als er helemaal geen preview beschikbaar is: toon een "Open in Spotify"-knop.
 *
 * iconSel      = jQuery-selector van het play/stop-icoon
 * containerSel = jQuery-selector van de embed-container (voor fallback-knop)
 * spotifyId    = Spotify track-id (mag null zijn)
 * previewUrl   = opgeslagen URL (mag verlopen zijn)
 */
function _tryPlayUrl(previewUrl, spotifyId, iconSel, containerSel) {
    function showOpenSpotify() {
        if (spotifyId) {
            $(containerSel).html(
                \'<a href="https://open.spotify.com/track/\' + spotifyId + \'" target="_blank" \' +
                \'class="btn btn-sm btn-outline-success"><i class="bi bi-spotify me-1"></i>Open in Spotify</a>\'
            ).show();
        }
        stopPreview();
    }

    function playUrl(url) {
        $(iconSel).removeClass("bi-play-fill").addClass("bi-stop-fill");
        _previewAudio = new Audio(url);
        _previewAudio.volume = 0.6;
        _previewAudio.onended = stopPreview;
        var p = _previewAudio.play();
        if (p && p.catch) {
            p.catch(function() {
                _previewAudio = null;
                // Verse URL ophalen via server
                if (spotifyId) {
                    $(iconSel).removeClass("bi-stop-fill").addClass("bi-play-fill");
                    $(containerSel).html(\'<span class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Preview laden...</span>\').show();
                    $.get("api/preview.php", {spotify_id: spotifyId}, function(r) {
                        if (r.preview_url) {
                            $(containerSel).hide().empty();
                            playUrl(r.preview_url);
                        } else {
                            $(containerSel).empty();
                            showOpenSpotify();
                        }
                    }).fail(function() {
                        $(containerSel).empty();
                        showOpenSpotify();
                    });
                } else {
                    showOpenSpotify();
                }
            });
        }
    }

    if (previewUrl) {
        playUrl(previewUrl);
    } else if (spotifyId) {
        // Geen URL opgeslagen — haal meteen een verse op
        $(containerSel).html(\'<span class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Preview laden...</span>\').show();
        $.get("api/preview.php", {spotify_id: spotifyId}, function(r) {
            $(containerSel).hide().empty();
            if (r.preview_url) {
                playUrl(r.preview_url);
            } else {
                showOpenSpotify();
            }
        }).fail(function() {
            $(containerSel).empty();
            showOpenSpotify();
        });
    }
}

function toggleSearchPreview(i) {
    var r = _searchResults[i];
    if (!r || (!r.preview_url && !r.spotify_id)) return;
    if (_playingType === "search" && _playingIdx === i) { stopPreview(); return; }
    stopPreview();
    _playingType = "search";
    _playingIdx  = i;
    _tryPlayUrl(r.preview_url || null, r.spotify_id || null,
                "#sr-play-" + i, "#spotify-embed-container");
}

function toggleTablePreview(i) {
    var s = _songsList[i];
    if (!s || (!s.preview_url && !s.spotify_id)) return;
    if (_playingType === "table" && _playingIdx === i) { stopPreview(); return; }
    stopPreview();
    _playingType = "table";
    _playingIdx  = i;
    _tryPlayUrl(s.preview_url || null, s.spotify_id || null,
                "#play-icon-" + i, "#table-spotify-embed");
}

// Stop audio wanneer modal sluit
document.getElementById("songModal").addEventListener("hidden.bs.modal", stopPreview);
</script>';
require APP_ROOT . '/includes/footer.php';
?>
